<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** «Filiallar xodimlari» sahifasi olib tashlandi: eskirgan ruxsat yozuvlari tozalanadi (boshqa ma'lumotga tegilmaydi). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_permissions')) {
            DB::table('user_permissions')->where('permission', 'staff.view_all_branches')->delete();
        }
    }

    public function down(): void
    {
        // Ruxsat qaytarilmaydi (funksiya olib tashlangan).
    }
};
