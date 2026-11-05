<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EDUZIYO E7: sotuv sahifasi. Platforma darajasidagi (markazga bog'liq bo'lmagan) jadvallar:
 * sotuv arizalari, yangiliklar va platforma sozlamalari (aloqa ma'lumotlari). Faqat sAdmin boshqaradi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_applications', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('center_name', 160);
            $t->string('phone', 20);
            $t->string('city', 80)->nullable();
            $t->unsignedSmallInteger('branches_count')->nullable();
            $t->unsignedInteger('students_count')->nullable();
            $t->text('message')->nullable();
            $t->string('status', 20)->default('new');
            $t->timestamp('phone_verified_at')->nullable();   // Telegram bot orqali tasdiqlanganda to'ldiriladi
            $t->unsignedBigInteger('telegram_chat_id')->nullable();
            $t->string('utm_source', 100)->nullable();
            $t->string('utm_medium', 100)->nullable();
            $t->string('utm_campaign', 100)->nullable();
            $t->date('follow_up_at')->nullable();
            $t->text('internal_note')->nullable();
            $t->string('ip', 45)->nullable();
            $t->foreignId('opened_center_id')->nullable()->constrained('centers')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'created_at']);
            $t->index('phone');
        });

        Schema::create('news_posts', function (Blueprint $t) {
            $t->id();
            $t->string('title', 160);
            $t->string('slug', 190)->unique();
            $t->string('excerpt', 300)->nullable();
            $t->text('body');
            $t->boolean('is_published')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['is_published', 'published_at']);
        });

        Schema::create('platform_settings', function (Blueprint $t) {
            $t->string('key', 80)->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        // Ma'lumot yo'qolmasligi uchun ataylab bo'sh.
    }
};
