<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** sAdmin tomonidan tasdiqlanadigan umumiy SMS matnlari (Eskiz faqat shularni yuboradi). Platforma darajasida, markazga bog'lanmagan. */
class SmsPlatformTemplate extends Model
{
    protected $fillable = ['key', 'label', 'body', 'is_system', 'is_approved', 'approved_at', 'approved_by', 'eskiz_status', 'eskiz_template_id', 'eskiz_error'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_approved' => 'boolean', 'approved_at' => 'datetime'];
    }
}
