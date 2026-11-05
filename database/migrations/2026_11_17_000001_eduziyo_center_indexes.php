<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EDUZIYO E3: ko'p markazli ishlash uchun indekslar. Har so'rovga `center_id` filtri avtomatik qo'shiladi (CenterScope),
 * shuning uchun katta jadvallarda `center_id` bilan boshlanadigan kompozit indekslar kerak. Faqat qo'shimcha (ma'lumotga tegmaydi).
 */
return new class extends Migration
{
    /** jadval => [indeks nomi => ustunlar] */
    private const INDEXES = [
        'users' => ['users_center_role_status_idx' => ['center_id', 'role', 'status']],
        'payments' => ['payments_center_created_idx' => ['center_id', 'created_at']],
        'payouts' => ['payouts_center_created_idx' => ['center_id', 'created_at']],
        'attendances' => ['attendances_center_date_idx' => ['center_id', 'date']],
        'attendance_sessions' => ['att_sessions_center_date_idx' => ['center_id', 'date']],
        'audit_logs' => ['audit_logs_center_created_idx' => ['center_id', 'created_at']],
        'sms_messages' => [
            'sms_messages_center_created_idx' => ['center_id', 'created_at'],
            'sms_messages_status_created_idx' => ['status', 'created_at'],   // SmsDispatch: butun tizim bo'yicha «queued» qidiradi
        ],
        'leads' => ['leads_center_status_idx' => ['center_id', 'status']],
        'groups' => ['groups_center_ends_idx' => ['center_id', 'ends_on']],
        'group_students' => ['group_students_center_active_idx' => ['center_id', 'is_active']],
        'balance_transactions' => ['balance_tx_center_created_idx' => ['center_id', 'created_at']],
        'wallet_transactions' => ['wallet_tx_center_created_idx' => ['center_id', 'created_at']],
        'support_tickets' => ['support_tickets_center_status_idx' => ['center_id', 'status']],
        'center_subscriptions' => ['center_subs_center_ends_idx' => ['center_id', 'ends_at']],   // har so'rovda joriy obuna qidiriladi
        'topup_requests' => ['topup_requests_center_status_idx' => ['center_id', 'status']],
        'notification_recipients' => ['notif_recipients_created_idx' => ['user_id', 'created_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasColumns($table, $columns) || Schema::hasIndex($table, $name)) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
