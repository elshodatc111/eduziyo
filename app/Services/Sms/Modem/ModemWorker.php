<?php

namespace App\Services\Sms\Modem;

use App\Models\SmsMessage;
use App\Models\SmsModem;
use App\Models\SmsOutbox;
use App\Services\Sms\SmsDispatcher;
use Illuminate\Support\Facades\Cache;

/**
 * Doimiy ishlovchi (`php artisan sms:modem-run`, systemd): modemlarni avtomatik topadi/yangilaydi va navbatdagi SMS'larni
 * tayyor modemlar orasida navbatma-navbat yuboradi. Xato bo'lgan xabarlar darhol Eskiz'ga o'tkaziladi.
 */
class ModemWorker
{
    public const HEARTBEAT_KEY = 'sms.modem_worker_seen';
    public const SCAN_EVERY = 60;
    public const SCAN_REQUEST_KEY = 'sms.modem_scan_request';
    public const SCAN_DONE_KEY = 'sms.modem_scan_done';

    public static function testKey(int $modemId): string
    {
        return 'sms.modem_test.'.$modemId;
    }

    public static function testResultKey(string $token): string
    {
        return 'sms.modem_test_result.'.$token;
    }

    /** Xabarlar orasidagi pauza (soniya). Testlarda o'chiriladi. @var callable(int):void */
    public static $pauser = null;

    private int $lastScan = 0;

    public function __construct(private ModemScanner $scanner, private ModemSender $sender, private SmsDispatcher $dispatcher) {}

    public static function alive(): bool
    {
        $ts = Cache::get(self::HEARTBEAT_KEY);

        return $ts && $ts > now()->subSeconds(SmsModem::SEEN_SECONDS)->timestamp;
    }

    /** Oxirgi 15 soniyada ishchi yurak urishi berganmi (ikkinchi nusxani oldini olish uchun). */
    public static function activeRecently(): bool
    {
        $ts = Cache::get(self::HEARTBEAT_KEY);

        return $ts && $ts > now()->subSeconds(15)->timestamp;
    }

    /** @return array{sent:int, failed:int, scanned:bool} */
    public function tick(bool $forceScan = false): array
    {
        Cache::put(self::HEARTBEAT_KEY, now()->timestamp, now()->addDay());

        // Veb-panelda (qurilmasiz konteyner) bosilgan «Qayta qidirish» — port faqat shu ishchida ko'rinadi
        $scanRequest = Cache::pull(self::SCAN_REQUEST_KEY);

        $scanned = false;
        if ($forceScan || $scanRequest || time() - $this->lastScan >= self::SCAN_EVERY) {
            $report = $this->scanner->scan();
            $this->lastScan = time();
            $scanned = true;
            if ($scanRequest) {
                Cache::put(self::SCAN_DONE_KEY, ['req' => $scanRequest, 'found' => (int) ($report['found'] ?? 0)], 120);
            }
        }

        $this->handleTestRequests();

        $sent = $failed = 0;
        $rows = SmsOutbox::where('status', 'queued')->orderBy('id')->limit(30)->get();

        foreach ($rows as $row) {
            // SIM kunlik chegarasiga yetganlar o'tkazib yuboriladi
            $modem = SmsModem::usable()->orderByRaw('last_sent_at is null desc')->orderBy('last_sent_at')->get()
                ->first(fn (SmsModem $m) => $m->remainingToday() !== 0);
            if (! $modem) {
                break;   // tayyor modem yo'q (yoki hammasi chegaraga yetgan) — SmsDispatcher 2 daqiqadan so'ng Eskiz'ga o'tkazadi
            }

            $sms = SmsMessage::withoutGlobalScopes()->find($row->sms_id);
            if (! $sms || $sms->status !== SmsMessage::QUEUED) {
                $row->update(['status' => 'fallback']);

                continue;
            }

            if ($sent + $failed > 0) {
                $this->pause();   // faqat xabarlar orasida
            }

            $row->update(['status' => 'claimed', 'claimed_at' => now(), 'attempts' => $row->attempts + 1]);
            $r = $this->sender->send($modem, $sms->phone, $sms->secret ?? $sms->message);

            if ($r['ok']) {
                $row->update(['status' => 'sent', 'reported_at' => now()]);
                $sms->update(['status' => SmsMessage::SENT, 'sent_at' => now(), 'provider_response' => 'Modem: '.$modem->name(), 'secret' => null, 'channel' => 'modem']);
                $sent++;
                $this->countDaily($modem);
            } else {
                $row->update(['status' => 'failed', 'reported_at' => now(), 'error' => mb_substr((string) $r['error'], 0, 250)]);
                $sms->update(['provider_response' => mb_substr('Modem xatosi: '.$r['error'], 0, 250)]);
                $failed++;
            }
        }

        $this->dispatcher->run();   // xato/olinmagan xabarlar -> Eskiz

        return ['sent' => $sent, 'failed' => $failed, 'scanned' => $scanned];
    }

    /** Veb-paneldagi «Sinov SMS» so'rovlari: yuborish shu ishchida bajariladi, natija keshga qo'yiladi. */
    private function handleTestRequests(): void
    {
        foreach (SmsModem::query()->whereNotNull('device')->get() as $modem) {
            $req = Cache::pull(self::testKey($modem->id));
            if (! is_array($req) || empty($req['token'])) {
                continue;
            }
            $r = $this->sender->send($modem, (string) $req['phone'], (string) $req['text']);
            Cache::put(self::testResultKey((string) $req['token']), $r, 60);
        }
    }

    private function countDaily(SmsModem $modem): void
    {
        $today = $modem->daily_date && $modem->daily_date->isToday();
        $modem->update(['daily_date' => today(), 'daily_sent' => $today ? $modem->daily_sent + 1 : 1]);
    }

    /** Operator bloklamasligi uchun xabarlar orasida tasodifiy pauza (sozlamalardan). */
    private function pause(): void
    {
        $min = max(0, \App\Support\IntegrationSettings::int('modem_pause_min', 3));
        $max = max($min, \App\Support\IntegrationSettings::int('modem_pause_max', 8));
        $sec = $max > 0 ? random_int($min, $max) : 0;

        if ($sec > 0) {
            (self::$pauser ?? 'sleep')($sec);
        }
    }
}
