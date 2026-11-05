<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** EDUZIYO E5: SMS kanallari (modem/Eskiz), modem navbati, SMS faktura va qo'shimcha SMS. Faqat qo'shimcha. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $t) {
            $t->string('channel', 10)->nullable();   // modem | eskiz
        });

        Schema::table('center_subscriptions', function (Blueprint $t) {
            $t->unsignedInteger('extra_sms')->default(0);   // sotib olingan qo'shimcha SMS (tarif davri oxirigacha)
        });

        Schema::create('sms_outbox', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sms_id')->unique()->constrained('sms_messages')->cascadeOnDelete();
            $t->string('status', 10)->default('queued')->index();   // queued | claimed | sent | failed | fallback
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('claimed_at')->nullable();
            $t->timestamp('reported_at')->nullable();
            $t->string('error', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('sms_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->constrained('centers');
            $t->unsignedInteger('sms_count');
            $t->unsignedBigInteger('amount');
            $t->string('status', 10)->default('pending');   // pending | accepted | cancelled | expired
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at');
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_invoices');
        Schema::dropIfExists('sms_outbox');
        Schema::table('center_subscriptions', fn (Blueprint $t) => $t->dropColumn('extra_sms'));
        Schema::table('sms_messages', fn (Blueprint $t) => $t->dropColumn('channel'));
    }
};
