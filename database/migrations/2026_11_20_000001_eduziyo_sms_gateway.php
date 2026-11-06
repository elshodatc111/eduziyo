<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS Gateway (sms.elshod.date) asosiy kanal bo'ldi; USB modem qismi butunlay olib tashlandi
 * (sms_modems, sms_outbox jadvallari). sms_messages ga gateway xabar identifikatori qo'shiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $t) {
            $t->string('gateway_id', 40)->nullable()->index();
        });

        Schema::dropIfExists('sms_outbox');
        Schema::dropIfExists('sms_modems');

        // Eski modem kanalidagi navbatdagi (yuborilmagan) xabarlar endi hech kim tomonidan olinmaydi
        \Illuminate\Support\Facades\DB::table('sms_messages')->where('channel', 'modem')->where('status', 'queued')
            ->update(['status' => 'failed', 'provider_response' => "USB modem olib tashlandi: xabar yuborilmadi"]);
    }

    public function down(): void
    {
        Schema::table('sms_messages', fn (Blueprint $t) => $t->dropColumn('gateway_id'));
        // sms_modems / sms_outbox qayta yaratilmaydi (modem qismi olib tashlangan)
    }
};
