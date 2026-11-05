<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Tashqi xizmat sozlamalari (Telegram bot, USB modem) — `.env` o'rniga sAdmin sahifasidan boshqariladi.
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
        'modem_ports' => [null, false, '/dev/ttyUSB*,/dev/ttyACM*'],
        'modem_baud' => [null, false, '115200'],
        'modem_pause_min' => [null, false, '3'],
        'modem_pause_max' => [null, false, '8'],
        'modem_daily_limit' => [null, false, '0'],
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

    /** @return list<string> */
    public static function modemPorts(): array
    {
        $globs = array_filter(array_map('trim', explode(',', (string) self::get('modem_ports'))));
        $found = [];
        foreach ($globs as $g) {
            // faqat /dev/ ichidagi qurilmalar (boshqa yo'l kiritilsa e'tiborga olinmaydi)
            if (str_starts_with($g, '/dev/') && ! str_contains($g, '..')) {
                $found = array_merge($found, glob($g) ?: []);
            }
        }

        return array_values(array_unique($found));
    }
}
