<?php

namespace App\Services\Sms\Modem;

use App\Models\SmsModem;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Serverga ulangan USB modemlarni AVTOMATIK topadi: /dev/ttyUSB*, /dev/ttyACM* ni AT buyruq bilan tekshiradi,
 * IMEI bo'yicha guruhlaydi (bitta modem bir nechta port ochadi), SIM/tarmoq/signalni o'qiydi va bazaga yozadi.
 * Yangi modem avtomatik faollashadi; ko'rinmay qolganlari «Ulanmagan» bo'ladi.
 */
class ModemScanner
{
    public const REPORT_KEY = 'sms.modem_scan_report';

    public function __construct(private PortFactory $ports) {}

    /** @return array{found:int, report:list<array{device:string, result:string}>} */
    public function scan(): array
    {
        $report = [];
        $seen = [];

        foreach ($this->ports->candidates() as $device) {
            try {
                $port = $this->ports->open($device);
            } catch (Throwable $e) {
                $report[] = ['device' => $device, 'result' => $e->getMessage()];

                continue;
            }

            try {
                $at = new AtClient($port);
                if (! $at->alive()) {
                    $report[] = ['device' => $device, 'result' => 'AT buyrug\'iga javob bermadi (xizmat porti)'];

                    continue;
                }
                $info = $at->info();
            } catch (Throwable $e) {
                $report[] = ['device' => $device, 'result' => 'Xato: '.$e->getMessage()];

                continue;
            } finally {
                $port->close();
            }

            if (! $info['imei']) {
                $report[] = ['device' => $device, 'result' => 'IMEI o\'qilmadi'];

                continue;
            }
            if (isset($seen[$info['imei']])) {
                $report[] = ['device' => $device, 'result' => 'Qo\'shimcha port (IMEI '.substr($info['imei'], -4).')'];

                continue;
            }
            $seen[$info['imei']] = true;

            $modem = $this->store($device, $info);
            $report[] = ['device' => $device, 'result' => "Modem: {$modem->name()} — {$modem->statusLabel()}"];
        }

        // Bu safar ko'rinmaganlar
        SmsModem::whereNotIn('imei', array_keys($seen) ?: [''])->where('status', '!=', 'offline')->update(['status' => 'offline', 'signal' => null]);

        Cache::put(self::REPORT_KEY, ['at' => now()->toDateTimeString(), 'rows' => $report], now()->addDay());

        return ['found' => count($seen), 'report' => $report];
    }

    /** @param array{imei:?string, imsi:?string, model:?string, operator:?string, signal:?int, sim:?string, registered:bool} $info */
    private function store(string $device, array $info): SmsModem
    {
        $sim = $info['sim'];
        $simOk = $sim !== null && strtoupper($sim) === 'READY';
        $status = ! $simOk ? 'sim_error' : (! $info['registered'] ? 'no_network' : 'ready');

        $modem = SmsModem::firstOrNew(['imei' => $info['imei']]);
        $modem->fill([
            'device' => $device, 'model' => $info['model'], 'imsi' => $info['imsi'], 'operator' => $info['operator'], 'signal' => $info['signal'],
            'sim_status' => $sim, 'registered' => $info['registered'], 'status' => $status, 'last_seen_at' => now(),
            'last_error' => $status === 'ready' ? null : ($status === 'sim_error' ? 'SIM: '.($sim ?? 'noma\'lum') : 'Tarmoqqa ulanmagan'),
        ]);
        if (! $modem->exists) {
            $modem->enabled = true;   // yangi modem avtomatik faollashadi
        }
        $modem->save();

        return $modem;
    }
}
