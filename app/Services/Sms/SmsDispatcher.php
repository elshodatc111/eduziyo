<?php

namespace App\Services\Sms;

use App\Models\SmsMessage;
use App\Models\SmsOutbox;
use App\Services\SmsService;

/**
 * Modem navbatini kuzatadi: gateway olmagan (2 daqiqa), olib natija bermagan (5 daqiqa) yoki xato qaytargan xabarlar
 * AVTOMATIK Eskiz'ga o'tkaziladi. Har daqiqada ishlaydi (`sms:dispatch`).
 */
class SmsDispatcher
{
    public const QUEUED_TIMEOUT_MIN = 2;
    public const CLAIMED_TIMEOUT_MIN = 5;
    public const MAX_AGE_HOURS = 24;

    public function __construct(private SmsService $sms) {}

    /** @return array{fallback:int} */
    public function run(): array
    {
        $rows = SmsOutbox::query()
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('status', 'queued')->where('updated_at', '<', now()->subMinutes(self::QUEUED_TIMEOUT_MIN)))
                ->orWhere(fn ($w) => $w->where('status', 'claimed')->where('claimed_at', '<', now()->subMinutes(self::CLAIMED_TIMEOUT_MIN)))
                ->orWhere('status', 'failed'))
            ->where('created_at', '>', now()->subHours(self::MAX_AGE_HOURS))
            ->limit(200)->get();

        $n = 0;
        foreach ($rows as $row) {
            $sms = $row->sms;
            $row->update(['status' => 'fallback']);

            if ($sms && $sms->status === SmsMessage::QUEUED) {
                app(EskizDriver::class)->send($sms);
                $n++;
            }
        }

        return ['fallback' => $n];
    }
}
