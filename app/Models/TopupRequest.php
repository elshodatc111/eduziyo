<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopupRequest extends Model
{
    use BelongsToCenter;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'center_id', 'card_id', 'amount_claimed', 'approved_amount', 'paid_at', 'receipt_path', 'receipt_hash', 'note',
        'status', 'requested_by', 'decided_by', 'decided_at', 'reject_reason',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'decided_at' => 'datetime', 'amount_claimed' => 'integer', 'approved_amount' => 'integer'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(PaymentCard::class, 'card_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function statusLabel(): string
    {
        return ['pending' => 'Kutilmoqda', 'approved' => 'Tasdiqlandi', 'rejected' => 'Rad etildi'][$this->status] ?? $this->status;
    }
}
