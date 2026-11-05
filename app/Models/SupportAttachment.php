<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/** Murojaat xabariga biriktirilgan rasm. Faqat markaz direktori va sAdmin nazorat qilingan marshrut orqali ko'radi. */
class SupportAttachment extends Model
{
    use BelongsToCenter;

    protected $fillable = ['center_id', 'message_id', 'path', 'original_name', 'mime', 'size'];
}
