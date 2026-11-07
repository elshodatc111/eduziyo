<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CenterSmsAccount;
use App\Models\CenterSmsTemplateSync;
use App\Models\SmsPlatformTemplate;
use App\Services\Sms\EskizClient;
use App\Services\Sms\EskizTemplateSync;
use App\Services\Sms\SmsPlatformTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * sAdmin: umumiy SMS matnlarini tahrirlash va TASDIQLASH. Eskiz orqali faqat tasdiqlangan matnlar ketadi.
 * Tasdiqlangan matn platforma Eskiz hisobiga va barcha ulangan markazlarning Eskiz akkauntlariga moderatsiyaga yuboriladi.
 */
class SmsTemplateController extends Controller
{
    public function index()
    {
        SmsPlatformTemplates::ensureSeeded();
        $connected = CenterSmsAccount::withoutGlobalScopes()->where('status', 'connected')->count();
        $sync = CenterSmsTemplateSync::withoutGlobalScopes()->selectRaw('template_key, status, count(*) as c')->groupBy('template_key', 'status')->get()
            ->groupBy('template_key')->map(fn ($g) => $g->pluck('c', 'status')->all());

        return view('platform.sms.templates', [
            'templates' => SmsPlatformTemplate::orderBy('is_system')->orderBy('id')->get(),
            'platformEskiz' => EskizClient::platform() !== null,
            'connected' => $connected,
            'sync' => $sync,
            'placeholders' => SmsPlatformTemplates::allowedPlaceholders(),
        ]);
    }

    public function update(Request $request, string $key, EskizTemplateSync $sync): RedirectResponse
    {
        $tpl = SmsPlatformTemplate::where('key', $key)->firstOrFail();
        $d = $request->validate(['body' => ['required', 'string', 'min:10', 'max:320']], [], ['body' => 'Matn']);
        $body = trim(preg_replace('/\s+/u', ' ', $d['body']));

        if (str_contains($body, '{password}')) {
            throw ValidationException::withMessages(['body' => "SMS matnida parol bo'lmasligi kerak (parol SMS orqali yuborilmaydi)."]);
        }

        preg_match_all('/\{[a-z_]+\}/', $body, $m);
        $bad = array_values(array_diff(array_unique($m[0]), SmsPlatformTemplates::allowedPlaceholders()));
        if ($bad) {
            throw ValidationException::withMessages(['body' => "Noma'lum o'zgaruvchi: ".implode(', ', $bad)]);
        }

        if ($body !== $tpl->body) {
            // Matn hamma filial va markaz uchun bir xil; tasdiqlash bosqichi yo'q: saqlangan matn darhol Eskiz moderatsiyasiga ketadi.
            $tpl->update(['body' => $body, 'is_approved' => true, 'approved_at' => now(), 'approved_by' => $request->user()->id]);
            $sync->onChanged($tpl);
            $sync->onApproved($tpl->fresh());
            AuditLog::record('sms.template_edited', null, "SMS shablon matni o'zgartirildi: {$key}");

            return back()->with('success', "Matn saqlandi va Eskiz moderatsiyasiga yuborildi (platforma va ulangan markazlar).");
        }

        return back()->with('success', "O'zgarish yo'q.");
    }

    public function approve(Request $request, string $key, EskizTemplateSync $sync): RedirectResponse
    {
        $tpl = SmsPlatformTemplate::where('key', $key)->firstOrFail();
        $tpl->update(['is_approved' => true, 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        AuditLog::record('sms.template_approved', null, "SMS shablon tasdiqlandi: {$key}");

        $sync->onApproved($tpl->fresh());

        return back()->with('success', "Matn Eskiz moderatsiyasiga qayta yuborildi (platforma va ulangan markazlar).");
    }

    public function revoke(string $key, EskizTemplateSync $sync): RedirectResponse
    {
        $tpl = SmsPlatformTemplate::where('key', $key)->firstOrFail();
        $tpl->update(['is_approved' => false, 'approved_at' => null, 'approved_by' => null]);
        $sync->onChanged($tpl);
        AuditLog::record('sms.template_revoked', null, "SMS shablon tasdiqi qaytarib olindi: {$key}");

        return back()->with('success', "Tasdiq qaytarib olindi: bu matn endi Eskiz orqali yuborilmaydi.");
    }

    public function refresh(EskizTemplateSync $sync): RedirectResponse
    {
        $sync->refreshAll();

        return back()->with('success', "Eskiz shablon holatlari yangilandi.");
    }
}
