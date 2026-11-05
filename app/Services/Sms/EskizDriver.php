<?php

namespace App\Services\Sms;

use App\Contracts\SmsDriver;
use App\Models\SmsMessage;
use App\Services\SmsService;

/** Eskiz.uz kanali (umumiy platforma hisobi yoki filialning o'z akkaunti). */
class EskizDriver implements SmsDriver
{
    public function name(): string
    {
        return 'eskiz';
    }

    public function available(): bool
    {
        return true;
    }

    public function send(SmsMessage $sms): void
    {
        $sms->update(['channel' => 'eskiz']);
        app(SmsService::class)->deliverViaEskiz($sms);
    }
}
