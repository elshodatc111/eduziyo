<?php

namespace Tests\Support;

use App\Services\Sms\Modem\ModemPort;
use App\Services\Sms\Modem\PortFactory;
use RuntimeException;

/** Soxta USB modemlar: har qurilma uchun AT javoblari skripti. */
class FakeModemPorts extends PortFactory
{
    /** @var array<string, array> device => ['imei'=>..., 'at'=>bool, 'sim'=>'READY', 'reg'=>1, 'csq'=>20, 'send_ok'=>bool, 'denied'=>bool] */
    public array $devices = [];

    /** @var list<array{device:string, phone_pdu:string}> */
    public static array $sent = [];

    public function candidates(): array
    {
        return array_keys($this->devices);
    }

    public function open(string $device): ModemPort
    {
        $cfg = $this->devices[$device];
        if (! empty($cfg['denied'])) {
            throw new RuntimeException("Ruxsat yo'q: {$device} (foydalanuvchini `dialout` guruhiga qo'shing)");
        }

        return new FakePort($device, $cfg);
    }

    public static function modem(string $imei, array $over = []): array
    {
        return array_merge(['imei' => $imei, 'at' => true, 'sim' => 'READY', 'reg' => 1, 'csq' => 20, 'send_ok' => true, 'operator' => 'Ucell', 'model' => 'E3372'], $over);
    }
}

class FakePort implements ModemPort
{
    private string $out = '';

    private bool $awaitPdu = false;

    public function __construct(private string $device, private array $cfg) {}

    public function write(string $data): void
    {
        if ($this->awaitPdu) {
            $this->awaitPdu = false;
            FakeModemPorts::$sent[] = ['device' => $this->device, 'pdu' => rtrim($data, chr(26))];
            $this->out .= $this->cfg['send_ok'] ? "\r\n+CMGS: 12\r\n\r\nOK\r\n" : "\r\n+CMS ERROR: 500\r\n";

            return;
        }

        $cmd = trim($data);
        if (! $this->cfg['at']) {
            return;   // javob yo'q
        }
        $c = $this->cfg;
        $this->out .= match (true) {
            $cmd === 'AT', $cmd === 'ATE0', $cmd === 'AT+CMGF=0', str_starts_with($cmd, 'AT+COPS=') => "\r\nOK\r\n",
            $cmd === 'AT+CGSN' => "\r\n{$c['imei']}\r\n\r\nOK\r\n",
            $cmd === 'AT+CIMI' => "\r\n434050000000001\r\n\r\nOK\r\n",
            $cmd === 'AT+CGMM' => "\r\n{$c['model']}\r\n\r\nOK\r\n",
            $cmd === 'AT+CPIN?' => "\r\n+CPIN: {$c['sim']}\r\n\r\nOK\r\n",
            $cmd === 'AT+CSQ' => "\r\n+CSQ: {$c['csq']},99\r\n\r\nOK\r\n",
            $cmd === 'AT+COPS?' => "\r\n+COPS: 0,0,\"{$c['operator']}\",7\r\n\r\nOK\r\n",
            $cmd === 'AT+CREG?' => "\r\n+CREG: 0,{$c['reg']}\r\n\r\nOK\r\n",
            str_starts_with($cmd, 'AT+CMGS=') => $this->prompt(),
            default => "\r\nERROR\r\n",
        };
    }

    private function prompt(): string
    {
        $this->awaitPdu = true;

        return "\r\n> ";
    }

    public function readUntil(string $pattern, float $timeout): string
    {
        $r = $this->out;
        $this->out = '';

        return $r;
    }

    public function close(): void {}
}
