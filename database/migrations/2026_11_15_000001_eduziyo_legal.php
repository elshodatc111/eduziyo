<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** E1: huquqiy hujjatlar (oferta, maxfiylik, shartlar), rozilik jurnali, ota-ona roziligi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $t) {
            $t->id();
            $t->string('type', 20); // offer | privacy | terms
            $t->string('audience', 20)->default('all'); // all | owner | staff | student
            $t->unsignedInteger('version')->default(1);
            $t->string('title', 200);
            $t->longText('body');
            $t->string('status', 12)->default('draft'); // draft | published (e'londan keyin o'zgarmaydi)
            $t->boolean('requires_reacceptance')->default(true);
            $t->timestamp('effective_at')->nullable();
            $t->unsignedBigInteger('published_by')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['type', 'audience', 'version']);
        });

        // Rozilik jurnali: o'zgarmaydi, foydalanuvchi/markaz o'chirilsa ham saqlanadi (FK yo'q, nusxa ustunlar bor).
        Schema::create('user_consents', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('user_name', 160)->nullable();
            $t->string('user_role', 20)->nullable();
            $t->unsignedBigInteger('center_ref')->nullable();
            $t->string('center_name', 160)->nullable();
            $t->foreignId('legal_document_id')->constrained('legal_documents')->restrictOnDelete();
            $t->timestamp('accepted_at');
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->string('method', 20)->default('web');
            $t->index(['user_id', 'legal_document_id']);
            $t->index('center_ref');
        });

        // Direktor markaz nomidan oferta qabul qilganda: tashkilot va shaxs.
        Schema::create('center_agreements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('center_ref');
            $t->string('organization', 160);
            $t->string('accepted_by_name', 160);
            $t->unsignedBigInteger('accepted_by_user')->nullable();
            $t->foreignId('legal_document_id')->constrained('legal_documents')->restrictOnDelete();
            $t->timestamp('accepted_at');
            $t->string('ip', 45)->nullable();
            $t->index('center_ref');
        });

        Schema::table('leads', function (Blueprint $t) {
            $t->timestamp('consent_at')->nullable();
            $t->string('consent_version', 40)->nullable();
        });
        Schema::table('sales_applications', function (Blueprint $t) {
            $t->timestamp('consent_at')->nullable();
            $t->string('consent_version', 40)->nullable();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('guardian_name', 160)->nullable();
            $t->timestamp('guardian_consent_at')->nullable();
        });

        // Dastlabki qoralamalar (e'lon qilinmagan): sAdmin tahrirlab, yurist ko'rib chiqqach e'lon qiladi.
        foreach (\App\Support\LegalDefaults::all() as $d) {
            \Illuminate\Support\Facades\DB::table('legal_documents')->insert($d + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['guardian_name', 'guardian_consent_at']));
        Schema::table('sales_applications', fn (Blueprint $t) => $t->dropColumn(['consent_at', 'consent_version']));
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['consent_at', 'consent_version']));
        Schema::dropIfExists('center_agreements');
        Schema::dropIfExists('user_consents');
        Schema::dropIfExists('legal_documents');
    }
};
