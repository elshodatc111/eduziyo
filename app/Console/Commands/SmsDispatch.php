<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsDispatcher;
use Illuminate\Console\Command;

class SmsDispatch extends Command
{
    protected $signature = 'sms:dispatch';

    protected $description = "Modem navbatidagi olinmagan yoki xato SMS'larni Eskiz'ga o'tkazadi";

    public function handle(SmsDispatcher $dispatcher): int
    {
        $r = $dispatcher->run();
        $this->info("Eskiz'ga o'tkazildi: {$r['fallback']}");

        return self::SUCCESS;
    }
}
