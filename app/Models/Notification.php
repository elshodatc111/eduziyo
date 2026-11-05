<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** v11: sAdmin tomonidan yuborilgan push/ilova-ichi bildirishnoma. */
class Notification extends Model
{
    use BelongsToCenter;

    /** Platforma darajasidagi (markazsiz) bildirishnomalar ham bo'lishi mumkin. */
    protected bool $centerOptional = true;

    /** Markazsiz (platforma) bildirishnomalari hamma markaz foydalanuvchilariga ko'rinadi. */
    protected bool $centerNullVisible = true;

    protected $fillable = ['center_id', 'branch_id', 'title', 'body', 'data', 'sent_by', 'recipients_count'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }
}
