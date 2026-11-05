<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Center;
use App\Models\CenterBalanceTransaction;
use App\Models\PaymentCard;
use App\Models\PlatformSetting;
use App\Models\Tariff;
use App\Models\TopupRequest;
use App\Services\CenterBalanceService;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use App\Support\Xlsx\XlsxWriter;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Direktorning "Markaz" bo'limi: obuna, balans, to'ldirish, limitlar, eksport. Faqat direktor (Owner) o'z markazi uchun. */
class CenterPanelController extends Controller
{
    public function __construct(private SubscriptionService $subs, private CenterBalanceService $balance, private UsageService $usage) {}

    private function center(Request $request): Center
    {
        return Center::findOrFail($request->user()->center_id);
    }

    public function index(Request $request)
    {
        $center = $this->center($request);
        $limits = $this->subs->limits($center);

        return view('center.index', [
            'center' => $center,
            'limits' => $limits,
            'sub' => $this->subs->current($center),
            'upcoming' => $this->subs->upcoming($center),
            'balance' => $this->balance->balance($center),
            'students' => $this->subs->activeStudents($center->id),
            'branches' => \App\Models\Branch::where('status', 'active')->count(),
            'smsUsed' => $this->usage->used($center, 'sms'),
            'aiUsed' => $this->usage->used($center, 'ai'),
            'txns' => CenterBalanceTransaction::where('center_id', $center->id)->latest('id')->limit(15)->get(),
            'exportAllowed' => $this->exportAllowed($center),
        ]);
    }

    public function analytics(Request $request, \App\Services\AnalyticsService $analytics)
    {
        $center = $this->center($request);

        return view('center.analytics', ['center' => $center, 'a' => $analytics->forCenter($center)]);
    }

    public function tariffs(Request $request)
    {
        $center = $this->center($request);

        return view('center.tariffs', [
            'center' => $center,
            'tariffs' => Tariff::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'sub' => $this->subs->current($center),
            'upcoming' => $this->subs->upcoming($center),
            'history' => $this->subs->history($center, 10),
            'balance' => $this->balance->balance($center),
            'previews' => Tariff::where('is_active', true)->get()->mapWithKeys(fn ($t) => [$t->id => collect(['month', 'year'])->mapWithKeys(fn ($p) => [$p => ($p === 'year' ? $t->price_yearly : $t->price_monthly) ? app(\App\Services\BranchLimitService::class)->previewFor($center, $t, $p) : null])->all()])->all(),
            'lockedNow' => app(\App\Services\BranchLimitService::class)->locked($center),
        ]);
    }

    public function buy(Request $request, Tariff $tariff): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'in:month,year']]);
        $center = $this->center($request);

        // Past tarif filial limitidan oshib ketsa, direktor ogohlantirilganini tasdiqlashi shart
        $preview = app(\App\Services\BranchLimitService::class)->previewFor($center, $tariff, $data['period']);
        if ($preview && ! $request->boolean('ack_branches')) {
            return back()->with('error', "Bu tarifda ".$tariff->max_branches." ta filialgacha ishlaydi. Faqat ko'rish rejimiga o'tadi: ".implode(', ', $preview['names']).' ('.$preview['from']->format('d.m.Y').' dan). Davom etish uchun ogohlantirishni tasdiqlang.');
        }

        try {
            $sub = $this->subs->purchase($center, $tariff, $data['period'], $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record('center.tariff_purchased', null, "Tarif sotib olindi: {$tariff->name} ({$data['period']}), {$sub->price_paid} so'm");

        return redirect()->route('center.index')->with('success', "«{$tariff->name}» tarifi yoqildi: ".$sub->ends_at->format('d.m.Y').' gacha.');
    }

    public function topupForm(Request $request)
    {
        $center = $this->center($request);

        return view('center.topup', [
            'center' => $center,
            'cards' => PaymentCard::where('is_active', true)->orderBy('id')->get(),
            'balance' => $this->balance->balance($center),
            'requests' => TopupRequest::where('center_id', $center->id)->latest('id')->limit(20)->get(),
        ]);
    }

    public function topupStore(Request $request): RedirectResponse
    {
        $center = $this->center($request);
        $data = $request->validate([
            'card_id' => ['required', \Illuminate\Validation\Rule::exists('payment_cards', 'id')->where('is_active', true)],
            'amount_claimed' => ['required', 'integer', 'min:1000', 'max:2000000000'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['card_id' => 'Karta', 'amount_claimed' => 'Summa', 'paid_at' => "To'lov vaqti", 'receipt' => 'Chek']);

        $hash = hash_file('sha256', $request->file('receipt')->getRealPath());
        if (TopupRequest::withoutGlobalScopes()->where('receipt_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['receipt' => 'Bu chek avval yuklangan. Takroriy chekni qabul qilib bo\'lmaydi.']);
        }

        $path = $request->file('receipt')->store('topups/'.$center->id, 'local');

        $topup = TopupRequest::create([
            'center_id' => $center->id, 'card_id' => $data['card_id'], 'amount_claimed' => $data['amount_claimed'],
            'paid_at' => $data['paid_at'], 'receipt_path' => $path, 'receipt_hash' => $hash, 'note' => $data['note'] ?? null,
            'requested_by' => $request->user()->id,
        ]);

        $this->notifyTopup($center, $topup, $request->user());

        return redirect()->route('center.topup')->with('success', "So'rov yuborildi. Platforma administratori tekshirib tasdiqlagach balans to'ldiriladi.");
    }

    /** sAdminlarga Telegram: so'rov tafsiloti + chek fayli (yuborilmasa panel xabari baribir bor). */
    private function notifyTopup(\App\Models\Center $center, TopupRequest $topup, \App\Models\User $by): void
    {
        try {
            $tg = app(\App\Services\Telegram\TelegramNotifier::class);
            $card = \App\Models\PaymentCard::withoutGlobalScopes()->find($topup->card_id);
            $text = "💳 <b>Balans to'ldirish so'rovi</b>\n"
                .'Markaz: '.e($center->name)."\nSumma: <b>".number_format((int) $topup->amount_claimed, 0, '', ' ')." so'm</b>\n"
                .'To\'lov vaqti: '.\Illuminate\Support\Carbon::parse($topup->paid_at)->format('d.m.Y H:i')."\n"
                .($card ? 'Karta: …'.substr(preg_replace('/\D+/', '', (string) $card->number), -4)."\n" : '')
                .'Yuborgan: '.e($by->name).($topup->note ? "\nIzoh: ".e($topup->note) : '')
                ."\n\n".route('platform.topups');
            $tg->toSadmins($text);

            $full = Storage::disk('local')->path($topup->receipt_path);
            if (is_file($full)) {
                $tg->documentToSadmins($full, 'chek-'.$topup->id.'.'.pathinfo($full, PATHINFO_EXTENSION), 'Chek: '.$center->name);
            }
        } catch (\Throwable $e) {
            report($e);   // Telegram xatosi so'rovni buzmasin
        }
    }

    /** Chekni ko'rish: faqat shu markaz direktori (global scope) yoki sAdmin. */
    public function receipt(TopupRequest $topup)
    {
        abort_unless($topup->receipt_path && Storage::disk('local')->exists($topup->receipt_path), 404);

        return Storage::disk('local')->response($topup->receipt_path);
    }

    public function export(Request $request, string $type)
    {
        $center = $this->center($request);
        abort_unless(in_array($type, ['students', 'payments', 'groups'], true), 404);
        abort_unless($this->exportAllowed($center), 403);

        [$title, $headers, $rows] = match ($type) {
            'students' => ["O'quvchilar", ['ID', 'Ism', 'Telefon', 'Filial', 'Balans', 'Holat', "Qo'shilgan sana"],
                \App\Models\User::where('center_id', $center->id)->where('role', Role::Student)->orderBy('id')->get()
                    ->map(fn ($u) => [$u->id, $u->name, $u->phone, $u->branch?->name, (int) $u->balance, $u->status->value ?? (string) $u->status, optional($u->created_at)->format('d.m.Y')])->all()],
            'payments' => ["To'lovlar", ['ID', 'Sana', "O'quvchi", 'Turi', 'Usul', 'Summa', 'Izoh'],
                \App\Models\Payment::with('student:id,name')->orderBy('id')->get()
                    ->map(fn ($p) => [$p->id, optional($p->created_at)->format('d.m.Y H:i'), $p->student?->name, (string) ($p->type->value ?? $p->type), (string) ($p->method->value ?? $p->method), (int) $p->amount, $p->description])->all()],
            'groups' => ['Guruhlar', ['ID', 'Nomi', 'Kurs', "O'qituvchi", 'Narx', 'Boshlanish', 'Tugash'],
                \App\Models\Group::with(['course:id,name', 'teacher:id,name'])->orderBy('id')->get()
                    ->map(fn ($g) => [$g->id, $g->name, $g->course?->name, $g->teacher?->name, (int) $g->price, optional($g->starts_on)->format('d.m.Y'), optional($g->ends_on)->format('d.m.Y')])->all()],
        };

        $path = XlsxWriter::write($title, $headers, $rows, $title.' — '.$center->name);
        AuditLog::record('center.exported', null, "Markaz ma'lumoti eksport qilindi: {$title}");

        return response()->download($path, "{$type}-{$center->code}-".now()->format('Ymd').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function exportAllowed(Center $center): bool
    {
        return $center->status->isWritable() || $center->status === \App\Enums\CenterStatus::ReadOnly && PlatformSetting::value('allow_export_in_read_only', '1') === '1';
    }
}
