<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/** Markaz balansi (faqat direktor va sAdmin ko'radi). O'zgarishi faqat CenterBalanceService orqali. */
class CenterWallet extends Model
{
    use BelongsToCenter;

    protected $fillable = ['center_id', 'balance'];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }
}
