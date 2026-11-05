<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EDUZIYO E0: ko'p markazli (multi-tenant) qatlam.
 *
 *  - `centers` jadvali (Platforma → Markaz → Filial);
 *  - `branches`, `users` va filialga bog'liq barcha jadvallarga `center_id` (additive, nullable + indeks + FK);
 *  - mavjud ma'lumot bo'lsa - bitta "Asosiy markaz" yaratilib, hamma yozuv unga biriktiriladi (ma'lumot buzilmaydi);
 *  - `users.role` ENUM'iga `owner` (direktor) qo'shiladi.
 *
 * `center_id` ustuni DB darajasida NULL'ga ruxsat beradi (mavjud bazani buzmaslik uchun), lekin ilova darajasida
 * har doim to'ldiriladi va CenterScope NULL qatorlarni markaz foydalanuvchilariga ko'rsatmaydi
 * (xato bo'lsa ma'lumot "yo'qoladi", boshqa markazga "oqib" o'tmaydi). `php artisan tenancy:audit` shunday qatorlarni topadi.
 */
return new class extends Migration
{
    /** Filialga bog'liq jadvallar: center_id ularning branch_id si orqali to'ldiriladi. */
    private const BRANCH_TABLES = [
        'ai_chats', 'ai_messages', 'attendance_sessions', 'attendances', 'audit_logs', 'balance_transactions', 'books',
        'cash_closings', 'cash_requests', 'course_audios', 'course_questions', 'course_videos', 'courses',
        'discount_campaigns', 'expense_categories', 'group_students', 'groups', 'holidays', 'lead_sources', 'leads',
        'lesson_times', 'notifications', 'payments', 'payouts', 'price_plans', 'rooms', 'sms_messages', 'sms_templates',
        'student_notes', 'task_dismissals', 'test_attempts', 'user_branches', 'wallet_transactions', 'wallets',
    ];

    public function up(): void
    {
        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('owner_phone', 30)->nullable();
            $table->timestamp('tariff_ends_at')->nullable();
            $table->timestamp('read_only_since')->nullable();
            $table->timestamp('delete_after')->nullable();
            $table->json('reminders_sent')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $tables = array_merge(['branches', 'users', 'group_days', 'lead_notes', 'import_batches'], self::BRANCH_TABLES);

        foreach ($tables as $name) {
            if (Schema::hasColumn($name, 'center_id')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('center_id')->nullable()->constrained('centers');
            });
        }

        // Mavjud ma'lumot bo'lsa - bitta asosiy markazga biriktiriladi
        if (DB::table('branches')->exists()) {
            $now = now();
            $centerId = DB::table('centers')->insertGetId([
                'name' => 'Asosiy markaz', 'code' => 'main', 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('branches')->update(['center_id' => $centerId]);
            DB::table('users')->where('role', '!=', 'sadmin')->update(['center_id' => $centerId]);

            foreach (self::BRANCH_TABLES as $name) {
                DB::statement("UPDATE `{$name}` SET center_id = (SELECT center_id FROM branches WHERE branches.id = `{$name}`.branch_id) WHERE branch_id IS NOT NULL");
                DB::table($name)->whereNull('center_id')->update(['center_id' => $centerId]);
            }

            DB::statement('UPDATE group_days SET center_id = (SELECT center_id FROM `groups` WHERE `groups`.id = group_days.group_id)');
            DB::statement('UPDATE lead_notes SET center_id = (SELECT center_id FROM leads WHERE leads.id = lead_notes.lead_id)');
            DB::statement('UPDATE import_batches SET center_id = (SELECT center_id FROM users WHERE users.id = import_batches.user_id)');
            DB::table('group_days')->whereNull('center_id')->update(['center_id' => $centerId]);
            DB::table('lead_notes')->whereNull('center_id')->update(['center_id' => $centerId]);
            DB::table('import_batches')->whereNull('center_id')->update(['center_id' => $centerId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['sadmin', 'admin', 'manager', 'teacher', 'student', 'operator', 'owner'])->change();
        });
    }

    public function down(): void
    {
        // Ataylab bo'sh: markaz qatlamini qaytarish ma'lumot yo'qotishiga olib kelishi mumkin.
    }
};
