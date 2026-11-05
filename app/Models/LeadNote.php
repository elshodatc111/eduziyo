<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadNote extends Model
{
    use BelongsToCenter;

    public function centerIdFromParent(): ?int
    {
        return $this->lead_id ? (\Illuminate\Support\Facades\DB::table('leads')->where('id', $this->lead_id)->value('center_id') ?: null) : null;
    }

    public const UPDATED_AT = null;

    protected $fillable = ['center_id', 'lead_id', 'user_id', 'body', 'type'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
