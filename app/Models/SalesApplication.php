<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sotuv sahifasidan kelgan ariza (platforma darajasida; faqat sAdmin ko'radi). */
class SalesApplication extends Model
{
    public const STATUSES = [
        'new' => 'Yangi',
        'contacted' => "Bog'lanildi",
        'demo' => 'Demo ko\'rsatildi',
        'offer' => 'Taklif yuborildi',
        'won' => 'Mijoz bo\'ldi',
        'lost' => 'Rad etildi',
    ];

    protected $fillable = [
        'name', 'center_name', 'phone', 'city', 'branches_count', 'students_count', 'message', 'status',
        'phone_verified_at', 'telegram_chat_id', 'utm_source', 'utm_medium', 'utm_campaign', 'follow_up_at',
        'internal_note', 'ip', 'opened_center_id', 'wants_demo', 'tariff_slug',
    ];

    protected $casts = ['phone_verified_at' => 'datetime', 'follow_up_at' => 'date', 'wants_demo' => 'boolean'];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isOverdue(): bool
    {
        return $this->follow_up_at !== null && $this->follow_up_at->isPast() && ! in_array($this->status, ['won', 'lost'], true);
    }
}
