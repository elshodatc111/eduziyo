<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Rozilik jurnali yozuvi. O'zgartirilmaydi va o'chirilmaydi (markaz o'chirilsa ham qoladi). */
class UserConsent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'user_name', 'user_role', 'center_ref', 'center_name', 'legal_document_id', 'accepted_at', 'ip', 'user_agent', 'method'];

    protected $casts = ['accepted_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }
}
