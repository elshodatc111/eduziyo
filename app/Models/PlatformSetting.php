<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platforma sozlamalari (kalit-qiymat): sotuv sahifasi aloqa ma'lumotlari va h.k. Faqat sAdmin o'zgartiradi. */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function value(string $key, ?string $default = null): ?string
    {
        $v = static::query()->whereKey($key)->value('value');

        return filled($v) ? $v : $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** @return array<string, ?string> */
    public static function many(array $keys): array
    {
        $rows = static::query()->whereIn('key', $keys)->pluck('value', 'key');

        return collect($keys)->mapWithKeys(fn ($k) => [$k => filled($rows[$k] ?? null) ? $rows[$k] : null])->all();
    }
}
