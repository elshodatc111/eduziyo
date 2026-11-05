<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Murojaatga rasm (skrinshot) biriktirish. Fayllar yopiq diskda (storage/app/private/support) saqlanadi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $t->foreignId('message_id')->constrained('support_messages')->cascadeOnDelete();
            $t->string('path', 255);
            $t->string('original_name', 190);
            $t->string('mime', 60);
            $t->unsignedInteger('size');
            $t->timestamps();
            $t->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_attachments');
    }
};
