<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SmsModem;
use App\Models\SmsOutbox;
use App\Rules\UzPhone;
use App\Services\Sms\Modem\ModemScanner;
use App\Services\Sms\Modem\ModemSender;
use App\Services\Sms\Modem\ModemWorker;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** sAdmin: serverdagi USB modemlarni tekshirish, faollashtirish va sinash. */
class ModemController extends Controller
{
    public function index()
    {
        return view('platform.modems', [
            'modems' => SmsModem::orderBy('id')->get(),
            'worker' => ModemWorker::alive(),
            'queue' => SmsOutbox::whereIn('status', ['queued', 'claimed'])->count(),
            'report' => Cache::get(ModemScanner::REPORT_KEY),
        ]);
    }

    public function scan(ModemScanner $scanner): RedirectResponse
    {
        // Docker'da veb-konteynerda USB port ko'rinmaydi: ishchi ishlayotgan bo'lsa, qidiruvni o'sha bajaradi
        if (ModemWorker::alive()) {
            $stamp = (string) microtime(true);
            Cache::put(ModemWorker::SCAN_REQUEST_KEY, $stamp, 60);
            $r = null;
            for ($i = 0; $i < 40 && ! $r; $i++) {
                usleep(500_000);
                $done = Cache::get(ModemWorker::SCAN_DONE_KEY);
                if (is_array($done) && ($done['req'] ?? null) === $stamp) {
                    $r = ['found' => (int) $done['found']];
                }
            }
            if (! $r) {
                return back()->with('error', 'Modem ishchisi 20 soniyada javob bermadi. `docker logs eduziyo_modem` ni tekshiring.');
            }
        } else {
            $r = $scanner->scan();
        }

        return back()->with('success', $r['found'] > 0 ? "Topilgan modemlar: {$r['found']}." : "Modem topilmadi. Quyidagi diagnostika jadvaliga qarang.");
    }

    public function toggle(SmsModem $modem): RedirectResponse
    {
        $modem->update(['enabled' => ! $modem->enabled]);
        AuditLog::record('sms.modem_toggled', null, $modem->name().': '.($modem->enabled ? 'faollashtirildi' : "o'chirildi"));

        return back()->with('success', $modem->enabled ? 'Modem faollashtirildi.' : "Modem o'chirildi (SMS yuborilmaydi).");
    }

    public function update(Request $request, SmsModem $modem): RedirectResponse
    {
        $data = $request->validate(['label' => ['nullable', 'string', 'max:80']]);
        $modem->update(['label' => $data['label'] ?: null]);

        return back()->with('success', 'Nom saqlandi.');
    }

    public function reset(SmsModem $modem): RedirectResponse
    {
        $modem->update(['fail_streak' => 0, 'last_error' => null]);

        return back()->with('success', "Xato hisoblagichi tozalandi. Modem keyingi skanerlashda qayta tekshiriladi.");
    }

    /** Tanlangan modem orqali sinov SMS (navbat va limitlarsiz, darhol). */
    public function test(Request $request, SmsModem $modem, ModemSender $sender): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', new UzPhone]], [], ['phone' => 'Telefon']);
        abort_unless($modem->device, 422);

        $phone = Format::normalizePhone($data['phone']) ?? $data['phone'];
        $text = \App\Support\Brand::name().": modem sinovi ".now()->format('H:i');

        if (ModemWorker::alive()) {
            // Docker'da veb-konteynerda USB port yo'q: yuborishni modem ishchisi bajaradi, natijani kutamiz
            $token = bin2hex(random_bytes(8));
            Cache::put(ModemWorker::testKey($modem->id), ['token' => $token, 'phone' => $phone, 'text' => $text], 60);
            $r = null;
            for ($i = 0; $i < 60 && ! $r; $i++) {
                usleep(500_000);
                $r = Cache::pull(ModemWorker::testResultKey($token));
            }
            $r ??= ['ok' => false, 'error' => 'Modem ishchisi 30 soniyada javob bermadi'];
        } else {
            $r = $sender->send($modem, $phone, $text);
        }
        AuditLog::record('sms.modem_test', null, $modem->name().': '.($r['ok'] ? 'yuborildi' : 'xato: '.$r['error']));

        return $r['ok'] ? back()->with('success', 'Sinov SMS yuborildi.') : back()->with('error', 'Yuborilmadi: '.$r['error']);
    }
}
