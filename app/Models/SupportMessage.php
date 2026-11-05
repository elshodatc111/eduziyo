<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use BelongsToCenter;

    protected $fillable = ['ticket_id', 'center_id', 'user_id', 'is_staff', 'body'];

    protected function casts(): array
    {
        return ['is_staff' => 'boolean'];
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SupportAttachment::class, 'message_id')->withoutGlobalScopes();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
