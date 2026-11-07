<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsHealthMonitor;
use Illuminate\Console\Command;

class SmsHealthCheck extends Command
{
    protected $signature = 'sms:health';

    protected $description = "Modem (gateway) va umumiy Eskiz qoldig'ini tekshiradi; muammo bo'lsa sAdminlarga Telegram orqali xabar beradi";

    public function handle(SmsHealthMonitor $monitor): int
    {
        try {
            $monitor->run();
        } catch (\Throwable $e) {
            report($e);   // kuzatuv xatosi hech narsani buzmasin
        }

        return self::SUCCESS;
    }
}
