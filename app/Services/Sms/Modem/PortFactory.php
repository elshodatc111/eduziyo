<?php

namespace App\Services\Sms\Modem;

use RuntimeException;

/** Serverdagi USB-serial qurilmalarni topadi va ochadi (/dev/ttyUSB*, /dev/ttyACM*). */
class PortFactory
{
    /** @return list<string> */
    public function candidates(): array
    {
        $found = \App\Support\IntegrationSettings::modemPorts();
        sort($found, SORT_NATURAL);

        return array_values(array_unique($found));
    }

    public function open(string $device): ModemPort
    {
        if (! file_exists($device)) {
            throw new RuntimeException("Qurilma topilmadi: {$device}");
        }
        if (! is_readable($device) || ! is_writable($device)) {
            throw new RuntimeException("Ruxsat yo'q: {$device} (foydalanuvchini `dialout` guruhiga qo'shing)");
        }

        return new SerialPort($device, max(1200, \App\Support\IntegrationSettings::int('modem_baud', 115200)));
    }
}
