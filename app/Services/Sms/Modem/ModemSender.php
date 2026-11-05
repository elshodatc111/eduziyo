<?php

namespace App\Services\Sms\Modem;

use App\Models\SmsModem;
use Throwable;

/** Tanlangan modem orqali xabar yuboradi va modem statistikasini yangilaydi. */
class ModemSender
{
    public function __construct(private PortFactory $ports) {}

    /** @return array{ok:bool, error:?string} */
    public function send(SmsModem $modem, string $phone, string $text): array
    {
        try {
            $port = $this->ports->open((string) $modem->device);
        } catch (Throwable $e) {
            return $this->fail($modem, $e->getMessage());
        }

        try {
            $result = (new AtClient($port))->sendSms($phone, $text);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            $port->close();
        }

        if ($result['ok']) {
            $modem->update(['sent_count' => $modem->sent_count + 1, 'fail_streak' => 0, 'last_sent_at' => now(), 'last_seen_at' => now(), 'last_error' => null]);

            return $result;
        }

        return $this->fail($modem, (string) $result['error']);
    }

    /** @return array{ok:false, error:string} */
    private function fail(SmsModem $modem, string $error): array
    {
        $streak = $modem->fail_streak + 1;
        $modem->update([
            'failed_count' => $modem->failed_count + 1, 'fail_streak' => min(255, $streak), 'last_error' => mb_substr($error, 0, 250),
            // ketma-ket 3 xato — modem «Xato» holatiga o'tadi va keyingi skanerlashda qayta tekshiriladi
            'status' => $streak >= 3 ? 'error' : $modem->status,
        ]);

        return ['ok' => false, 'error' => $error];
    }
}
