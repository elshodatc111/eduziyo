<?php

namespace App\Services\Sms;

use App\Contracts\SmsDriver;
use App\Models\SmsMessage;
use App\Models\SmsOutbox;
use App\Models\SmsModem;
use App\Services\Sms\Modem\ModemWorker;

/**
 * USB modem kanali: xabar `sms_outbox` ga qo'yiladi, `sms:modem-run` ishchisi (shu serverda) tayyor modem orqali yuboradi.
 * Kanal faqat ishchi tirik va kamida bitta tayyor modem bo'lsa mavjud; aks holda xabar to'g'ridan-to'g'ri Eskiz'ga ketadi.
 */
class ModemDriver implements SmsDriver
{
    public function name(): string
    {
        return 'modem';
    }

    public function available(): bool
    {
        return ModemWorker::alive() && SmsModem::usable()->exists();
    }

    public function send(SmsMessage $sms): void
    {
        SmsOutbox::updateOrCreate(['sms_id' => $sms->id], ['status' => 'queued', 'claimed_at' => null, 'error' => null]);
        $sms->update(['channel' => 'modem', 'provider_response' => 'Modem navbatida']);
    }
}
