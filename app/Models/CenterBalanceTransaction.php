<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

class CenterBalanceTransaction extends Model
{
    use BelongsToCenter;

    public const UPDATED_AT = null;

    public const TOPUP = 'topup';
    public const TARIFF_PURCHASE = 'tariff_purchase';
    public const TARIFF_REFUND = 'tariff_refund';
    public const ADJUSTMENT = 'adjustment';
    public const SMS_PACK = 'sms_pack';

    protected $fillable = ['center_id', 'type', 'amount', 'balance_after', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'created_at' => 'datetime'];
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TOPUP => "Balans to'ldirildi",
            self::TARIFF_PURCHASE => 'Tarif sotib olindi',
            self::TARIFF_REFUND => 'Eski tarif qoldig\'i qaytarildi',
            self::ADJUSTMENT => 'Tuzatish (sAdmin)',
            self::SMS_PACK => 'Qo\'shimcha SMS paketi',
            default => $this->type,
        };
    }
}
