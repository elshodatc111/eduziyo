<?php

namespace App\Support;

/** Dastlabki hujjat qoralamalari. E'lon qilishdan oldin yurist ko'rib chiqishi shart. */
class LegalDefaults
{
    public const NOTE = "> **Qoralama.** Bu matn namunaviy. E'lon qilishdan oldin yurist ko'rib chiqsin, kerakli joylarni (tashkilot nomi, rekvizitlar) to'ldiring va shu qatorni o'chiring.\n\n";

    public static function all(): array
    {
        $name = '{platforma}';

        return [
            [
                'type' => 'offer', 'audience' => 'owner', 'version' => 1, 'status' => 'draft', 'requires_reacceptance' => true,
                'title' => 'Ommaviy oferta (xizmat ko\'rsatish shartnomasi)',
                'body' => self::NOTE."## 1. Umumiy qoidalar\n\nUshbu oferta {$name} platformasidan foydalanish shartlarini belgilaydi. Platformaga kirib, «Roziman» tugmasini bosish ofertani to'liq qabul qilishni anglatadi.\n\n## 2. Xizmat predmeti\n\nPlatforma o'quv markazlariga o'quvchilar, guruhlar, to'lovlar, davomat va hisobotlarni boshqarish imkonini beradi.\n\n## 3. To'lov va tariflar\n\nXizmat haqi tanlangan tarif bo'yicha markaz balansidan yechiladi. Balans yetarli bo'lmasa, tizim faqat o'qish rejimiga o'tadi.\n\n## 4. Tomonlarning mas'uliyati\n\nMarkaz kiritilgan ma'lumotlarning to'g'riligi va o'quvchilardan zarur roziliklarni olish uchun javobgar. Platforma ma'lumotlar xavfsizligini ta'minlash uchun oqilona choralar ko'radi.\n\n## 5. Shartnomani to'xtatish\n\nMarkaz istalgan vaqtda xizmatdan voz kechishi mumkin. Markaz o'chirilganda uning ma'lumotlari belgilangan muddatdan keyin butunlay o'chiriladi.\n\n## 6. Rekvizitlar\n\nTashkilot nomi, STIR, manzil, bog'lanish ma'lumotlari — to'ldirilsin.",
            ],
            [
                'type' => 'privacy', 'audience' => 'all', 'version' => 1, 'status' => 'draft', 'requires_reacceptance' => true,
                'title' => 'Maxfiylik siyosati (shaxsiy ma\'lumotlarni qayta ishlash)',
                'body' => self::NOTE."## 1. Qanday ma'lumotlar yig'iladi\n\nIsm-familiya, telefon raqami, manzil, tug'ilgan sana, o'qish va to'lov ma'lumotlari, tizimga kirish ma'lumotlari (IP, qurilma).\n\n## 2. Maqsad\n\nO'quv jarayonini yuritish, to'lovlarni hisobga olish, SMS va Telegram orqali xabar berish, xavfsizlik.\n\n## 3. Saqlash va himoya\n\nMa'lumotlar O'zbekiston Respublikasi qonunchiligiga muvofiq saqlanadi, faqat vakolatli xodimlarga o'z markazi doirasida ko'rinadi.\n\n## 4. Uchinchi shaxslarga berish\n\nSMS yuborish va xabarnoma xizmatlariga faqat xabar yetkazish uchun zarur minimal ma'lumot beriladi. Boshqa maqsadlarda sotilmaydi va berilmaydi.\n\n## 5. Sizning huquqlaringiz\n\nMa'lumotlaringizni ko'rish, tuzatish va o'chirishni markaz ma'muriyatidan so'rashingiz mumkin. 18 yoshgacha bo'lganlar uchun rozilikni ota-ona yoki qonuniy vakil beradi.\n\n## 6. Bog'lanish\n\nSavollar uchun markaz ma'muriyatiga yoki platforma qo'llab-quvvatlashiga murojaat qiling.",
            ],
            [
                'type' => 'terms', 'audience' => 'all', 'version' => 1, 'status' => 'draft', 'requires_reacceptance' => true,
                'title' => 'Foydalanish shartlari',
                'body' => self::NOTE."## 1. Hisob xavfsizligi\n\nLogin va parolni uchinchi shaxslarga bermang. Hisobingizdan qilingan amallar uchun o'zingiz javobgarsiz.\n\n## 2. Ruxsat etilmagan harakatlar\n\nTizimni buzishga urinish, boshqa markaz ma'lumotlariga kirishga harakat qilish, noqonuniy kontent kiritish taqiqlanadi.\n\n## 3. Xizmat uzilishlari\n\nTexnik ishlar tufayli qisqa uzilishlar bo'lishi mumkin.\n\n## 4. Shartlarni o'zgartirish\n\nShartlar o'zgarsa, yangi versiya e'lon qilinadi va qayta rozilik so'rashi mumkin.",
            ],
        ];
    }
}
