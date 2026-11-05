<?php

namespace App\Services\Sms\Modem;

use RuntimeException;

/** /dev/ttyUSBx ni `stty` bilan sozlab, oddiy fayl oqimi sifatida ishlatadi (qo'shimcha PHP kengaytma kerak emas). */
class SerialPort implements ModemPort
{
    /** @var resource */
    private $fp;

    public function __construct(private string $device, int $baud = 115200)
    {
        exec('stty -F '.escapeshellarg($device).' '.(int) $baud.' raw -echo -echoe -echok -hupcl clocal cread cs8 -parenb -cstopb min 0 time 0 2>&1', $out, $rc);
        if ($rc !== 0) {
            throw new RuntimeException("stty xatosi ({$device}): ".implode(' ', $out));
        }

        $fp = @fopen($device, 'r+b');
        if (! $fp) {
            throw new RuntimeException("Portni ochib bo'lmadi: {$device}");
        }
        stream_set_blocking($fp, false);
        $this->fp = $fp;
    }

    public function write(string $data): void
    {
        $len = strlen($data);
        $written = 0;
        $deadline = microtime(true) + 5;
        while ($written < $len && microtime(true) < $deadline) {
            $n = @fwrite($this->fp, substr($data, $written));
            if ($n === false) {
                throw new RuntimeException('Portga yozib bo\'lmadi');
            }
            $written += $n;
            if ($n === 0) {
                usleep(10_000);
            }
        }
        @fflush($this->fp);
    }

    public function readUntil(string $pattern, float $timeout): string
    {
        $buf = '';
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $r = [$this->fp];
            $w = $e = null;
            $left = max(0.0, $deadline - microtime(true));
            if (@stream_select($r, $w, $e, (int) $left, (int) (fmod($left, 1) * 1_000_000)) > 0) {
                $chunk = fread($this->fp, 4096);
                if ($chunk !== false && $chunk !== '') {
                    $buf .= $chunk;
                    if (preg_match($pattern, $buf)) {
                        break;
                    }
                }
            }
        }

        return $buf;
    }

    public function close(): void
    {
        if (is_resource($this->fp)) {
            @fclose($this->fp);
        }
    }
}
