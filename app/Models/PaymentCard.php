<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platforma to'lov kartasi (markaz balansini to'ldirish uchun). Platforma darajasida - markazga bog'liq emas. */
class PaymentCard extends Model
{
    protected $fillable = ['number', 'holder', 'bank', 'note', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
