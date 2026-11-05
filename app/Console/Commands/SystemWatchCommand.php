<?php

namespace App\Console\Commands;

use App\Services\SystemWatch;
use Illuminate\Console\Command;

class SystemWatchCommand extends Command
{
    protected $signature = 'system:watch {--loop : Doimiy ishlash (har 60 soniya) — Docker watchdog uchun}';

    protected $description = "Cron, navbat va modem ishchisi, zaxira, disk holatini tekshirib, muammo bo'lsa sAdminlarga Telegram xabari yuboradi";

    public function handle(SystemWatch $watch): int
    {
        do {
            $r = $watch->run();
            $this->line(sprintf('[%s] muammo: %d, tiklandi: %d, eslatma: %d', now()->format('H:i:s'), count($r['down']), count($r['up']), count($r['remind'])));

            if ($this->option('loop')) {
                sleep(60);
            }
        } while ($this->option('loop'));

        return self::SUCCESS;
    }
}
