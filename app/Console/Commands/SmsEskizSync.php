<?php

namespace App\Console\Commands;

use App\Services\Sms\EskizTemplateSync;
use Illuminate\Console\Command;

class SmsEskizSync extends Command
{
    protected $signature = 'sms:eskiz-sync';

    protected $description = "Eskiz shablonlari holatini yangilaydi (platforma va markaz akkauntlari) va xatoli yuborishlarni qaytaradi";

    public function handle(EskizTemplateSync $sync): int
    {
        $sync->refreshAll();
        $this->info('Eskiz shablon holatlari yangilandi.');

        return self::SUCCESS;
    }
}
