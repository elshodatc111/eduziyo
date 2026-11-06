<?php

namespace App\Services;

use App\Services\Sms\SmsGatewayClient;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Support\Facades\Cache;

/**
 * Jarayonlar nazorati: cron, navbat ishchisi, SMS Gateway, zaxira, disk va kassa to'xtasa/buzilsa sAdminlarga Telegram xabari.
 * Faqat HOLAT O'ZGARGANDA xabar beradi («to'xtadi» / «tiklandi»), muammo davom etsa {@see REMIND_HOURS} soatda bir eslatadi.
 * Cron o'zi to'xtasa buyruqni cron ishga tushira olmaydi — shuning uchun Docker'da alohida `watchdog` konteyneri (har 60 soniya) ham bor.
 */
class SystemWatch
{
    public const STATE_KEY = 'system.watch_state';
    public const REMIND_HOURS = 6;

    /** Faqat shu kalitlar «warning» da ham xabar beradi; qolganlari faqat «critical» da. */
    private const WARN_KEYS = ['cron', 'queue', 'telegram_backup', 'disk'];

    public function __construct(private SystemStatusService $status, private TelegramNotifier $tg) {}

    /**
     * Joriy muammolar: kalit => matn.
     *
     * @return array<string,string>
     */
    public function problems(): array
    {
        $out = [];
        foreach ($this->status->checks() as $c) {
            if ($c['status'] === 'critical' || ($c['status'] === 'warning' && in_array($c['key'], self::WARN_KEYS, true))) {
                $out[$c['key']] = $c['label'].': '.$c['detail'];
            }
        }

        // SMS Gateway: sozlangan bo'lsa tirik bo'lishi kerak (o'lik bo'lsa SMS Eskiz orqali ketadi)
        if (($gw = SmsGatewayClient::make()) && ! $gw->healthy()) {
            $out['sms_gateway'] = "SMS Gateway javob bermayapti (modem uzilgan bo'lishi mumkin). SMS vaqtincha Eskiz orqali ketadi.";
        }

        return $out;
    }

    /** @return array{down:list<string>, up:list<string>, remind:list<string>} */
    public function run(): array
    {
        $now = $this->problems();
        $state = Cache::get(self::STATE_KEY, []);
        $down = $up = $remind = [];

        foreach ($now as $key => $text) {
            if (! isset($state[$key])) {
                $down[] = $key;
                $state[$key] = ['since' => now()->timestamp, 'last' => now()->timestamp];
                $this->tg->toSadmins("🔴 <b>Muammo</b>\n".e($text)."\n\n".config('app.url'));
            } elseif (now()->timestamp - $state[$key]['last'] >= self::REMIND_HOURS * 3600) {
                $remind[] = $key;
                $state[$key]['last'] = now()->timestamp;
                $mins = (int) round((now()->timestamp - $state[$key]['since']) / 60);
                $this->tg->toSadmins("🟠 <b>Muammo davom etmoqda</b> ({$mins} daqiqa)\n".e($text));
            }
        }

        foreach ($state as $key => $info) {
            if (! isset($now[$key])) {
                $up[] = $key;
                $mins = (int) round((now()->timestamp - $info['since']) / 60);
                $this->tg->toSadmins("🟢 <b>Tiklandi</b>: {$key} ({$mins} daqiqadan so'ng)");
                unset($state[$key]);
            }
        }

        Cache::forever(self::STATE_KEY, $state);

        return compact('down', 'up', 'remind');
    }
}
