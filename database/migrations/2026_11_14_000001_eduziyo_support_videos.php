<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** EDUZIYO: qo'llab-quvvatlash tiketlari (markazga tegishli) va rol bo'yicha video qo'llanmalar (platforma). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $t->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('subject', 160);
            $t->string('status', 20)->default('open')->index();      // open (javob kutilmoqda) | pending_customer | closed
            $t->timestamp('sla_due_at')->nullable();
            $t->timestamp('first_response_at')->nullable();
            $t->timestamp('sla_reminded_at')->nullable();
            $t->timestamp('last_staff_at')->nullable();
            $t->timestamp('last_customer_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->string('closed_reason', 20)->nullable();             // manual | auto
            $t->unsignedTinyInteger('rating')->nullable();
            $t->string('rating_comment', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $t->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_staff')->default(false);
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('video_guides', function (Blueprint $t) {
            $t->id();
            $t->string('title', 160);
            $t->string('url', 500);
            $t->json('roles');                                       // ["owner","admin",...] — o'quvchi roli ruxsat etilmaydi
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(100);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_guides');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
