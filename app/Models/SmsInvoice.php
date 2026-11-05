<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

class SmsInvoice extends Model
{
    use BelongsToCenter;

    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    protected $fillable = ['center_id', 'sms_count', 'amount', 'status', 'note', 'created_by', 'decided_by', 'expires_at', 'decided_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'decided_at' => 'datetime', 'sms_count' => 'integer', 'amount' => 'integer'];
    }

    public function statusLabel(): string
    {
        return [self::PENDING => 'Kutilmoqda', self::ACCEPTED => 'Qabul qilindi', self::CANCELLED => 'Bekor qilindi', self::EXPIRED => "Muddati o'tdi (3 kun)"][$this->status] ?? $this->status;
    }
}
