<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EDUZIYO E4: markaz balansi, to'lov kartalari va to'ldirish so'rovlari, obuna (tarif davri), limit hisoblagichlari.
 * Faqat yangi jadvallar (mavjud ma'lumotga tegilmaydi). `center_id` bor jadvallarning hammasi `BelongsToCenter` modeliga ega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->unique()->constrained('centers');
            $t->bigInteger('balance')->default(0);
            $t->timestamps();
        });

        Schema::create('center_balance_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->constrained('centers');
            $t->string('type', 30);            // topup | tariff_purchase | tariff_refund | adjustment
            $t->bigInteger('amount');          // +kirim / -chiqim
            $t->bigInteger('balance_after');
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('payment_cards', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30);
            $t->string('holder', 120);
            $t->string('bank', 80)->nullable();
            $t->string('note', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('topup_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->constrained('centers');
            $t->foreignId('card_id')->nullable()->constrained('payment_cards')->nullOnDelete();
            $t->unsignedBigInteger('amount_claimed');
            $t->unsignedBigInteger('approved_amount')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->string('receipt_path')->nullable();
            $t->string('receipt_hash', 64)->nullable()->index();
            $t->string('note', 255)->nullable();
            $t->string('status', 12)->default('pending')->index();   // pending | approved | rejected
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->string('reject_reason', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('center_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->constrained('centers');
            $t->foreignId('tariff_id')->nullable()->constrained('tariffs')->nullOnDelete();
            $t->string('period', 8);                         // month | year
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->index();
            $t->bigInteger('price_paid')->default(0);
            $t->string('source', 10)->default('purchase');   // purchase | grant
            $t->json('snapshot');                            // tarif nusxasi (narx va limitlar o'zgarsa ham shu davrga ta'sir qilmaydi)
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('note', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('usage_counters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id')->constrained('centers');
            $t->string('kind', 10);                          // sms | ai
            $t->date('period_start');
            $t->unsignedInteger('used')->default(0);
            $t->unsignedInteger('limit_snapshot')->default(0);
            $t->timestamps();
            $t->unique(['center_id', 'kind', 'period_start']);
        });
    }

    public function down(): void
    {
        foreach (['usage_counters', 'center_subscriptions', 'topup_requests', 'payment_cards', 'center_balance_transactions', 'center_wallets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
