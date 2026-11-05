<?php

namespace App\Contracts;

use App\Models\SmsMessage;

/** SMS yuborish kanali (USB modem yoki Eskiz). */
interface SmsDriver
{
    public function name(): string;

    /** Hozir ishlatish mumkinmi (modem uchun: gateway yaqinda "tirik" bo'lganmi). */
    public function available(): bool;

    /** Xabarni yuboradi yoki navbatga qo'yadi (holatni SmsMessage'da yangilaydi). */
    public function send(SmsMessage $sms): void;
}
