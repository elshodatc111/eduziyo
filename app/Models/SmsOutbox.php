<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** USB modem (gateway) navbati. Platforma darajasida; faqat servis va gateway API ishlatadi. */
class SmsOutbox extends Model
{
    protected $table = 'sms_outbox';

    protected $fillable = ['sms_id', 'status', 'attempts', 'claimed_at', 'reported_at', 'error'];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime', 'reported_at' => 'datetime'];
    }

    public function sms(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class, 'sms_id')->withoutGlobalScopes();
    }
}
