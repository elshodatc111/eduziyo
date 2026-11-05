<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ommaviy demo'ga rol bo'yicha kirish (platforma darajasida, markazga bog'liq emas). */
class DemoVisit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['role', 'ip_hash', 'user_agent'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
