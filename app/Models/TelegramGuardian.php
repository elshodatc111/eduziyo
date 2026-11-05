<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Telegram chat ↔ o'quvchi aloqasi: ota-ona (`parent`) yoki o'quvchining o'zi (`student`). */
class TelegramGuardian extends Model
{
    use BelongsToCenter;

    public const PARENT = 'parent';
    public const STUDENT = 'student';

    protected $fillable = ['center_id', 'branch_id', 'student_id', 'chat_id', 'kind', 'phone', 'tg_username', 'notify_absence', 'notify_debt', 'notify_payment', 'linked_at'];

    protected function casts(): array
    {
        return ['notify_absence' => 'boolean', 'notify_debt' => 'boolean', 'notify_payment' => 'boolean', 'linked_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
