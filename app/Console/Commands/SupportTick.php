<?php

namespace App\Console\Commands;

use App\Services\SupportService;
use Illuminate\Console\Command;

class SupportTick extends Command
{
    protected $signature = 'support:tick';

    protected $description = "Murojaatlar: SLA eslatmasi va 7 kun javobsiz qolganini avtomatik yopish";

    public function handle(SupportService $support): int
    {
        $r = $support->tick();
        $this->info("Eslatma: {$r['reminded']}, yopildi: {$r['closed']}");

        return self::SUCCESS;
    }
}
