<?php

namespace App\Console\Commands;

use App\Services\DemoService;
use Illuminate\Console\Command;

/** Demo markazni boshlang'ich holatga qaytaradi (har tun). Faqat is_demo markazga tegadi. */
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--dry-run : Hech narsa o\'chirmasdan nima o\'chirilishini ko\'rsatadi}';

    protected $description = "Ommaviy demo markazni tozalab, namunaviy ma'lumot bilan qayta yaratadi";

    public function handle(DemoService $demo): int
    {
        $dry = (bool) $this->option('dry-run');
        $report = $demo->reset($dry);

        $this->info($dry ? "Quruq ishga tushirish: o'chiriladigan qatorlar" : "Demo markaz tiklandi. O'chirilgan qatorlar");
        $this->table(['Jadval', 'Qatorlar'], collect($report)->map(fn ($n, $t) => [$t, $n])->values()->all());

        return self::SUCCESS;
    }
}
