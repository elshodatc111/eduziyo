<?php

namespace App\Console\Commands;

use App\Services\Sms\Modem\ModemScanner;
use Illuminate\Console\Command;

class SmsModemScan extends Command
{
    protected $signature = 'sms:modem-scan';

    protected $description = "Serverga ulangan USB modemlarni qidiradi va holatini ko'rsatadi";

    public function handle(ModemScanner $scanner): int
    {
        $r = $scanner->scan();
        $this->table(['Port', 'Natija'], array_map(fn ($x) => [$x['device'], $x['result']], $r['report']));
        $this->info("Topilgan modemlar: {$r['found']}");

        return self::SUCCESS;
    }
}
