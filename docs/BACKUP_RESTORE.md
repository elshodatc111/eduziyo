# Zaxira va tiklash (EDUZIYO)

## Qanday ishlaydi
- Har kuni 03:00 `app:backup` baza nusxasini `storage/app/backups` ga yozadi (oxirgi 14 ta).
- Har kuni 03:20 `backup:telegram` shu nusxani **shifrlab**, Telegram bot orqali ulangan **sAdmin(lar)ga** yuboradi (45 MB dan kattasi `.part001, .part002…` bo'laklarga bo'linadi). Har yakshanba 04:00 da cheklar va yuklangan fayllar (`--files`) yuboriladi.
- Shifrlash: har zaxira uchun tasodifiy AES-256-GCM kaliti, u **ochiq RSA kalit** bilan o'raladi. Serverda faqat **ochiq** kalit turadi — serverga kirilsa ham zaxirani ochib bo'lmaydi. Telegram ham mazmunni ko'rmaydi.
- Zaxira bajarilmasa (kalit yo'q, bot ulanmagan, yuborib bo'lmadi) sAdmin'ga ogohlantirish keladi. Shifrlanmagan hech narsa yuborilmaydi.

## Birinchi sozlash (bir marta)
1. **O'z kompyuteringizda** (yoki serverda vaqtincha): `php artisan backup:keygen --dir=/tmp/kalit`
2. `backup-public.pem` ni serverda `storage/app/backup-public.pem` ga qo'ying (yoki `.env` da `BACKUP_PUBLIC_KEY_PATH`).
3. `backup-private.pem` ni **xavfsiz joyga** (parol menejer, flesh) ko'chiring va serverdan **o'chiring**. Yo'qolsa zaxiralarni ochib bo'lmaydi; ikkita nusxa saqlang.
4. `.env`: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET`; so'ng `php artisan telegram:webhook`.
5. sAdmin profilida «Telegramni ulash» → botda «Start». Tekshirish: `php artisan backup:telegram --files`.

## Tiklash
1. Telegram'dan fayl(lar)ni yuklab oling. Bo'laklar bo'lsa birlashtiring:
   - Linux/macOS: `cat backup_2026-11-12_030000.sql.gz.enc.part* > backup.sql.gz.enc`
   - Windows (cmd): `copy /b backup_..part001+backup_..part002 backup.sql.gz.enc`
2. Ochish (PHP + OpenSSL bor har qanday kompyuterda): `php bin/backup-decrypt.php backup.sql.gz.enc backup-private.pem backup.sql.gz`
   - «Fayl buzilgan» xabari — fayl to'liq yuklanmagan yoki o'zgartirilgan.
3. Bazaga yuklash (MariaDB): `gunzip < backup.sql.gz | mysql -u USER -p DB_NOMI`
4. Fayllar zaxirasi (`files_*.zip.enc`) ham xuddi shunday ochiladi; zip ichidagi `app/topups` va `app/public` ni `storage/` ga qaytaring.
5. `php artisan migrate --force` va `php artisan optimize:clear`.

## Tiklashni sinab turing
Kamida oyda bir marta zaxirani **alohida** bazaga tiklab ko'ring: sinalmagan zaxira zaxira emas.
