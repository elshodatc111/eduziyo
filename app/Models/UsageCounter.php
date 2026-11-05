<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

class UsageCounter extends Model
{
    use BelongsToCenter;

    protected $fillable = ['center_id', 'kind', 'period_start', 'used', 'limit_snapshot'];

    protected function casts(): array
    {
        return ['period_start' => 'date:Y-m-d'];
    }
}
