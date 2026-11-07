<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Throwable;

/**
 * Tizim voqealari uchun SMS: filialda SMS yoqilgan va shablon faol bo'lsagina yuboriladi.
 * Hech qachon asosiy jarayonni to'xtatmaydi.
 */
class SmsNotifier
{
    public function __construct(private SmsService $sms) {}

    /**
     * $phoneOverride berilsa, xabar $recipient (yozuv/hisobot uchun) emas, shu raqamga yuboriladi
     * (masalan v8 B1: darsga kelmaganlik xabari o'quvchiga emas, ota-ona raqamiga — $recipient->phone2).
     */
    public function notify(string $key, User $recipient, array $vars = [], ?string $phoneOverride = null): void
    {
        try {
            $branch = Branch::find($recipient->branch_id);
            $phone = $phoneOverride ?? $recipient->phone;

            if (! $branch || ! $branch->sms_enabled || blank($phone)) {
                return;
            }

            unset($vars['password']);   // parol SMS orqali hech qachon yuborilmaydi

            $template = $this->sms->template($branch, $key);

            if (! $template['enabled']) {
                return;
            }

            $allVars = ['name' => $recipient->name, 'branch' => $branch->name] + $vars;
            $text = $this->sms->render($template['body'], $allVars);

            $this->sms->queue($branch, $phone, $text, $key, $recipient, auth()->user());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
