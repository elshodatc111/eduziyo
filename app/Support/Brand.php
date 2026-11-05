<?php

namespace App\Support;

use App\Models\Center;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Platforma brendi: nom, logotip, favicon (sAdmin sozlaydi) va markaz logotipi (direktor sozlaydi).
 * Sozlama bo'lmasa — standart «EDUZIYO» va harfli belgi. Bazaga ulanib bo'lmasa ham standartga qaytadi.
 */
class Brand
{
    public const DEFAULT_NAME = 'EDUZIYO';

    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $v = Schema::hasTable('platform_settings') ? PlatformSetting::many(['brand_name', 'brand_logo', 'brand_favicon']) : [];
        } catch (Throwable) {
            $v = [];
        }

        return self::$cache = ['name' => $v['brand_name'] ?? self::DEFAULT_NAME, 'logo' => $v['brand_logo'] ?? null, 'favicon' => $v['brand_favicon'] ?? null];
    }

    public static function name(): string
    {
        return self::all()['name'];
    }

    public static function initial(): string
    {
        return mb_strtoupper(mb_substr(self::name(), 0, 1));
    }

    public static function logoUrl(): ?string
    {
        return ($p = self::all()['logo']) ? asset('uploads/'.$p) : null;
    }

    public static function faviconUrl(): ?string
    {
        $p = self::all()['favicon'] ?? self::all()['logo'];

        return $p ? asset('uploads/'.$p) : null;
    }

    public static function centerLogoUrl(?Center $center): ?string
    {
        return $center?->logo_path ? asset('uploads/'.$center->logo_path) : null;
    }
}
