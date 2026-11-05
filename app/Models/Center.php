<?php

namespace App\Models;

use App\Enums\CenterStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Markaz - mijoz tashkiloti (EDUZIYO'da Platforma → Markaz → Filial).
 * Diqqat: bu modelga global scope qo'yilmagan - markazlar ro'yxatini faqat sAdmin ko'radi
 * (marshrutlar `role:sadmin` bilan himoyalangan), direktor esa o'z markazini `$user->center` orqali oladi.
 */
class Center extends Model
{
    protected $fillable = [
        'name', 'code', 'status', 'owner_user_id', 'owner_name', 'owner_phone',
        'tariff_ends_at', 'read_only_since', 'delete_after', 'reminders_sent',
        'is_demo', 'notes', 'created_by',
    ];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'status' => CenterStatus::class,
            'tariff_ends_at' => 'datetime',
            'read_only_since' => 'datetime',
            'delete_after' => 'datetime',
            'reminders_sent' => 'array',
            'is_demo' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === CenterStatus::Active;
    }

    /** Markaz nomidan takrorlanmaydigan kod (slug). */
    public static function makeCode(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'markaz';
        $code = $base;
        $i = 2;

        while (static::where('code', $code)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $code = $base.'-'.$i++;
        }

        return $code;
    }
}
