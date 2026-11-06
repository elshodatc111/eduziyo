<?php

return [

    // Eskiz.uz SMS xizmati. Platforma hisobi sAdmin «Integratsiyalar» sahifasida saqlanadi (.env — faqat zaxira qiymat);
    // har bir markaz o'z akkauntini «Markaz → SMS» bo'limida ulaydi.
    'eskiz' => [
        'base_url' => env('ESKIZ_BASE_URL', 'https://notify.eskiz.uz/api'),
        'email' => env('ESKIZ_EMAIL'),
        'password' => env('ESKIZ_PASSWORD'),
        'from' => env('ESKIZ_FROM', '4546'),
    ],

    // OpenAI (AI yordamchi). Kalit sAdmin «Integratsiyalar» sahifasida saqlanadi (.env — faqat zaxira qiymat).
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'daily_limit' => (int) env('OPENAI_DAILY_LIMIT', 200),
    ],

    // SMS Gateway (o'z serverimiz, sms.elshod.date). Kalit va webhook siri sAdmin «Integratsiyalar» sahifasida saqlanadi.
    'sms_gateway' => [
        'url' => env('SMS_GATEWAY_URL', 'https://sms.elshod.date'),
        'key' => env('SMS_GATEWAY_KEY'),
        'webhook_secret' => env('SMS_GATEWAY_WEBHOOK_SECRET'),
    ],

    // Claude (Anthropic) — OpenAI ishlamay qolganda zaxira (yoki asosiy) AI. Kalit sAdmin «Integratsiyalar» sahifasida saqlanadi;
    // .env dagi qiymat faqat sahifada hech narsa saqlanmagan bo'lsa ishlatiladi.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
    ],

    // v11: Firebase Cloud Messaging (mobil ilova push-bildirishnomasi). Xizmat hisobi
    // JSON kaliti faqat serverda saqlanadi, hech qachon zip/git'ga kirmaydi.
    'fcm' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-service-account.json')),
    ],

    // EDUZIYO E8: Telegram bot (webhook). Token va maxfiy sarlavha faqat .env da.
    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    // Zaxira nusxa shifrlash: serverda faqat OCHIQ kalit turadi; yopiq kalit sAdmin'da (serverda emas).
    'backup' => [
        'public_key_path' => env('BACKUP_PUBLIC_KEY_PATH', storage_path('app/backup-public.pem')),
    ],

];
