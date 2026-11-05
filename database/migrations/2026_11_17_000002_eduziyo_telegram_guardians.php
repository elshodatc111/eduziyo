<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** EDUZIYO E3: ota-ona va o'quvchining Telegram bot ulanishi (bir ota-ona — bir necha farzand). Faqat qo'shimcha. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_guardians', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $t->bigInteger('chat_id');
            $t->string('kind', 10);                         // parent | student
            $t->string('phone', 20);
            $t->string('tg_username', 64)->nullable();
            $t->boolean('notify_absence')->default(true);
            $t->boolean('notify_debt')->default(true);
            $t->boolean('notify_payment')->default(true);
            $t->timestamp('linked_at')->useCurrent();
            $t->timestamps();

            $t->unique(['student_id', 'chat_id']);
            $t->index('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_guardians');
    }
};
