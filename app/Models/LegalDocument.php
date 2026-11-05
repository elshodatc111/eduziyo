<?php

namespace App\Models;

use App\Support\Brand;
use App\Support\MarkdownRenderer;
use Illuminate\Database\Eloquent\Model;

/** Huquqiy hujjat versiyasi. E'lon qilingach o'zgarmaydi — o'zgartirish yangi versiya yaratadi. Platforma darajasida. */
class LegalDocument extends Model
{
    public const TYPES = ['offer' => 'Ommaviy oferta', 'privacy' => 'Maxfiylik siyosati', 'terms' => 'Foydalanish shartlari'];

    public const AUDIENCES = ['all' => 'Hamma', 'owner' => 'Direktorlar', 'staff' => 'Xodimlar', 'student' => "O'quvchilar"];

    /** Ommaviy sahifa yo'llari. */
    public const SLUGS = ['offer' => 'oferta', 'privacy' => 'maxfiylik', 'terms' => 'shartlar'];

    protected $fillable = ['type', 'audience', 'version', 'title', 'body', 'status', 'requires_reacceptance', 'effective_at', 'published_by', 'published_at'];

    protected $casts = ['requires_reacceptance' => 'boolean', 'effective_at' => 'datetime', 'published_at' => 'datetime'];

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    public function html(): string
    {
        return MarkdownRenderer::toHtml(str_replace('{platforma}', Brand::name(), $this->body));
    }

    /** Hozir kuchda: e'lon qilingan va kuchga kirgan. */
    public function scopeInEffect($q)
    {
        return $q->where('status', 'published')->where(fn ($w) => $w->whereNull('effective_at')->orWhere('effective_at', '<=', now()));
    }
}
