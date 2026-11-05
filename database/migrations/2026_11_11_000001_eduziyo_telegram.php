<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** EDUZIYO E8: Telegram bot - foydalanuvchi ulanishi, parol tiklash kodlari, ariza tasdiqlash tokeni. Faqat qo'shimcha. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->bigInteger('chat_id')->unique();
            $t->string('tg_username', 64)->nullable();
            $t->timestamp('linked_at')->useCurrent();
            $t->timestamps();
        });

        Schema::create('telegram_reset_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('code_hash', 64);
            $t->timestamp('expires_at');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::table('sales_applications', function (Blueprint $t) {
            $t->string('telegram_token', 40)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('sales_applications', fn (Blueprint $t) => $t->dropColumn('telegram_token'));
        Schema::dropIfExists('telegram_reset_codes');
        Schema::dropIfExists('telegram_links');
    }
};
