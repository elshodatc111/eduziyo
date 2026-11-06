<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Tashqi xizmat sozlamalari (Telegram bot, AI, SMS Gateway, Eskiz) — `.env` o'rniga sAdmin sahifasidan boshqariladi.
 * Sahifada saqlangan qiymat `.env` dagisidan ustun turadi; saqlanmagan bo'lsa `.env`/standart qiymat ishlatiladi.
 * Sirlar (token, webhook kaliti) bazada shifrlangan (APP_KEY bilan) saqlanadi va sahifada hech qachon to'liq ko'rsatilmaydi.
 */
class IntegrationSettings
{
    /** key => [env/config manbasi, sir?, standart] */
    public const DEFINITIONS = [
        'telegram_token' => ['services.telegram.token', true, null],
        'telegram_username' => ['services.telegram.username', false, null],
        'telegram_webhook_secret' => ['services.telegram.webhook_secret', true, null],
        'telegram_webhook_url' => [null, false, null],
        'telegram_alert_chats' => [null, false, null],
        'openai_key' => ['services.openai.key', true, null],
        'openai_model' => ['services.openai.model', false, 'gpt-4.1-mini'],
        'anthropic_key' => ['services.anthropic.key', true, null],
        'anthropic_model' => ['services.anthropic.model', false, 'claude-sonnet-4-5'],
        'ai_primary' => [null, false, 'openai'],
        'gateway_url' => ['services.sms_gateway.url', false, 'https://sms.elshod.date'],
        'gateway_key' => ['services.sms_gateway.key', true, null],
        'gateway_secret' => ['services.sms_gateway.webhook_secret', true, null],
        'eskiz_email' => ['services.eskiz.email', false, null],
        'eskiz_password' => ['services.eskiz.password', true, null],
        'eskiz_from' => ['services.eskiz.from', false, '4546'],
    ];

    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function stored(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = PlatformSetting::query()->whereIn('key', array_map(fn ($k) => 'int_'.$k, array_keys(self::DEFINITIONS)))->pluck('value', 'key')->all();
            } catch (\Throwable) {
                self::$cache = [];   // jadval hali yo'q (migratsiya paytida)
            }
        }

        return self::$cache;
    }

    public static function get(string $key): ?string
    {
        [$config, $secret, $default] = self::DEFINITIONS[$key] ?? [null, false, null];
        $raw = self::stored()['int_'.$key] ?? null;

        if (filled($raw)) {
            if ($secret) {
                try {
                    return Crypt::decryptString($raw);
                } catch (DecryptException) {
                    return null;   // APP_KEY o'zgargan: qiymatni qayta kiritish kerak
                }
            }

            return $raw;
        }

        $fallback = $config ? config($config) : null;

        return filled($fallback) ? (string) $fallback : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        return is_numeric($v = self::get($key)) ? (int) $v : $default;
    }

    /** Sahifada saqlangan qiymat bormi (sir uchun «saqlangan» belgisi). */
    public static function has(string $key): bool
    {
        return filled(self::stored()['int_'.$key] ?? null);
    }

    /** `.env` da qiymat bormi (sahifada ko'rsatish uchun). */
    public static function fromEnv(string $key): bool
    {
        $config = self::DEFINITIONS[$key][0] ?? null;

        return $config !== null && filled(config($config));
    }

    public static function set(string $key, ?string $value): void
    {
        abort_unless(isset(self::DEFINITIONS[$key]), 500);
        $secret = self::DEFINITIONS[$key][1];
        $stored = filled($value) ? ($secret ? Crypt::encryptString($value) : $value) : null;

        PlatformSetting::put('int_'.$key, $stored);
        self::flush();
    }

    /** Sirning oxirgi 4 belgisi (tanish bo'lish uchun). */
    public static function mask(string $key): ?string
    {
        $v = self::get($key);

        return $v ? '••••'.mb_substr($v, -4) : null;
    }

}
