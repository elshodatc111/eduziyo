<?php

namespace App\Services\Sms;

use App\Services\Telegram\TelegramNotifier;
use App\Support\IntegrationSettings as S;
use Illuminate\Support\Facades\Cache;

/**
 * sAdmin uchun ogohlantirishlar (Telegram): modem/gateway ishlamayotgani va umumiy Eskiz qoldig'i kamayganini bildiradi.
 * `sms:health` har 5 daqiqada ishlaydi. Modem uchun ketma-ket 2 ta xato tekshiruvdan keyin bir marta xabar beriladi,
 * tiklanganda ham bir marta xabar yuboriladi. Eskiz qoldig'i kam bo'lsa kuniga bir marta.
 */
class SmsHealthMonitor
{
    /** Ketma-ket nechta xato tekshiruvdan keyin ogohlantiriladi. */
    public const FAILS_BEFORE_ALERT = 2;

    /** Umumiy Eskiz qoldig'i shundan kam bo'lsa ogohlantirish (S «eskiz_low_balance» bilan o'zgartiriladi). */
    public const DEFAULT_LOW_BALANCE = 200;

    public function __construct(private TelegramNotifier $tg) {}

    public function run(): void
    {
        $this->checkModem();
        $this->checkEskizBalance();
    }

    public function checkModem(): void
    {
        $client = SmsGatewayClient::make();
        if (! $client) {
            return;   // gateway sozlanmagan: kuzatadigan narsa yo'q
        }

        $h = $client->health();
        $ok = $h !== null && ($h['status'] ?? 'down') !== 'down' && (int) ($h['modems_usable'] ?? 0) > 0;

        if ($ok) {
            if (Cache::pull('sms-health:modem-alerted')) {
                $this->tg->toSadmins("✅ SMS modem qayta ishlayapti.");
            }
            Cache::forget('sms-health:modem-fails');

            return;
        }

        $fails = (int) Cache::get('sms-health:modem-fails', 0) + 1;
        Cache::put('sms-health:modem-fails', $fails, now()->addHour());

        if ($fails >= self::FAILS_BEFORE_ALERT && Cache::add('sms-health:modem-alerted', 1, now()->addDay())) {
            $why = $h === null ? "Gateway javob bermayapti" : 'Tayyor modem yo\'q (holat: '.($h['status'] ?? '?').')';
            $this->tg->toSadmins("⚠️ SMS modem ishlamayapti: {$why}. SMS lar zaxira Eskiz orqali yuboriladi (u ham sozlanmagan bo'lsa — yuborilmaydi).");
        }
    }

    public function checkEskizBalance(): void
    {
        $client = EskizClient::platform();
        if (! $client || ! Cache::add('sms-health:eskiz-checked', 1, now()->addHour())) {
            return;   // akkaunt yo'q yoki soatiga bir marta tekshiriladi
        }

        $balance = $client->balance();
        $limit = (int) (S::get('eskiz_low_balance') ?: self::DEFAULT_LOW_BALANCE);

        if ($balance !== null && $balance < $limit && Cache::add('sms-health:eskiz-low-alerted', 1, now()->addDay())) {
            $this->tg->toSadmins("⚠️ Umumiy Eskiz qoldig'i kam: {$balance} (chegara: {$limit}). Zaxira SMS lar to'xtab qolmasligi uchun to'ldiring.");
        }
    }
}
