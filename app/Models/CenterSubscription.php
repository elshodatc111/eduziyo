<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenterSubscription extends Model
{
    use BelongsToCenter;

    protected $fillable = ['center_id', 'tariff_id', 'period', 'starts_at', 'ends_at', 'price_paid', 'source', 'snapshot', 'created_by', 'note', 'extra_sms'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'snapshot' => 'array', 'price_paid' => 'integer'];
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function name(): string
    {
        return (string) ($this->snapshot['name'] ?? '—');
    }
}
