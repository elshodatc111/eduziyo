<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChat extends Model
{
    use BelongsToCenter;

    protected $fillable = ['center_id', 'user_id', 'branch_id', 'title', 'kind'];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }
}
