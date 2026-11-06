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

/** sAdmin: AI, SMS Gateway, Eskiz va Telegram bot sozlamalari — `.env` o'rniga shu sahifadan. */
class IntegrationController extends Controller
{
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
            'ai' => [
                'primary' => S::get('ai_primary') === 'anthropic' ? 'anthropic' : 'openai',
                'openai_mask' => S::mask('openai_key'), 'openai_env' => S::fromEnv('openai_key') && ! S::has('openai_key'), 'openai_model' => S::get('openai_model'),
                'anthropic_mask' => S::mask('anthropic_key'), 'anthropic_env' => S::fromEnv('anthropic_key') && ! S::has('anthropic_key'), 'anthropic_model' => S::get('anthropic_model'),
                'active' => app(\App\Services\OpenAiService::class)->providers(),
            ],
            'eskiz' => ['email' => S::get('eskiz_email'), 'mask' => S::mask('eskiz_password'), 'env' => S::fromEnv('eskiz_password') && ! S::has('eskiz_password'), 'from' => S::get('eskiz_from')],
            'gateway' => ['url' => S::get('gateway_url'), 'key_mask' => S::mask('gateway_key'), 'key_env' => S::fromEnv('gateway_key') && ! S::has('gateway_key'),
                'secret_mask' => S::mask('gateway_secret'), 'secret_env' => S::fromEnv('gateway_secret') && ! S::has('gateway_secret'), 'webhook' => route('sms-gateway.webhook')],
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

    public function saveAi(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'primary' => ['required', 'in:openai,anthropic'],
            'openai_key' => ['nullable', 'string', 'max:300', 'regex:/^sk-[A-Za-z0-9_\-]{20,}$/'],
            'openai_model' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'anthropic_key' => ['nullable', 'string', 'max:300', 'regex:/^sk-ant-[A-Za-z0-9_\-]{20,}$/'],
            'anthropic_model' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ], [
            'openai_key.regex' => "OpenAI kaliti «sk-...» bilan boshlanishi kerak.",
            'anthropic_key.regex' => "Claude kaliti «sk-ant-...» bilan boshlanishi kerak.",
        ], ['openai_key' => 'OpenAI kaliti', 'anthropic_key' => 'Claude kaliti', 'openai_model' => 'OpenAI modeli', 'anthropic_model' => 'Claude modeli']);

        S::set('ai_primary', $d['primary']);
        foreach (['openai', 'anthropic'] as $p) {
            if (filled($d[$p.'_key'] ?? null)) {
                S::set($p.'_key', trim($d[$p.'_key']));
            }
            S::set($p.'_model', filled($d[$p.'_model'] ?? null) ? trim($d[$p.'_model']) : null);
        }
        AuditLog::record('integration.ai_saved', null, 'AI sozlamalari saqlandi (asosiy: '.$d['primary'].')');

        return back()->with('success', 'AI sozlamalari saqlandi. «Sinab ko\'rish» tugmasi bilan ishlashini tekshiring.');
    }

    public function clearAiKey(Request $request): RedirectResponse
    {
        $p = $request->validate(['provider' => ['required', 'in:openai,anthropic']])['provider'];
        S::set($p.'_key', null);
        AuditLog::record('integration.ai_key_cleared', null, "AI kaliti sahifadan o'chirildi: ".$p);

        return back()->with('success', "Sahifada saqlangan kalit o'chirildi (.env dagisi bo'lsa, o'sha ishlatiladi).");
    }

    /** Har bir provayderni alohida, markaz hisobini sarflamasdan sinaydi. */
    public function testAi(Request $request): RedirectResponse
    {
        $ai = app(\App\Services\OpenAiService::class);
        $lines = [];
        foreach ($ai->providers() as $p) {
            $r = $ai->probe($p);
            $lines[] = (\App\Services\OpenAiService::PROVIDERS[$p]).': '.($r === true ? 'ishlayapti ✅' : 'xato ❌ — '.$r);
        }

        return back()->with($lines ? 'success' : 'error', $lines ? implode(' · ', $lines) : 'Hech qaysi kalit kiritilmagan.');
    }

    public function saveEskiz(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'email' => ['nullable', 'email', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
            'from' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9 _.-]{3,30}$/'],
        ], ['from.regex' => 'Yuboruvchi nomi 3–30 ta harf/raqamdan iborat bo\'lsin.'], ['email' => 'Eskiz email', 'password' => 'Eskiz paroli', 'from' => 'Yuboruvchi nomi']);

        $oldEmail = S::get('eskiz_email');
        $oldPass = S::get('eskiz_password');
        $oldFrom = S::get('eskiz_from');
        S::set('eskiz_email', $d['email'] ?? null);
        if (filled($d['password'] ?? null)) {
            S::set('eskiz_password', $d['password']);
        }
        S::set('eskiz_from', $d['from'] ?? null);

        if (filled(S::get('eskiz_email')) && filled(S::get('eskiz_password'))) {
            $client = \App\Services\Sms\EskizClient::platform();
            $check = $client?->check();
            if ($check !== true) {
                S::set('eskiz_email', $oldEmail);
                S::set('eskiz_password', $oldPass);
                S::set('eskiz_from', $oldFrom);
                throw ValidationException::withMessages(['password' => (string) $check]);
            }
        }
        AuditLog::record('integration.eskiz_saved', null, 'Platforma Eskiz hisobi saqlandi');

        return back()->with('success', "Platforma Eskiz hisobi saqlandi va tekshirildi. «SMS shablonlar» sahifasida matnlarni tasdiqlang.");
    }

    public function saveGateway(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'url' => ['required', 'url:https', 'max:190'],
            'key' => ['nullable', 'string', 'max:200', 'regex:/^sk_sms_[A-Za-z0-9_\-]{10,}$/'],
            'secret' => ['nullable', 'string', 'max:200'],
        ], ['url.url' => 'Manzil https:// bilan boshlanishi kerak.', 'key.regex' => "Gateway kaliti «sk_sms_...» ko'rinishida bo'lishi kerak."],
            ['url' => 'Gateway manzili', 'key' => 'API kaliti', 'secret' => 'Webhook siri']);

        S::set('gateway_url', rtrim($d['url'], '/'));
        if (filled($d['key'] ?? null)) {
            S::set('gateway_key', trim($d['key']));
        }
        if (filled($d['secret'] ?? null)) {
            S::set('gateway_secret', trim($d['secret']));
        }
        AuditLog::record('integration.gateway_saved', null, 'SMS Gateway sozlamalari saqlandi');

        return back()->with('success', "SMS Gateway sozlamalari saqlandi. «Tekshirish» tugmasi bilan ulanishni sinab ko'ring.");
    }

    public function testGateway(): RedirectResponse
    {
        $client = \App\Services\Sms\SmsGatewayClient::make();
        if (! $client) {
            return back()->with('error', "Gateway API kaliti kiritilmagan.");
        }
        $r = $client->check();

        return back()->with($r === true ? 'success' : 'error', $r === true ? "Gateway ishlayapti va kalit to'g'ri ✅" : $r);
    }
}
