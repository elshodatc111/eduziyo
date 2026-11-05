<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use BelongsToCenter;

    public const OPEN = 'open';
    public const PENDING_CUSTOMER = 'pending_customer';
    public const CLOSED = 'closed';

    protected $fillable = ['center_id', 'opened_by', 'subject', 'status', 'sla_due_at', 'first_response_at', 'sla_reminded_at', 'last_staff_at', 'last_customer_at',
        'closed_at', 'closed_reason', 'rating', 'rating_comment'];

    protected function casts(): array
    {
        return ['sla_due_at' => 'datetime', 'first_response_at' => 'datetime', 'sla_reminded_at' => 'datetime', 'last_staff_at' => 'datetime',
            'last_customer_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->withoutGlobalScopes()->with(['author', 'attachments'])->orderBy('id');
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function statusLabel(): string
    {
        return [self::OPEN => 'Javob kutilmoqda', self::PENDING_CUSTOMER => 'Mijoz javobi kutilmoqda', self::CLOSED => 'Yopilgan'][$this->status] ?? $this->status;
    }

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }
}
