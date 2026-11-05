<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Yangiliklar: faqat sAdmin yozadi, faqat sotuv sahifasida ko'rinadi. */
class NewsPost extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'is_published', 'published_at', 'created_by', 'image_path', 'youtube_id'];

    protected $casts = ['is_published' => 'boolean', 'published_at' => 'datetime'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->where('published_at', '<=', now());
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('uploads/'.$this->image_path) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function makeSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'yangilik';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
