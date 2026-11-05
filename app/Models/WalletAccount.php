<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

class WalletAccount extends Model
{
    use BelongsToCenter;

    protected $table = 'wallets';

    protected $fillable = ['center_id', 'branch_id', 'code', 'balance'];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }
}
