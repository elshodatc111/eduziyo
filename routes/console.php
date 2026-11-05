<?php

use App\Jobs\QueueHeartbeatJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Har kuni ertalab tug'ilgan kun tabriklari (php artisan schedule:work yoki serverda cron kerak)
Schedule::command('sms:birthdays')->dailyAt('09:00');

// v8 B1: qarzdorlarga avtomatik eslatma (faqat sms_auto_debt yoqilgan filiallarda)
Schedule::command('sms:debts')->dailyAt('09:15');

// v8: eskirgan bir martalik tokenlarni (ikki marta yuborishdan himoya) tozalash
Schedule::command('model:prune', ['--model' => [\App\Models\SubmissionToken::class]])->daily();

// v8 A3: har kuni tunda avtomatik zaxira nusxa (storage/app/backups, 14 kunlik saqlanadi)
Schedule::command('app:backup')->dailyAt('03:00');

// v8 A4: cron/rejalashtiruvchi ishlab turganini "Tizim holati" sahifasida ko'rsatish uchun signal
Schedule::call(fn () => Cache::put('system.cron_heartbeat', now(), now()->addHours(2)))->everyFiveMinutes();

// v10 (4-band): navbat ishchisi (queue worker) ishlab turganini bilish uchun signal - bu vazifa
// navbatga QO'YILADI, faqat `php artisan queue:work` uni bajarganda signal yangilanadi.
Schedule::job(new QueueHeartbeatJob())->everyFiveMinutes();

// EDUZIYO E0: markaz izolyatsiyasi auditi (center_id bo'sh/mos emas qatorlar) - muammo bo'lsa buyruq xato bilan tugaydi
Schedule::command('tenancy:audit')->dailyAt('03:30');

// EDUZIYO E7d: ommaviy demo markazni har tun boshlang'ich holatga qaytarish (faqat is_demo markaz)
Schedule::command('demo:reset')->dailyAt('03:10')->withoutOverlapping();

// EDUZIYO E4: markazlar tarif hayot sikli (eslatma, faqat ko'rish rejimi, muddati o'tganini o'chirish)
Schedule::command('centers:lifecycle')->hourly()->withoutOverlapping();

// EDUZIYO E8: kunlik shifrlangan baza zaxirasi Telegram orqali (03:00 dagi `app:backup` dan keyin), cheklar/fayllar - haftalik
Schedule::command('backup:telegram')->dailyAt('03:20')->withoutOverlapping();
Schedule::command('backup:telegram --files')->weeklyOn(0, '04:00')->withoutOverlapping();

// EDUZIYO: modem navbatidagi (olinmagan/xato) SMS'larni Eskiz'ga o'tkazish va eski SMS matnlarini tozalash
Schedule::command('sms:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('sms:prune-text')->dailyAt('04:00');
Schedule::command('support:tick')->hourly()->withoutOverlapping();

// EDUZIYO E3: jarayonlar nazorati (cron/navbat/modem/zaxira/disk) — o'zgarish bo'lsa sAdminlarga Telegram xabari.
// Cron o'zi to'xtasa, bu vazifa ham to'xtaydi; shuning uchun Docker'da `system:watch --loop` konteyneri alohida ishlaydi.
Schedule::command('system:watch')->everyFiveMinutes()->withoutOverlapping();

// EDUZIYO E3: botga ulangan ota-ona/o'quvchilarga qarz eslatmasi (Telegram, bepul)
Schedule::command('telegram:debts')->dailyAt('09:20')->withoutOverlapping();
