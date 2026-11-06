<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS qayta tuzilishi:
 *  - sAdmin umumiy matnlarni tasdiqlaydi (sms_platform_templates);
 *  - har bir MARKAZ bitta o'z Eskiz akkauntini ulaydi (center_sms_accounts) — tarif SMS limiti tugagach shu orqali ketadi;
 *  - tasdiqlangan matnlar markaz Eskiz'iga avtomatik yuboriladi (center_sms_template_sync);
 *  - filial darajasidagi Eskiz maydonlari va SMS paket fakturalari (sms_invoices, extra_sms) olib tashlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_platform_templates', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();
            $t->string('label', 160);
            $t->text('body');
            $t->boolean('is_system')->default(false);          // filialga ko'rinmaydigan tizim matni (masalan, limit tugadi)
            $t->boolean('is_approved')->default(false);
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->string('eskiz_status', 20)->default('none');   // platforma Eskiz hisobida: none|pending|approved|rejected|error
            $t->string('eskiz_template_id', 60)->nullable();
            $t->string('eskiz_error', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('center_sms_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->unique()->constrained('centers')->cascadeOnDelete();
            $t->string('email', 190);
            $t->text('password');                              // shifrlangan (model cast)
            $t->string('sender', 30)->default('4546');
            $t->string('status', 20)->default('connected');    // connected|error
            $t->string('last_error', 255)->nullable();
            $t->timestamp('connected_at')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('center_sms_template_sync', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $t->string('template_key', 40);
            $t->string('body_hash', 64);
            $t->string('status', 20)->default('pending');     // pending|approved|rejected|error
            $t->string('eskiz_template_id', 60)->nullable();
            $t->string('last_error', 255)->nullable();
            $t->timestamps();
            $t->unique(['center_id', 'template_key']);
        });

        // SMS paket fakturalari butunlay olib tashlanadi (jadval va extra_sms ustuni)
        if (Schema::hasTable('sms_invoices')) {
            Schema::drop('sms_invoices');
        }
        if (Schema::hasColumn('center_subscriptions', 'extra_sms')) {
            Schema::table('center_subscriptions', fn (Blueprint $t) => $t->dropColumn('extra_sms'));
        }

        // Filialning o'z Eskiz maydonlari (endi har markazga bitta akkaunt)
        foreach (['eskiz_email', 'eskiz_password', 'eskiz_from'] as $col) {
            if (Schema::hasColumn('branches', $col)) {
                Schema::table('branches', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('center_sms_template_sync');
        Schema::dropIfExists('center_sms_accounts');
        Schema::dropIfExists('sms_platform_templates');
        Schema::table('branches', function (Blueprint $t) {
            $t->string('eskiz_email')->nullable();
            $t->text('eskiz_password')->nullable();
            $t->string('eskiz_from', 30)->nullable();
        });
        Schema::table('center_subscriptions', fn (Blueprint $t) => $t->unsignedInteger('extra_sms')->default(0));
    }
};
