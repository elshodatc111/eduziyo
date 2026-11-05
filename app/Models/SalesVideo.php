<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sotuv sahifasidagi YouTube videolar (faqat video ID saqlanadi). */
class SalesVideo extends Model
{
    protected $fillable = ['title', 'youtube_id', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];
}
