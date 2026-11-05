<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Models\SmsMessage;
use Illuminate\Console\Command;

class SmsPruneText extends Command
{
    protected $signature = 'sms:prune-text';

    protected $description = "Belgilangan muddatdan eski SMS matnlarini tozalaydi (yozuv va holat qoladi)";

    public function handle(): int
    {
        $days = (int) PlatformSetting::value('sms_text_retention_days', '90');
        if ($days <= 0) {
            $this->info('Cheksiz saqlash: hech narsa tozalanmadi.');

            return self::SUCCESS;
        }

        $n = SmsMessage::withoutGlobalScopes()->where('created_at', '<', now()->subDays($days))->where('message', '!=', '[o\'chirildi]')
            ->update(['message' => '[o\'chirildi]', 'secret' => null]);
        $this->info("Tozalandi: {$n}");

        return self::SUCCESS;
    }
}
