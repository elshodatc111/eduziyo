<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/** Tasdiqlangan umumiy matnning markaz Eskiz akkauntidagi tasdiqlanish holati. */
class CenterSmsTemplateSync extends Model
{
    use BelongsToCenter;

    protected $table = 'center_sms_template_sync';

    protected $fillable = ['center_id', 'template_key', 'body_hash', 'status', 'eskiz_template_id', 'last_error'];
}
