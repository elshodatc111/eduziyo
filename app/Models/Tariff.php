<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tarif katalogi (sotuv sahifasi uchun). Obuna va limitlarni majburlash keyingi bosqichda shu jadvalga tayanadi. */
class Tariff extends Model
{
    protected $fillable = [
        'name', 'tagline', 'price_monthly', 'price_yearly', 'max_branches', 'max_students', 'sms_monthly', 'ai_monthly',
        'features', 'is_featured', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_featured' => 'boolean', 'is_active' => 'boolean'];

    /** @return list<string> */
    public function featureList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->features) ?: [])));
    }

    public function hasPrice(): bool
    {
        return $this->price_monthly !== null;
    }

    /** Yillik to'lovda oyiga necha so'm tushadi va 12 oylik narxga nisbatan necha so'm tejaladi. */
    public function yearlySaving(): ?int
    {
        if ($this->price_monthly === null || $this->price_yearly === null) {
            return null;
        }

        return max(0, $this->price_monthly * 12 - $this->price_yearly);
    }
}
