<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/** Markazning o'z Eskiz akkaunti (har markazga bitta). Tarif SMS limiti tugagach SMS shu orqali ketadi. */
class CenterSmsAccount extends Model
{
    use BelongsToCenter;

    protected $fillable = ['center_id', 'email', 'password', 'sender', 'status', 'last_error', 'connected_at', 'checked_at'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'connected_at' => 'datetime', 'checked_at' => 'datetime'];
    }

    public function isUsable(): bool
    {
        return $this->status === 'connected';
    }

    public static function usableFor(int $centerId): ?self
    {
        return static::withoutGlobalScopes()->where('center_id', $centerId)->where('status', 'connected')->first();
    }
}
