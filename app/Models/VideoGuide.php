<?php

namespace App\Models;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Model;

/**
 * Ilova ichidagi rol bo'yicha video qo'llanma. Platforma darajasida; havolani faqat sAdmin kiritadi.
 * Faqat YouTube havolasi qabul qilinadi (ilova ichida youtube-nocookie embed). Reklamasiz va boshqa videolarsiz
 * ko'rsatishga YouTube to'liq kafolat bermaydi — kerak bo'lsa keyin o'z serverga o'tkaziladi.
 */
class VideoGuide extends Model
{
    protected $fillable = ['title', 'url', 'roles', 'is_active', 'sort_order', 'cover_path'];

    protected function casts(): array
    {
        return ['roles' => 'array', 'is_active' => 'boolean'];
    }

    public function scopeForRole($q, string $role)
    {
        return $q->where('is_active', true)->whereJsonContains('roles', $role)->orderBy('sort_order')->orderBy('id');
    }

    /** Muqova: sAdmin yuklagan rasm, bo'lmasa YouTube'ning o'z kichik rasmi. */
    public function coverUrl(): ?string
    {
        if ($this->cover_path) {
            return asset('uploads/'.$this->cover_path);
        }

        return ($id = $this->youtubeId()) ? "https://i.ytimg.com/vi/{$id}/hqdefault.jpg" : null;
    }

    public function youtubeId(): ?string
    {
        return YouTube::id($this->url);
    }

    public function embedUrl(): ?string
    {
        return ($id = $this->youtubeId()) ? YouTube::embedUrl($id).'&playsinline=1&iv_load_policy=3' : null;
    }
}
