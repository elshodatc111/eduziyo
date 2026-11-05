<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Markaz izolyatsiyasi auditi: `center_id` si bo'sh yoki filial markaziga mos kelmaydigan qatorlarni topadi.
 * (`Model::insert()`, `DB::table()` kabi model hodisalarini aylanib o'tadigan yozuvlar shunday qoldirishi mumkin.)
 * Kamida 1 ta muammo bo'lsa chiqish kodi 1. Ishlab chiqarishda kuniga bir marta ishga tushirish tavsiya etiladi.
 */
class TenancyAudit extends Command
{
    protected $signature = 'tenancy:audit';

    protected $description = "center_id si bo'sh yoki filial markaziga mos kelmaydigan qatorlarni tekshiradi";

    /** center_id = NULL bo'lishi mumkin bo'lgan jadvallar (platforma darajasidagi yozuvlar). */
    private const NULL_ALLOWED = ['audit_logs', 'notifications', 'import_batches'];

    public function handle(): int
    {
        $problems = 0;
        $rows = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if (! Schema::hasColumn($name, 'center_id')) {
                continue;
            }

            $nulls = $name === 'users'
                ? DB::table($name)->whereNull('center_id')->where('role', '!=', 'sadmin')->count()
                : (in_array($name, self::NULL_ALLOWED, true) ? 0 : DB::table($name)->whereNull('center_id')->count());

            $mismatch = 0;
            if ($name !== 'branches' && Schema::hasColumn($name, 'branch_id')) {
                $mismatch = DB::table($name)
                    ->join('branches', 'branches.id', '=', "{$name}.branch_id")
                    ->whereNotNull("{$name}.center_id")
                    ->whereColumn("{$name}.center_id", '!=', 'branches.center_id')
                    ->count();
            }

            if ($name === 'branches') {
                $nulls = DB::table($name)->whereNull('center_id')->count();
            }

            $problems += $nulls + $mismatch;
            $rows[] = [$name, $nulls, $mismatch];
        }

        $bad = array_filter($rows, fn ($r) => $r[1] > 0 || $r[2] > 0);

        if ($bad) {
            $this->table(['Jadval', "center_id bo'sh", 'Filial markaziga mos emas'], array_values($bad));
            $this->error("Muammo topildi: {$problems} ta qator.");

            return self::FAILURE;
        }

        $this->info('Markaz izolyatsiyasi toza: '.count($rows).' ta jadval tekshirildi, muammo yo\'q.');

        return self::SUCCESS;
    }
}
