<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    use BelongsToCenter;

    public const UPDATED_AT = null;

    protected $fillable = ['center_id', 'ai_chat_id', 'branch_id', 'user_id', 'role', 'content'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
