<?php

namespace App\Support;

/** SMS shablonlari: nomi, standart matni va standart holati. */
class SmsTemplates
{
    public const PLACEHOLDERS = [
        '{name}' => "Qabul qiluvchi ismi", '{branch}' => 'Filial nomi', '{amount}' => 'Summa', '{balance}' => 'Balans',
        '{debt}' => 'Qarz summasi', '{login}' => 'Login', '{password}' => 'Parol', '{group}' => 'Guruh nomi',
    ];

    /** Tizim xabarlari: filialga ko'rinmaydi, tarif limitiga sanalmaydi (sAdmin ularni ham tasdiqlaydi). */
    public const SYSTEM_KEYS = ['sms_limit_reached'];

    public static function system(): array
    {
        return [
            'sms_limit_reached' => [
                'label' => "Direktorga: tarif SMS limiti tugadi (avtomatik)", 'enabled' => true,
                'body' => "Hurmatli {name}, {center} markazining oylik SMS limiti tugadi. Yuborishni davom ettirish uchun CRM'da Eskiz akkauntingizni ulang yoki tarifni yangilang.",
            ],
        ];
    }

    public static function all(): array
    {
        return [
            'student_welcome' => [
                'label' => "Yangi o'quvchiga xush kelibsiz (login va parol bilan)", 'enabled' => true,
                'body' => "Hurmatli {name}, {branch} o'quv markaziga xush kelibsiz! Mobil ilova uchun login: {login}, parol: {password}.",
            ],
            'payment_received' => [
                'label' => "To'lov qabul qilindi", 'enabled' => true,
                'body' => "Hurmatli {name}, {amount} to'lovingiz qabul qilindi. Balansingiz: {balance}.",
            ],
            'discount_given' => [
                'label' => 'Chegirma berildi', 'enabled' => true,
                'body' => "Hurmatli {name}, sizga {amount} chegirma qo'llandi.",
            ],
            'refund_made' => [
                'label' => "To'lov qaytarildi", 'enabled' => true,
                'body' => "Hurmatli {name}, {amount} to'lovingiz qaytarildi.",
            ],
            'staff_paid' => [
                'label' => "Ish haqi to'landi (o'qituvchi va hodimga)", 'enabled' => false,
                'body' => "Hurmatli {name}, sizga {amount} ish haqi to'landi.",
            ],
            'password_reset' => [
                'label' => "O'quvchi paroli yangilandi", 'enabled' => false,
                'body' => "Hurmatli {name}, parolingiz yangilandi. Login: {login}, yangi parol: {password}.",
            ],
            'birthday' => [
                'label' => "Tug'ilgan kun tabrigi", 'enabled' => false,
                'body' => "Hurmatli {name}, {branch} jamoasi sizni tug'ilgan kuningiz bilan tabriklaydi! Ishlaringizga omad!",
            ],
            'debt_reminder' => [
                'label' => "Qarzdorlarga eslatma (ommaviy yuborishda va avtomatik)", 'enabled' => false,
                'body' => "Hurmatli {name}, balansingizda {debt} qarzdorlik mavjud. Iltimos, to'lovni amalga oshiring.",
            ],
            'absence_notice' => [
                'label' => "Darsga kelmaganlik xabari (avtomatik, ota-ona raqamiga)", 'enabled' => false,
                'body' => "Hurmatli ota-ona, {name} bugun {group} guruhidagi darsga kelmadi.",
            ],
        ];
    }
}
