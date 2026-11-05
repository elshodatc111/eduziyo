<?php

namespace App\Services;

use App\Models\Center;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Markazning barcha ma'lumotini (foydalanuvchilar, filiallar, guruhlar, to'lovlar, ... ) bo'laklab o'chiradi.
 * `centers` qatorining o'zi tegmaydi. Demo tozalash va (keyinroq) tarif tugagandan keyingi o'chirish shuni ishlatadi.
 *
 * Xavfsizlik: faqat bitta markaz ID si bo'yicha `WHERE center_id = ?`; boshqa markaz qatorlariga tegmaydi.
 */
class CenterWiper
{
    /** center_id ustuni yo'q, lekin foydalanuvchiga bog'langan jadvallar (ustun nomi). */
    private const USER_LINKED = [
        'device_tokens' => 'user_id',
        'notification_recipients' => 'user_id',
        'password_reset_codes' => 'user_id',
        'submission_tokens' => 'user_id',
        'user_permissions' => 'user_id',
        'telegram_links' => 'user_id',
        'telegram_reset_codes' => 'user_id',
    ];

    /** @return array<string,int> jadval => o'chirilgan qatorlar soni */
    public function wipe(Center $center, bool $dryRun = false): array
    {
        $report = [];
        $userIds = DB::table('users')->where('center_id', $center->id)->pluck('id')->all();

        $run = function () use ($center, $userIds, $dryRun, &$report) {
            foreach (self::USER_LINKED as $table => $column) {
                if (Schema::hasTable($table) && $userIds) {
                    $report[$table] = $this->delete(DB::table($table)->whereIn($column, $userIds), $dryRun);
                }
            }

            if (Schema::hasTable('personal_access_tokens') && $userIds) {
                $report['personal_access_tokens'] = $this->delete(
                    DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)->whereIn('tokenable_id', $userIds), $dryRun
                );
            }

            if (Schema::hasTable('sessions') && $userIds) {
                $report['sessions'] = $this->delete(DB::table('sessions')->whereIn('user_id', $userIds), $dryRun);
            }

            $pending = [];
            foreach (Schema::getTables() as $table) {
                if ($table['name'] !== 'centers' && Schema::hasColumn($table['name'], 'center_id')) {
                    $pending[] = $table['name'];
                }
            }

            // Tashqi kalit (FK) tartibi: MySQL'da tekshiruv o'chiriladi; SQLite tranzaksiya ichida o'chirib bo'lmaydi,
            // shuning uchun FK xatosi bergan jadval keyingi turga qoldiriladi (bog'liq jadvallar avval o'chadi).
            for ($pass = 0; $pass < 30 && $pending; $pass++) {
                $next = [];

                foreach ($pending as $name) {
                    try {
                        $count = DB::transaction(fn () => $this->delete(DB::table($name)->where('center_id', $center->id), $dryRun));
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($e->getCode() !== '23000') {
                            throw $e;
                        }
                        $next[] = $name;

                        continue;
                    }

                    if ($count > 0) {
                        $report[$name] = ($report[$name] ?? 0) + $count;
                    }
                }

                if (count($next) === count($pending)) {
                    throw new \RuntimeException("Markaz ma'lumotini o'chirib bo'lmadi (tashqi kalit): ".implode(', ', $next));
                }

                $pending = $next;
            }
        };

        if ($dryRun) {
            $run();

            return $report;
        }

        // Markaz logotipi fayli ham o'chiriladi
        if ($center->logo_path) {
            \Illuminate\Support\Facades\Storage::disk('uploads')->delete($center->logo_path);
        }

        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('support/'.$center->id);

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction($run);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $report;
    }

    private function delete($query, bool $dryRun): int
    {
        return $dryRun ? (int) $query->count() : (int) $query->delete();
    }
}
