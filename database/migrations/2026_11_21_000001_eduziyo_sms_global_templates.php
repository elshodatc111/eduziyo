<?php

use App\Models\SmsPlatformTemplate;
use App\Models\SmsTemplate;
use App\Support\SmsTemplates;
use Illuminate\Database\Migrations\Migration;

/**
 * SMS matnlari hamma uchun bir xil va faqat sAdmin tahrirlaydi:
 *  - barcha umumiy matnlar tasdiqlangan hisoblanadi (alohida tasdiqlash bosqichi yo'q; Eskiz'ga o'zi yuboriladi);
 *  - parol yuboradigan shablon («password_reset») o'chiriladi, matnida {password} bo'lganlari standart matnga qaytariladi;
 *  - filiallardagi eski matn nusxalari ishlatilmaydi (o'chiriladi), faqat yoqish/o'chirish holati qoladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        SmsPlatformTemplate::query()->where('key', 'password_reset')->delete();
        SmsTemplate::withoutGlobalScopes()->where('key', 'password_reset')->delete();

        $defaults = SmsTemplates::all() + SmsTemplates::system();
        foreach (SmsPlatformTemplate::query()->where('body', 'like', '%{password}%')->get() as $tpl) {
            if (isset($defaults[$tpl->key])) {
                $tpl->update(['body' => $defaults[$tpl->key]['body'], 'eskiz_status' => 'none', 'eskiz_template_id' => null, 'eskiz_error' => null]);
            } else {
                $tpl->delete();
            }
        }

        SmsPlatformTemplate::query()->where('is_approved', false)->update(['is_approved' => true, 'approved_at' => now()]);

        // filialning eski matn nusxalari yangi umumiy matnni bosib ketmasin: nusxani umumiy matnga tenglashtiramiz
        foreach (SmsPlatformTemplate::query()->get(['key', 'body']) as $tpl) {
            SmsTemplate::withoutGlobalScopes()->where('key', $tpl->key)->update(['body' => $tpl->body]);
        }
    }

    public function down(): void
    {
    }
};
