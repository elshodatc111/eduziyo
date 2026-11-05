<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SIM kunlik chegarasi hisobi (modem sozlamalari sahifadan boshqariladi). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_modems', function (Blueprint $t) {
            $t->unsignedInteger('daily_sent')->default(0);
            $t->date('daily_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sms_modems', fn (Blueprint $t) => $t->dropColumn(['daily_sent', 'daily_date']));
    }
};
