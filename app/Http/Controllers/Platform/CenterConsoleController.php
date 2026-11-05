<?php

namespace App\Http\Controllers\Platform;

use App\Enums\CenterStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Center;
use App\Models\CenterBalanceTransaction;
use App\Models\Tariff;
use App\Models\User;
use App\Rules\UzPhone;
use App\Services\AnalyticsService;
use App\Services\CenterBalanceService;
use App\Services\SubscriptionService;
use App\Support\Format;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** sAdmin: markazlar konsoli - ro'yxat, yaratish, tarif sovg'a qilish, balans tuzatish, to'xtatish/tiklash, tahlil. */
class CenterConsoleController extends Controller
{
    public function __construct(private SubscriptionService $subs, private CenterBalanceService $balance, private AnalyticsService $analytics) {}

    public function index()
    {
        $data = $this->analytics->platform();

        return view('platform.centers.index', ['rows' => $data['rows'], 'summary' => $data['summary']]);
    }

    public function analytics()
    {
        $data = $this->analytics->platform();

        return view('platform.analytics', ['rows' => $data['rows'], 'summary' => $data['summary']]);
    }

    public function create()
    {
        return view('platform.centers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:centers,name'],
            'branch_name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_phone' => ['required', 'string', new UzPhone],
        ], [], ['name' => 'Markaz nomi', 'branch_name' => 'Birinchi filial nomi', 'owner_name' => 'Direktor ismi', 'owner_phone' => 'Direktor telefoni']);

        $phone = Format::canonicalPhone($data['owner_phone']) ?? $data['owner_phone'];
        if (User::where('role', Role::Owner)->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['owner_phone' => 'Bu telefon bilan direktor allaqachon mavjud.']);
        }

        $password = Str::lower(Str::random(10));

        [$center, $owner] = DB::transaction(function () use ($data, $phone, $password, $request) {
            $center = Center::create([
                'name' => $data['name'], 'code' => Center::makeCode($data['name']), 'status' => CenterStatus::Pending,
                'owner_name' => $data['owner_name'], 'owner_phone' => $phone, 'created_by' => $request->user()->id,
            ]);
            Branch::create([
                'center_id' => $center->id, 'name' => $data['branch_name'], 'code' => Branch::makeCode($data['branch_name']),
                'status' => \App\Enums\BranchStatus::Active, 'opened_at' => now(),
            ]);

            $base = Str::slug($center->code, '').'.direktor';
            $username = $base;
            for ($i = 2; User::where('username', $username)->exists(); $i++) {
                $username = $base.$i;
            }
            $owner = User::create([
                'center_id' => $center->id, 'role' => Role::Owner, 'name' => $data['owner_name'],
                'username' => $username, 'phone' => $phone, 'password' => $password, 'created_by' => $request->user()->id,
            ]);
            $center->update(['owner_user_id' => $owner->id]);

            return [$center, $owner];
        });

        AuditLog::record('center.created', null, "Yangi markaz yaratildi: {$center->name}");

        $res = redirect()->route('platform.centers.show', $center)
            ->with('success', "«{$center->name}» markazi yaratildi. Tarif berilmaguncha u faqat ko'rish rejimida.");

        // Telegram bot bor bo'lsa: parolni sAdmin ko'rmaydi - direktor havola orqali botdan oladi (7 kun amal qiladi)
        $link = app(\App\Services\Telegram\TelegramClient::class)->deepLink('own_'.app(\App\Services\Telegram\TelegramLinkService::class)->makeToken($owner, 'own', 60 * 24 * 7));

        return $link
            ? $res->with('owner_link', $link)
            : $res->with('credentials', ['login' => $owner->username, 'password' => $password]);
    }

    public function show(Center $center)
    {
        abort_if($center->status === CenterStatus::Deleted, 404);

        return view('platform.centers.show', [
            'center' => $center,
            'a' => $this->analytics->forCenter($center),
            'limits' => $this->subs->limits($center),
            'sub' => $this->subs->current($center),
            'history' => $this->subs->history($center, 10),
            'txns' => CenterBalanceTransaction::withoutGlobalScopes()->where('center_id', $center->id)->latest('id')->limit(15)->get(),
            'tariffs' => Tariff::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Markazni butunlay o'chiradi: barcha filiallar, foydalanuvchilar va ma'lumotlar (`CenterWiper`). Qaytarib bo'lmaydi.
     * Tasdiq: markaz nomini yozish + sAdmin paroli. Demo markaz o'chirilmaydi. Rozilik jurnali saqlanadi.
     */
    public function destroy(Request $request, Center $center, \App\Services\CenterWiper $wiper): RedirectResponse
    {
        abort_if($center->status === CenterStatus::Deleted, 404);

        if ($center->is_demo) {
            return back()->with('error', "Demo markazni o'chirib bo'lmaydi.");
        }

        $request->validate(['confirm_name' => ['required', 'string'], 'password' => ['required', 'string']], [], ['confirm_name' => 'Markaz nomi', 'password' => 'Parol']);

        if (trim($request->input('confirm_name')) !== $center->name) {
            throw ValidationException::withMessages(['confirm_name' => "Markaz nomi to'liq mos kelmadi."]);
        }
        if (! \Illuminate\Support\Facades\Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => "Parol noto'g'ri."]);
        }

        $branches = Branch::withoutGlobalScopes()->where('center_id', $center->id)->count();
        $students = User::withoutGlobalScopes()->where('center_id', $center->id)->where('role', Role::Student)->count();
        $name = $center->name;

        $wiper->wipe($center);
        // Nomi bo'shatiladi (nom noyob): xuddi shu nomli markazni qayta ochish mumkin
        $center->forceFill(['status' => CenterStatus::Deleted, 'deleted_at' => now(), 'name' => mb_substr($name, 0, 100)." [o'chirilgan #{$center->id}]", 'logo_path' => null])->save();
        AuditLog::record('center.deleted', null, "sAdmin markazni o'chirdi: {$name} ({$branches} filial, {$students} o'quvchi)");

        return redirect()->route('platform.centers.index')->with('success', "«{$name}» markazi va uning barcha ma'lumoti o'chirildi.");
    }

    public function grant(Request $request, Center $center): RedirectResponse
    {
        $data = $request->validate(['tariff_id' => ['required', 'exists:tariffs,id'], 'days' => ['required', 'integer', 'min:1', 'max:3660'], 'note' => ['nullable', 'string', 'max:255']]);

        try {
            $this->subs->grant($center, Tariff::findOrFail($data['tariff_id']), (int) $data['days'], $data['note'] ?? null, $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record('center.tariff_granted', null, "{$center->name}: tarif sovg'a qilindi ({$data['days']} kun)");

        return back()->with('success', "Tarif {$data['days']} kunga berildi.");
    }

    public function adjust(Request $request, Center $center): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'not_in:0', 'between:-2000000000,2000000000'], 'reason' => ['required', 'string', 'max:255']]);

        try {
            $this->balance->adjust($center, (int) $data['amount'], $data['reason'], $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record('center.balance_adjusted', null, "{$center->name}: balans tuzatildi ({$data['amount']}): {$data['reason']}");

        return back()->with('success', 'Balans tuzatildi.');
    }

    public function suspend(Center $center): RedirectResponse
    {
        abort_if($center->is_demo, 403);
        $center->update(['status' => CenterStatus::Suspended]);
        \Illuminate\Support\Facades\Schema::hasTable('sessions') && DB::table('sessions')->whereIn('user_id', User::where('center_id', $center->id)->select('id'))->delete();
        AuditLog::record('center.suspended', null, "Markaz to'xtatildi: {$center->name}");

        return back()->with('success', "Markaz to'xtatildi: xodimlar kira olmaydi.");
    }

    public function resume(Center $center): RedirectResponse
    {
        $center->update(['status' => CenterStatus::Pending]);
        $this->subs->sync($center->refresh());
        AuditLog::record('center.resumed', null, "Markaz tiklandi: {$center->name}");

        return back()->with('success', 'Markaz tiklandi.');
    }
}
