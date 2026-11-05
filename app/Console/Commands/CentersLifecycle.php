<?php

namespace App\Console\Commands;

use App\Services\CenterLifecycle;
use Illuminate\Console\Command;

class CentersLifecycle extends Command
{
    protected $signature = 'centers:lifecycle {--dry-run : Hech narsani o\'zgartirmasdan nima bo\'lishini ko\'rsatadi}';

    protected $description = "Markazlar tarif hayot sikli: eslatma, faqat ko'rish rejimi va muddati o'tganini o'chirish";

    public function handle(CenterLifecycle $lifecycle): int
    {
        $stats = $lifecycle->run((bool) $this->option('dry-run'));
        $this->table(['Eslatma', "Faqat ko'rish", 'Ogohlantirish', "O'chirildi", 'SMS faktura (muddati o\'tdi)'], [array_values($stats)]);

        return self::SUCCESS;
    }
}
