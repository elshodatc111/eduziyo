<?php

namespace App\Console\Commands;

use App\Models\SmsMessage;
use App\Services\Sms\SmsGatewayClient;
use App\Services\SmsService;
use Illuminate\Console\Command;

class SmsGatewaySync extends Command
{
    protected $signature = 'sms:gateway-sync';

    protected $description = "Gateway navbatida qolgan SMS'lar holatini tekshiradi (webhook o'tkazib yuborilgan bo'lsa); yuborilmaganlar Eskiz'ga o'tadi";

    /** Gateway navbatda saqlash muddati (15 daq) + zaxira: shundan keyin ham yakuniy holat kelmasa Eskiz'ga o'tkazamiz. */
    public const STUCK_MINUTES = 25;

    public function handle(SmsService $svc): int
    {
        $client = SmsGatewayClient::make();
        $done = $fallback = 0;

        $rows = SmsMessage::withoutGlobalScopes()->where('channel', 'gateway')->where('status', SmsMessage::QUEUED)->whereNotNull('gateway_id')
            ->where('created_at', '>', now()->subDay())->where('updated_at', '<', now()->subMinutes(2))->limit(300)->get();

        foreach ($rows as $sms) {
            $r = $client?->find($sms->gateway_id);

            if ($r === null) {   // gateway javob bermadi: uzoq kutgan xabarni Eskiz'ga o'tkazamiz
                if ($sms->created_at->lt(now()->subMinutes(self::STUCK_MINUTES))) {
                    $svc->applyGatewayStatus($sms, 'expired', 'Gateway javob bermadi');
                    $fallback++;
                }

                continue;
            }
            if (! empty($r['missing'])) {
                $svc->applyGatewayStatus($sms, 'failed', 'Gateway xabarni topmadi');
                $fallback++;

                continue;
            }

            $status = (string) ($r['status'] ?? '');
            if (in_array($status, ['sent', 'failed', 'expired', 'cancelled'], true)) {
                $svc->applyGatewayStatus($sms, $status, $r['error'] ?? null);
                $done++;
            } elseif ($sms->created_at->lt(now()->subMinutes(self::STUCK_MINUTES))) {
                $svc->applyGatewayStatus($sms, 'expired', 'Gateway navbatida juda uzoq turdi');
                $fallback++;
            }
        }

        $this->info("Yangilandi: {$done}, Eskiz'ga o'tkazildi: {$fallback}");

        return self::SUCCESS;
    }
}
