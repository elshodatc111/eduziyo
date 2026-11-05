<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** EDUZIYO E7d: ommaviy demo - rol bo'yicha kirishlar jurnali va arizada ko'rilgan demo rollari (additive). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_visits', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20)->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 160)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('sales_applications', function (Blueprint $table) {
            $table->string('demo_roles', 120)->nullable()->after('tariff_slug');
        });
    }

    public function down(): void
    {
        Schema::table('sales_applications', fn (Blueprint $table) => $table->dropColumn('demo_roles'));
        Schema::dropIfExists('demo_visits');
    }
};
