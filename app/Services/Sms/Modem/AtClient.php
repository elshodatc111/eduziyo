<?php

namespace App\Services\Sms\Modem;

/** AT buyruqlar bilan ishlash. */
class AtClient
{
    private const FINAL = '/(?:^|\r?\n)(OK|ERROR|\+CME ERROR:[^\r\n]*|\+CMS ERROR:[^\r\n]*|NO CARRIER|BUSY)\r?\n/';

    public function __construct(private ModemPort $port) {}

    /** @return array{ok:bool, lines:list<string>, raw:string, error:?string} */
    public function command(string $cmd, float $timeout = 3.0): array
    {
        $this->port->write($cmd."\r");
        $raw = $this->port->readUntil(self::FINAL, $timeout);

        $ok = (bool) preg_match('/(?:^|\r?\n)OK\r?\n/', $raw);
        $error = null;
        if (preg_match('/(ERROR|\+CME ERROR:[^\r\n]*|\+CMS ERROR:[^\r\n]*)/', $raw, $m)) {
            $error = trim($m[1]);
        } elseif (! $ok) {
            $error = $raw === '' ? 'Javob yo\'q (vaqt tugadi)' : 'Noaniq javob';
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw)), fn ($l) => $l !== '' && $l !== $cmd && $l !== 'OK'));

        return ['ok' => $ok, 'lines' => $lines, 'raw' => $raw, 'error' => $ok ? null : $error];
    }

    /** Modem AT ga javob beradimi. */
    public function alive(): bool
    {
        for ($i = 0; $i < 2; $i++) {
            if ($this->command('AT', 1.2)['ok']) {
                return true;
            }
        }

        return false;
    }

    /** @return array{imei:?string, imsi:?string, model:?string, operator:?string, signal:?int, sim:?string, registered:bool} */
    public function info(): array
    {
        $this->command('ATE0', 1.0);

        $imei = null;
        foreach ($this->command('AT+CGSN', 2)['lines'] as $l) {
            if (preg_match('/^\d{14,16}$/', $l)) {
                $imei = $l;
                break;
            }
        }

        $imsi = null;
        foreach ($this->command('AT+CIMI', 2)['lines'] as $l) {
            if (preg_match('/^\d{10,16}$/', $l)) {
                $imsi = $l;
                break;
            }
        }

        $model = null;
        foreach ($this->command('AT+CGMM', 2)['lines'] as $l) {
            if (! str_starts_with($l, '+') && $l !== 'ERROR') {
                $model = mb_substr($l, 0, 80);
                break;
            }
        }

        $sim = null;
        $cpin = $this->command('AT+CPIN?', 3);
        if (preg_match('/\+CPIN:\s*([^\r\n]+)/', $cpin['raw'], $m)) {
            $sim = trim($m[1]);
        } elseif (str_contains($cpin['raw'], 'SIM not inserted') || str_contains($cpin['raw'], 'CME ERROR: 10')) {
            $sim = 'NO SIM';
        }

        $signal = null;
        if (preg_match('/\+CSQ:\s*(\d+)/', $this->command('AT+CSQ', 2)['raw'], $m) && (int) $m[1] !== 99) {
            $signal = (int) round(min(31, (int) $m[1]) / 31 * 100);
        }

        $operator = null;
        $this->command('AT+COPS=3,0', 1.5);
        if (preg_match('/\+COPS:\s*\d+(?:,\d+,"([^"]*)")?/', $this->command('AT+COPS?', 4)['raw'], $m)) {
            $operator = ($m[1] ?? '') !== '' ? $m[1] : null;
        }

        $registered = (bool) preg_match('/\+CREG:\s*\d+,\s*(1|5)\b/', $this->command('AT+CREG?', 2)['raw']);

        return compact('imei', 'imsi', 'model', 'operator', 'signal', 'sim', 'registered');
    }

    /**
     * Bitta (yoki ko'p qismli) xabarni PDU rejimida yuboradi.
     *
     * @return array{ok:bool, error:?string}
     */
    public function sendSms(string $phone, string $text): array
    {
        if (! ($r = $this->command('AT+CMGF=0', 2))['ok']) {
            return ['ok' => false, 'error' => 'PDU rejimi yoqilmadi: '.$r['error']];
        }

        foreach (SmsPdu::encode($phone, $text) as [$pdu, $len]) {
            $this->port->write("AT+CMGS={$len}\r");
            $prompt = $this->port->readUntil('/>\s*$|ERROR/', 5);
            if (! str_contains($prompt, '>')) {
                return ['ok' => false, 'error' => 'Modem xabar qabul qilishga tayyor emas'];
            }

            $this->port->write($pdu.chr(26));
            $raw = $this->port->readUntil('/\+CMGS:\s*\d+[\s\S]*OK\r?\n|ERROR/', 45);
            if (! preg_match('/\+CMGS:\s*\d+/', $raw) || ! preg_match('/OK\r?\n/', $raw)) {
                preg_match('/(\+CMS ERROR:[^\r\n]*|\+CME ERROR:[^\r\n]*|ERROR)/', $raw, $m);

                return ['ok' => false, 'error' => $m[1] ?? 'Yuborish vaqti tugadi'];
            }
        }

        return ['ok' => true, 'error' => null];
    }

    public function close(): void
    {
        $this->port->close();
    }
}
