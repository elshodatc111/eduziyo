<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** EDUZIYO: markazning o'z logotipi (additive). Platforma nomi/logotipi `platform_settings` da saqlanadi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centers', fn (Blueprint $t) => $t->string('logo_path', 255)->nullable()->after('notes'));
    }

    public function down(): void
    {
        Schema::table('centers', fn (Blueprint $t) => $t->dropColumn('logo_path'));
    }
};
