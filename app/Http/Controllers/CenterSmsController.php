<?php

namespace App\Http\Controllers;

use App\Jobs\SyncEskizTemplatesJob;
use App\Models\AuditLog;
use App\Models\Center;
use App\Models\CenterSmsAccount;
use App\Models\CenterSmsTemplateSync;
use App\Models\SmsMessage;
use App\Models\SmsPlatformTemplate;
use App\Services\Sms\EskizClient;
use App\Services\Sms\EskizTemplateSync;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use App\Support\Format;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Direktor: markazning SMS jurnali, tarif limiti holati va markazning O'Z Eskiz akkauntini ulash.
 * Tarif SMS limiti tugagach xabarlar shu akkaunt orqali ketadi (faqat sAdmin tasdiqlagan matnlar).
 */
class CenterSmsController extends Controller
{
    public function __construct(private UsageService $usage, private SubscriptionService $subs) {}

    public function index(Request $request)
    {
        $center = Center::findOrFail($request->user()->center_id);
        $limit = $this->subs->limits($center)['sms_monthly'] ?? null;
        $used = $this->usage->used($center, 'sms');

        $messages = SmsMessage::withoutGlobalScopes()->where('center_id', $center->id)
            ->when(SafeInput::string($request->input('status')), fn ($q, $v) => $q->where('status', $v))
            ->when(SafeInput::string($request->input('q')), fn ($q, $t) => $q->where('phone', 'like', '%'.Format::digits($t).'%'))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        $account = CenterSmsAccount::withoutGlobalScopes()->where('center_id', $center->id)->first();

        return view('center.sms', [
            'center' => $center,
            'messages' => $messages,
            'limit' => $limit,
            'used' => $used,
            'account' => $account,
            'exhausted' => $limit !== null && $used >= $limit,
            'nearLimit' => $limit !== null && $limit > 0 && $used >= (int) ceil($limit * 0.8),
            'templates' => SmsPlatformTemplate::where('is_approved', true)->orderBy('is_system')->orderBy('id')->get(),
            'sync' => CenterSmsTemplateSync::withoutGlobalScopes()->where('center_id', $center->id)->get()->keyBy('template_key'),
        ]);
    }

    public function connect(Request $request, EskizTemplateSync $sync): RedirectResponse
    {
        $center = Center::findOrFail($request->user()->center_id);
        $existing = CenterSmsAccount::withoutGlobalScopes()->where('center_id', $center->id)->first();

        $d = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => [$existing ? 'nullable' : 'required', 'string', 'max:190'],
            'sender' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9 _.-]{3,30}$/'],
        ], ['sender.regex' => "Yuboruvchi nomi 3–30 ta harf/raqamdan iborat bo'lsin (Eskiz bergan nom, masalan 4546)."],
            ['email' => 'Eskiz email', 'password' => 'Eskiz paroli', 'sender' => 'Yuboruvchi nomi']);

        $password = filled($d['password'] ?? null) ? $d['password'] : $existing?->password;

        // Kiritilgan ma'lumot Eskiz'da tekshiriladi va faqat to'g'ri bo'lsa saqlanadi
        $check = (new EskizClient($d['email'], (string) $password, $d['sender']))->check();
        if ($check !== true) {
            throw ValidationException::withMessages(['password' => $check]);
        }

        CenterSmsAccount::withoutGlobalScopes()->updateOrCreate(['center_id' => $center->id], [
            'email' => $d['email'], 'password' => $password, 'sender' => $d['sender'],
            'status' => 'connected', 'last_error' => null, 'connected_at' => $existing?->connected_at ?? now(), 'checked_at' => now(),
        ]);
        AuditLog::record('sms.eskiz_connected', null, "Markaz Eskiz akkaunti ulandi: {$center->name}");

        SyncEskizTemplatesJob::dispatch($center->id);

        return back()->with('success', "Eskiz ulandi. Tasdiqlangan SMS matnlari Eskiz'ga moderatsiyaga yuborilmoqda.");
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $centerId = $request->user()->center_id;
        CenterSmsAccount::withoutGlobalScopes()->where('center_id', $centerId)->delete();
        CenterSmsTemplateSync::withoutGlobalScopes()->where('center_id', $centerId)->delete();
        AuditLog::record('sms.eskiz_disconnected', null, "Markaz Eskiz akkaunti uzildi (markaz #{$centerId})");

        return back()->with('success', "Eskiz akkaunti uzildi. Tarif limiti tugagach SMS yuborilmaydi.");
    }

    public function resync(Request $request, EskizTemplateSync $sync): RedirectResponse
    {
        $acc = CenterSmsAccount::withoutGlobalScopes()->where('center_id', $request->user()->center_id)->first();
        if (! $acc) {
            return back()->with('error', 'Avval Eskiz akkauntini ulang.');
        }

        $sync->refreshCenter($acc);
        if ($acc->isUsable()) {
            $sync->syncCenter($acc);
        }

        return back()->with($acc->isUsable() ? 'success' : 'error', $acc->isUsable() ? 'Shablon holatlari yangilandi.' : 'Eskiz akkauntiga kirib bo\'lmadi: '.$acc->last_error);
    }
}
