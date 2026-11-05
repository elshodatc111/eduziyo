<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Ko'p markaz indekslari (2026_11_17_000001) mavjud va migratsiya qayta ishga tushsa xato bermaydi. */
class CenterIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_center_indexes_exist(): void
    {
        foreach ([
            'users' => 'users_center_role_status_idx', 'payments' => 'payments_center_created_idx', 'audit_logs' => 'audit_logs_center_created_idx',
            'sms_messages' => 'sms_messages_status_created_idx', 'center_subscriptions' => 'center_subs_center_ends_idx', 'leads' => 'leads_center_status_idx',
        ] as $table => $index) {
            $this->assertTrue(Schema::hasIndex($table, $index), "{$table}.{$index} yo'q");
        }
    }

    public function test_migration_is_idempotent(): void
    {
        $m = require database_path('migrations/2026_11_17_000001_eduziyo_center_indexes.php');
        $m->up();
        $m->up();
        $this->assertTrue(Schema::hasIndex('payments', 'payments_center_created_idx'));
    }
}
