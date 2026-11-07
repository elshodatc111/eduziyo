<?php

namespace App\Services\Sms;

use App\Models\SmsPlatformTemplate;
use App\Support\SmsTemplates;

/**
 * Umumiy SMS matnlari: FAQAT sAdmin tahrirlaydi, hamma filial va markaz uchun bir xil (filial/markaz o'zgartira olmaydi).
 * Matnlar avtomatik tasdiqlangan hisoblanadi va Eskiz moderatsiyasiga o'zi yuboriladi (akkaunt kiritilganda/o'zgarganda ham).
 * Eskiz (platforma yoki markaz akkaunti) FAQAT shu matnlarga mos xabarni yuboradi.
 * Filial standart matni ham shu yerdan olinadi (o'zgartirmagan filiallar tasdiqlangan matnni ishlatadi).
 */
class SmsPlatformTemplates
{
    /** Bazada yo'q standart shablonlarni yaratadi (tasdiqlanmagan holda). */
    public static function ensureSeeded(): void
    {
        foreach (SmsTemplates::all() + SmsTemplates::system() as $key => $d) {
            SmsPlatformTemplate::firstOrCreate(['key' => $key], [
                'label' => $d['label'], 'body' => $d['body'], 'is_system' => in_array($key, SmsTemplates::SYSTEM_KEYS, true),
                'is_approved' => true, 'approved_at' => now(),
            ]);
        }
    }

    public static function find(string $key): ?SmsPlatformTemplate
    {
        self::ensureSeeded();

        return SmsPlatformTemplate::where('key', $key)->first();
    }

    /** Umumiy matn (sAdmin o'zgartirgan bo'lsa — o'sha). */
    public static function body(string $key): ?string
    {
        return SmsPlatformTemplate::where('key', $key)->value('body') ?? (SmsTemplates::all() + SmsTemplates::system())[$key]['body'] ?? null;
    }

    public static function hash(string $body): string
    {
        return hash('sha256', $body);
    }

    /** Berilgan kalit va tayyor matn tasdiqlangan shablonga mosmi (o'zgaruvchilar o'rniga istalgan qiymat). */
    public static function allows(?string $key, string $text): bool
    {
        if (! $key) {
            return false;
        }
        $tpl = SmsPlatformTemplate::where('key', $key)->where('is_approved', true)->first();
        if (! $tpl) {
            return false;
        }

        $parts = preg_split('/(\{[a-z_]+\})/', $tpl->body, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $re = '';
        foreach ($parts as $p) {
            $re .= preg_match('/^\{[a-z_]+\}$/', $p) ? '.{0,200}?' : preg_quote($p, '/');
        }

        return (bool) preg_match('/^'.$re.'$/su', trim($text));
    }

    /** Ruxsat etilgan o'zgaruvchilar: filial shablonlari + tizim matni uchun {center}. */
    public static function allowedPlaceholders(): array
    {
        return array_merge(array_keys(SmsTemplates::PLACEHOLDERS), ['{center}']);
    }
}
