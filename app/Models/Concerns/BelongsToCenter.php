<?php

namespace App\Models\Concerns;

use App\Models\Center;
use App\Support\CenterContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Markazga tegishli modellar uchun: avtomatik markaz filtri va `center_id` ni to'ldirish.
 *
 * Markaz ID si filialdan (`branch_id`), ota yozuvdan (`centerIdFromParent()`) yoki joriy kontekstdan olinadi.
 * Hech biri aniqlanmasa - LogicException (jim noto'g'ri markazga yozilmaydi). Faqat model
 * `protected bool $centerOptional = true` desa (platforma darajasidagi jurnal/bildirishnoma) markazsiz yoziladi.
 *
 * Diqqat: `Model::insert()`, `DB::table()` va `upsert()` model hodisalarini aylanib o'tadi - ularda
 * `center_id` qo'lda berilishi shart (`php artisan tenancy:audit` shunday qatorlarni topadi).
 */
trait BelongsToCenter
{
    public static function bootBelongsToCenter(): void
    {
        static::addGlobalScope(new CenterScope);

        static::creating(function ($model) {
            if (empty($model->center_id)) {
                $model->center_id = CenterContext::resolveFor($model, $model->isCenterOptional());
            }
        });
    }

    /** Markazsiz yozish ruxsat etiladimi (platforma darajasidagi jurnal/bildirishnoma). */
    public function isCenterOptional(): bool
    {
        return (bool) ($this->centerOptional ?? false);
    }

    /** center_id = NULL qatorlar markaz foydalanuvchilariga ko'rinadimi. */
    public function isCenterNullVisible(): bool
    {
        return (bool) ($this->centerNullVisible ?? false);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}
