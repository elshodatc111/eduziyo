<?php

namespace App\Enums;

/** Markaz (mijoz tashkiloti) holati. TZ v1.0, 8.3-bo'lim. */
enum CenterStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Tarif kutilmoqda',
            self::Active => 'Faol',
            self::ReadOnly => "Faqat ko'rish",
            self::Suspended => "To'xtatilgan",
            self::Deleted => "O'chirilgan",
        };
    }

    /** Markaz foydalanuvchilari tizimga kira oladimi. */
    public function canLogin(): bool
    {
        return in_array($this, [self::Pending, self::Active, self::ReadOnly], true);
    }

    /** Yozish amallari (davomat, to'lov, o'quvchi qo'shish...) ruxsat etiladimi. */
    public function isWritable(): bool
    {
        return $this === self::Active;
    }
}
