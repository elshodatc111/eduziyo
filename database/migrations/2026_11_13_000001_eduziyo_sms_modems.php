<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** EDUZIYO: serverga ulangan USB modemlar (avtomatik topiladi). Platforma darajasida (center_id yo'q). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_modems', function (Blueprint $t) {
            $t->id();
            $t->string('imei', 20)->unique();
            $t->string('label', 80)->nullable();            // sAdmin bergan nom
            $t->string('device', 120)->nullable();          // /dev/ttyUSB2 (har skanerlashda yangilanadi)
            $t->string('model', 80)->nullable();
            $t->string('imsi', 20)->nullable();
            $t->string('operator', 60)->nullable();
            $t->unsignedTinyInteger('signal')->nullable();  // 0-100 %
            $t->string('sim_status', 30)->nullable();      // READY | SIM PIN | NO SIM ...
            $t->boolean('registered')->default(false);      // tarmoqqa ulanganmi
            $t->string('status', 20)->default('offline')->index(); // ready | no_network | sim_error | offline | error
            $t->boolean('enabled')->default(true);          // sAdmin faollashtirgan/o'chirgan
            $t->unsignedInteger('sent_count')->default(0);
            $t->unsignedInteger('failed_count')->default(0);
            $t->unsignedTinyInteger('fail_streak')->default(0);
            $t->string('last_error', 255)->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_modems');
    }
};
