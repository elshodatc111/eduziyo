<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use BelongsToCenter;

    /** sAdmin (markazsiz) yuklagan fayl markazsiz saqlanadi; direktor/xodim yuklasa - o'z markazi. */
    protected bool $centerOptional = true;

    public function centerIdFromParent(): ?int
    {
        return $this->user_id ? (\App\Models\User::whereKey($this->user_id)->value('center_id') ?: null) : null;
    }

    protected $fillable = ['center_id', 'user_id', 'status', 'filename', 'summary', 'rows', 'result_path'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'rows' => 'array'];
    }
}
