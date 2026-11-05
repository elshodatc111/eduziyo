<?php

namespace App\Services;

use App\Models\Center;
use Illuminate\Support\Facades\Log;

/**
 * Markaz direktoriga tizim xabari (tarif eslatmasi va h.k.). Hozircha jurnalga yoziladi;
 * Telegram bot bosqichida shu joydan bot orqali yuboriladi.
 */
class CenterAlerts
{
    /** @var list<array{center_id:int,text:string}> testlar va keyingi kanallar uchun */
    public static array $sent = [];

    public function send(Center $center, string $text): void
    {
        self::$sent[] = ['center_id' => $center->id, 'text' => $text];
        Log::info("[markaz #{$center->id}] {$text}");

        if (class_exists(\App\Services\Telegram\TelegramNotifier::class)) {
            app(\App\Services\Telegram\TelegramNotifier::class)->toCenterOwner($center, $text);
        }
    }
}
