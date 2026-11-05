<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Telegram\TelegramClient;
use App\Support\IntegrationSettings as S;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** sAdmin: Telegram bot (token, nom, webhook) va USB modem parametrlari — `.env` o'rniga shu sahifadan. */
class IntegrationController extends Controller
{
    public const BAUDS = [9600, 19200, 38400, 57600, 115200, 230400, 460800];

    public function index(Request $request, TelegramClient $tg)
    {
        $status = null;
        if ($request->boolean('tekshirish') && $tg->configured()) {
            $status = ['me' => $tg->me(), 'hook' => $tg->webhookInfo()];
        }

        return view('platform.integrations', [
            'status' => $status,
            'tokenMask' => S::mask('telegram_token'), 'tokenFromEnv' => S::fromEnv('telegram_token') && ! S::has('telegram_token'),
            'secretMask' => S::mask('telegram_webhook_secret'), 'secretFromEnv' => S::fromEnv('telegram_webhook_secret') && ! S::has('telegram_webhook_secret'),
            'username' => S::get('telegram_username'),
            'alertChats' => S::get('telegram_alert_chats'),
            'alertTargets' => count(app(\App\Services\Telegram\TelegramNotifier::class)->sadminChats()),
            'webhookUrl' => S::get('telegram_webhook_url') ?: rtrim((string) config('app.url'), '/').'/api/telegram/webhook',
            'modem' => ['ports' => S::get('modem_ports'), 'baud' => S::int('modem_baud', 115200), 'pause_min' => S::int('modem_pause_min', 3),
                'pause_max' => S::int('modem_pause_max', 8), 'daily_limit' => S::int('modem_daily_limit', 0)],
            'bauds' => self::BAUDS,
            'detected' => S::modemPorts(),
        ]);
    }

    public function saveTelegram(Request $request, TelegramClient $tg): RedirectResponse
    {
        $d = $request->validate([
            'token' => ['nullable', 'string', 'max:200', 'regex:/^\d{5,}:[A-Za-z0-9_-]{20,}$/'],
            'username' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9_]{4,32}$/'],
            'webhook_url' => ['nullable', 'url', 'max:255', 'starts_with:https://'],
            'new_secret' => ['nullable', 'boolean'],
            'alert_chats' => ['nullable', 'string', 'max:300', function ($a, $v, $fail) {
                foreach (array_filter(array_map('trim', explode(',', (string) $v))) as $c) {
                    if (! preg_match('/^-?\d{5,}$/', $c)) {
                        $fail("Chat ID noto'g'ri: {$c}. Faqat raqamlar, vergul bilan ajrating (guruh ID si minus bilan boshlanadi).");
                    }
                }
            }],
        ], [
            'token.regex' => "Token formati noto'g'ri (masalan 123456:ABC...). @BotFather bergan to'liq tokenni kiriting.",
            'webhook_url.starts_with' => 'Webhook manzili https:// bilan boshlanishi kerak (Telegram talabi).',
        ], ['token' => 'Bot tokeni', 'username' => 'Bot nomi', 'webhook_url' => 'Webhook manzili', 'alert_chats' => 'Ogohlantirish chatlari']);

        if (filled($d['token'] ?? null)) {
            // Yangi token Telegram'da tekshiriladi va faqat to'g'ri bo'lsa saqlanadi
            $previous = S::get('telegram_token');
            S::set('telegram_token', $d['token']);
            $me = $tg->me();
            if (! $me['ok']) {
                S::set('telegram_token', $previous);
                throw ValidationException::withMessages(['token' => 'Token Telegram tomonidan qabul qilinmadi: '.($me['error'] ?? '')]);
            }
            if (blank($d['username'] ?? null) && ! empty($me['username'])) {
                $d['username'] = $me['username'];
            }
        }

        if (array_key_exists('username', $d)) {
            S::set('telegram_username', filled($d['username']) ? ltrim($d['username'], '@') : null);
        }
        S::set('telegram_webhook_url', $d['webhook_url'] ?? null);
        S::set('telegram_alert_chats', filled($d['alert_chats'] ?? null) ? preg_replace('/\s+/', '', $d['alert_chats']) : null);

        if ($request->boolean('new_secret') || (blank(S::get('telegram_webhook_secret')) && S::get('telegram_token'))) {
            S::set('telegram_webhook_secret', Str::random(48));
        }

        AuditLog::record('integration.telegram_saved', null, 'Telegram bot sozlamalari saqlandi');

        return back()->with('success', 'Telegram sozlamalari saqlandi. Endi «Webhook o\'rnatish» tugmasini bosing.');
    }

    public function clearTelegram(): RedirectResponse
    {
        foreach (['telegram_token', 'telegram_webhook_secret'] as $k) {
            S::set($k, null);
        }
        AuditLog::record('integration.telegram_cleared', null, "Telegram token va webhook kaliti sahifadan o'chirildi");

        return back()->with('success', "Sahifada saqlangan token va kalit o'chirildi (.env dagisi bo'lsa, o'sha ishlatiladi).");
    }

    public function setWebhook(TelegramClient $tg): RedirectResponse
    {
        if (! $tg->configured()) {
            return back()->with('error', 'Avval bot tokenini kiriting.');
        }
        if (blank(S::get('telegram_webhook_secret'))) {
            S::set('telegram_webhook_secret', Str::random(48));
        }

        $url = S::get('telegram_webhook_url') ?: rtrim((string) config('app.url'), '/').'/api/telegram/webhook';
        if (! str_starts_with($url, 'https://')) {
            return back()->with('error', "Webhook manzili https:// bilan boshlanishi kerak. Domen va HTTPS sozlangach qayta urinib ko'ring (hozirgi: {$url}).");
        }

        $ok = $tg->setWebhook($url, (string) S::get('telegram_webhook_secret'));
        AuditLog::record('integration.webhook_set', null, 'Telegram webhook: '.($ok ? "o'rnatildi" : 'xato'));

        return $ok
            ? redirect()->route('platform.integrations', ['tekshirish' => 1])->with('success', "Webhook o'rnatildi: {$url}")
            : back()->with('error', "Webhook o'rnatilmadi. Token, manzil va serverning internetga chiqishini tekshiring.");
    }

    public function removeWebhook(TelegramClient $tg): RedirectResponse
    {
        $ok = $tg->deleteWebhook();
        AuditLog::record('integration.webhook_removed', null, 'Telegram webhook olib tashlandi: '.($ok ? 'ok' : 'xato'));

        return back()->with($ok ? 'success' : 'error', $ok ? 'Webhook olib tashlandi (bot xabar olmaydi).' : "Olib tashlab bo'lmadi.");
    }

    public function saveModem(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'ports' => ['required', 'string', 'max:200', function ($attr, $value, $fail) {
                foreach (array_filter(array_map('trim', explode(',', $value))) as $g) {
                    if (! preg_match('~^/dev/[A-Za-z0-9_*?\[\]-]+$~', $g)) {
                        $fail("Port yo'li noto'g'ri: {$g}. Faqat /dev/ichidagi qurilma (masalan /dev/ttyUSB*) ruxsat etiladi.");
                    }
                }
            }],
            'baud' => ['required', 'integer', 'in:'.implode(',', self::BAUDS)],
            'pause_min' => ['required', 'integer', 'between:0,120'],
            'pause_max' => ['required', 'integer', 'between:0,300', 'gte:pause_min'],
            'daily_limit' => ['required', 'integer', 'between:0,100000'],
        ], ['pause_max.gte' => "Eng uzun pauza eng qisqasidan kam bo'lmasin."], [
            'ports' => 'Port', 'baud' => 'Tezlik', 'pause_min' => 'Eng qisqa pauza', 'pause_max' => 'Eng uzun pauza', 'daily_limit' => 'Kunlik chegara',
        ]);

        S::set('modem_ports', trim($d['ports']));
        S::set('modem_baud', (string) $d['baud']);
        S::set('modem_pause_min', (string) $d['pause_min']);
        S::set('modem_pause_max', (string) $d['pause_max']);
        S::set('modem_daily_limit', (string) $d['daily_limit']);
        AuditLog::record('integration.modem_saved', null, 'Modem sozlamalari saqlandi');

        return back()->with('success', "Modem sozlamalari saqlandi. Ishchi (sms:modem-run) keyingi aylanishda qo'llaydi.");
    }
}
