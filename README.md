# EDUZIYO

Ko'p markazli (multi-tenant) o'quv markazlari boshqaruv platformasi.
**Laravel 12 · PHP 8.3 · MySQL/MariaDB · Blade + Tailwind + Alpine.js · Telegram bot · USB modem SMS**

Bitta o'rnatishda ko'p o'quv markazi ishlaydi; har markazning o'z filiallari, xodimlari, o'quvchilari va puli bor va ular bir-birini ko'rmaydi.
Platformani egasi (**sAdmin**) boshqaradi: markazlar, tariflar, to'lovlar, murojaatlar, integratsiyalar.

> Versiya tarixi: [`CHANGELOG.md`](CHANGELOG.md) · Joylashtirish: [`docs/DEPLOY.md`](docs/DEPLOY.md) ·
> Zaxira: [`docs/BACKUP_RESTORE.md`](docs/BACKUP_RESTORE.md) · USB modem: [`docs/SMS_MODEM.md`](docs/SMS_MODEM.md)

---

## Mundarija

1. [Imkoniyatlar](#1-imkoniyatlar)
2. [Rollar](#2-rollar)
3. [Arxitektura qoidalari](#3-arxitektura-qoidalari)
4. [Lokal o'rnatish](#4-lokal-ornatish-windows--xampp)
5. [Fon jarayonlari va ogohlantirishlar](#5-fon-jarayonlari-va-ogohlantirishlar)
6. [Telegram bot](#6-telegram-bot)
7. [Integratsiyalar (sozlamalar sahifasi)](#7-integratsiyalar)
8. [Testlar va sifat nazorati](#8-testlar-va-sifat-nazorati)
9. [Tezlik: N+1 va indekslar](#9-tezlik-n1-va-indekslar)
10. [Server: Docker, GitHub, Cloudflare](#10-server-docker-github-cloudflare)
11. [Yangilash](#11-yangilash)
12. [Loyiha tuzilmasi](#12-loyiha-tuzilmasi)
13. [Xavfsizlik](#13-xavfsizlik)
14. [Ma'lum cheklovlar va reja](#14-malum-cheklovlar-va-reja)

---

## 1. Imkoniyatlar

**Markaz ichida:** filiallar, xodimlar va ruxsatlar, o'quvchilar, guruhlar va jadval, davomat, to'lovlar (ikki bosqichli tasdiqlash, storno, chegirma/aksiya),
kassa va smena, moliya va xarajatlar, o'qituvchi/xodim ish haqi, lid-varonka, kurslar (video/audio/test), SMS (USB modem + Eskiz zaxirasi),
statistika va hisobotlar, Excel import/eksport, AI yordamchi, o'quvchi veb-kabineti (PWA).

**Platforma darajasida (sAdmin):** markazlar konsoli va tahlil, tariflar va obuna, markaz balansi va to'ldirish (chek bilan), limitlar,
hayot sikli (faqat ko'rish → o'chirish), sotuv sahifasi va ommaviy demo, yangiliklar/videolar, murojaat (tiket) va SLA,
huquqiy hujjatlar va majburiy rozilik, tizim holati, **Integratsiyalar** sahifasi, Telegram orqali shifrlangan zaxira.

## 2. Rollar

| Rol | Ko'lami |
|---|---|
| **sAdmin** | Platforma egasi: barcha markaz va filiallar, barcha ruxsatlar |
| **Direktor** | O'z markazi: barcha filiallar, tarif/balans, murojaat, markaz sozlamalari |
| **Admin / Menejer / Operator / O'qituvchi** | Faqat o'z filiali; ruxsatlar `config/permissions.php` bo'yicha |
| **O'quvchi** | Veb-kabinet `/cabinet` va Telegram bot (ota-ona ham) |

Admin ruxsatlarini faqat sAdmin belgilaydi; admin boshqalarga faqat o'zida borini bera oladi.

## 3. Arxitektura qoidalari

Quyidagilar buzilmaydi (testlar va audit shuni tekshiradi):

- **Markaz/filial izolyatsiyasi.** Markazga tegishli har jadvalda `center_id`, filialga tegishlisida `branch_id`; modellarda `BelongsToCenter` / `BelongsToBranch`
  (avtomatik global filtr, `CenterScope`). `User` modelida global filtr **yo'q** — ro'yxatlarda `User::visibleToContext()` yoki aniq `center_id` filtri ishlatiladi.
  Boshqa markaz/filial yozuvi **404** beradi.
- **Pul faqat servislar orqali** (`BalanceService`, `WalletService`, `PaymentService`, `CashboxService`, ...): qulflangan tranzaksiyada, jurnalga yozilib.
  Balans/kassa qo'lda o'zgartirilmaydi; kassa manfiyga tushmaydi.
- **Ruxsatlar:** yangi ruxsat `config/permissions.php` ga qo'shiladi va `resources/ai/knowledge.md` da tasvirlanadi.
- **Migratsiyalar faqat additive**: yangi fayl qo'shiladi, eskilari tahrirlanmaydi.
- **Telefon:** faqat `+998 91 555 6677` (`Format::canonicalPhone`, `UzPhone`); summa maydonlari `x-input` (money) bilan.
- **Sirlar** (`.env`, tokenlar, parollar) kodda, logda, zip/repoda bo'lmaydi. Integratsiya kalitlari bazada shifrlanib saqlanadi.
- **AI (OpenAI):** ism va telefon yuborilmaydi; faqat ruxsat va markaz doirasidagi hisoblangan ma'lumot.

### Markazlar aralashib ketmasligi uchun ikki qatlamli nazorat

| Buyruq | Nimani tekshiradi | Qachon |
|---|---|---|
| `php artisan tenancy:scan` | **Kod**: xom SQL (`DB::table`, `DB::select`...), `withoutGlobalScopes()` va shunga o'xshash joylar soni `database/tenancy-baseline.json` dan oshmasin | har deploy'da (`deploy.sh` to'xtatadi) va testda |
| `php artisan tenancy:audit` | **Ma'lumot**: bazadagi markazlararo noto'g'ri bog'lanishlar | har kuni 03:30 va deploy'dan keyin |

Yangi istisno ongli ravishda kerak bo'lsa: `php artisan tenancy:scan --update` va diffni ko'rib chiqing.
`TenantLeakCanaryTest` — ikki markazli sinov: har asosiy sahifa va ro'yxatda boshqa markaz yozuvi ko'rinmasligini tekshiradi.

## 4. Lokal o'rnatish (Windows + XAMPP)

Kerak: PHP 8.2+ (kengaytmalar: mbstring, openssl, pdo_mysql, fileinfo, curl, zip, gd, intl; testlar uchun pdo_sqlite), Composer 2, MySQL/MariaDB, Node 20+ (faqat dizayn/build uchun).

```bat
cd C:\xampp\htdocs\EDUZIYO
composer install
copy .env.example .env
php artisan key:generate
```

`.env` da: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `SADMIN_USERNAME`, `SADMIN_PASSWORD`. phpMyAdmin'da bazani `utf8mb4_unicode_ci` bilan yarating.

```bat
php artisan migrate
php artisan db:seed            REM birinchi sAdmin
php artisan storage:link
npm install && npm run build   REM faqat dizayn o'zgargan bo'lsa
php artisan serve              REM http://127.0.0.1:8000
```

Fon jarayonlari uchun alohida CMD oynalarida (lokal sinov): `php artisan queue:work`, `php artisan schedule:work`.
Sinov ma'lumotlari: `php artisan db:seed --class=DemoSeeder` (**haqiqiy serverda ishlatmang**).

## 5. Fon jarayonlari va ogohlantirishlar

Tizim 3 ta alohida jarayonga tayanadi; biri to'xtasa SMS, zaxira va tarif muddati jim qoladi — shuning uchun **watchdog** bor.

| Jarayon | Buyruq | Vazifasi |
|---|---|---|
| Rejalashtiruvchi | `schedule:work` (yoki har daqiqa `schedule:run`) | SMS yuborish, zaxira, tarif muddati, audit, eslatmalar |
| Navbat | `queue:work` | Fon vazifalari |
| Modem ishchisi | `sms:modem-run` | USB modem orqali SMS (ixtiyoriy) |
| **Watchdog** | `system:watch --loop` | Yuqoridagilarni tekshiradi, to'xtasa **Telegram**'ga xabar beradi |

Watchdog nimalarni kuzatadi: cron yurak urishi, navbat yurak urishi (va ishlamay qolgan vazifalar), modem ishchisi, disk, oxirgi zaxira.
Holat o'zgargandagina xabar yuboradi (takrorlamaydi) va tiklanganda ham xabar beradi. Qo'shimcha: `system:watch` har 5 daqiqada cron orqali ham ishlaydi
(cron o'zi to'xtasa, `--loop` rejimidagi alohida jarayon xabar beradi).

Xabarlar **botga ulangan barcha sAdminlarga** boradi (qo'shimcha chatlar — Integratsiyalar sahifasidagi «Ogohlantirish chatlari»). Shuningdek:
markaz **to'lov chekini** yuborganda va **murojaat** ochilganda/javob berilganda sAdminga Telegram xabari keladi (sahifaga havola bilan).
Holat sAdmin → «Tizim holati» sahifasida ham ko'rinadi.

## 6. Telegram bot

Bitta bot, rolga qarab ishlaydi (token va webhook — sAdmin → Integratsiyalar).

- **Xodim/direktor/sAdmin:** parolni faqat bot kodi orqali tiklash, ariza va kirish, tizim xabarlari, `/holat`.
- **O'quvchi:** `/jadval` (dars jadvali), `/guruhlar` (guruhlari: o'qituvchi, kunlar, vaqt, xona), `/qarz` yoki `/balans` (balans va qarz), `/davomat` (so'nggi davomat).
- **Ota-ona / o'quvchi ulanishi:** botda `/ulanish` → telefon raqamini ulashadi; raqam o'quvchining 2-raqami (ota-ona) bo'lsa — ota-ona, o'quvchining o'z raqami bo'lsa — o'quvchi sifatida ulanadi (kabinetdagi «Telegram» kartasida bot havolasi bor); farzandining (bir nechta bo'lsa — har biri) jadvali, guruhlari,
  davomati va qarzini ko'radi. Guruh ma'lumoti ota-ona va o'quvchi uchun **bir xil** manbadan olinadi.
- **Avtomatik xabarlar:** darsga kelmaganda davomat xabari; to'lov qabul qilinganda tasdiq; har kuni 09:20 da (`telegram:debts`) qarz eslatmasi
  (qarzdorga va ulangan ota-onasiga, kuniga bir marta).
- Xabarlarni sozlash: `/bildirishnoma davomat|qarz|tolov on|off`; ulanishni uzish: `/chiqish`.

Maxfiylik: Telegram'ga faqat kerakli matn yuboriladi; webhook maxfiy sarlavha bilan himoyalangan va tezlik chegarasiga ega.

## 7. Integratsiyalar

sAdmin → **Integratsiyalar** sahifasi `.env` o'rniga ishlatiladi (sahifadagi qiymat `.env` dan ustun; bo'sh qoldirilsa avvalgisi saqlanadi, sirlar shifrlanadi va to'liq ko'rsatilmaydi):
Telegram bot tokeni (Telegram'da tekshiriladi), webhook o'rnatish/holat/olib tashlash, ogohlantirish chatlari, USB modem portlari, tezlik,
xabarlar orasidagi tasodifiy pauza, SIM kunlik chegarasi (oshsa SMS Eskiz orqali ketadi). OpenAI va Eskiz kalitlari hozircha `.env` da.

## 8. Testlar va sifat nazorati

```bat
php vendor/phpunit/phpunit/phpunit                          REM SQLite (xotirada)
php vendor/phpunit/phpunit/phpunit -c phpunit.mariadb.xml   REM MariaDB (qat'iy ONLY_FULL_GROUP_BY)
php artisan tenancy:scan
php artisan tenancy:audit
```

Muhim to'plamlar: `TenancyMetaTest` (center_id bor jadval — scope'li model), `TenantLeakCanaryTest` (markazlararo oqish),
`QueryBudgetTest` (asosiy ro'yxat sahifalarida so'rovlar soni sahifadagi qator soniga bog'liq emas), `CenterIndexesTest`,
`SystemWatchTest`, `TelegramFamilyTest`, `CenterBillingTest`, ... Har o'zgarishda to'liq to'plam o'tishi shart.

## 9. Tezlik: N+1 va indekslar

- `QueryBudgetTest` har asosiy ro'yxat sahifasiga ikki xil hajmda ma'lumot beradi va so'rovlar soni o'smasligini tekshiradi
  (yangi sahifani `PAGES` ro'yxatiga qo'shing). `Model::preventLazyLoading` ishlab chiqish muhitida yoqilgan.
- Ish haqi (`PayrollService::teacherAccrualsMany`) va platforma tahlili (`SubscriptionService::limitsMany`) bitta guruhli so'rovlarga o'tkazilgan.
- `2026_11_17_000001_eduziyo_center_indexes` markaz bo'yicha filtrlanadigan ro'yxatlar uchun qo'shma indekslarni qo'shadi (additive; indeks bor bo'lsa qayta yaratilmaydi).
- Yangi ro'yxat sahifasi qo'shganda: `with()` bilan bog'lanishlarni oldindan yuklang, filtr ustunlariga indeks qo'shing, testni `PAGES` ga kiriting.

## 10. Server: Docker, GitHub, Cloudflare

Hammasi [`docs/DEPLOY.md`](docs/DEPLOY.md) da bosqichma-bosqich. Qisqacha:

- **GitHub:** kompyuterdan SSH bilan `git push`; serverda **alohida deploy kaliti** va `github-eduziyo` alias — boshqa loyihalar kalitlariga tegmaydi.
- **Docker:** `deploy/docker-compose.yml` (loyiha nomi `eduziyo`): `app` (php-fpm), `web` (nginx, `127.0.0.1:7005`), `queue`, `scheduler`, `watchdog`, ixtiyoriy `modem`.
  Alohida baza/foydalanuvchi (`deploy/db-create.sh`), xotira/CPU chegaralari, loglar aylantiriladi.
- **Cloudflare Tunnel:** mavjud tunnelga bitta yangi marshrut — `educrm.elshod.date` → `http://localhost:7005`. Eski marshrutlar o'zgarmaydi.
- **Yangilash:** serverda `./deploy/deploy.sh` (git pull → build → `tenancy:scan` → migrate → qayta ishga tushirish → `tenancy:audit`); orqaga qaytarish: `--rollback`.

## 11. Yangilash

Lokal/oddiy server: yangi fayllarni ustiga yozing (`.env` va `storage/` tegilmaydi), so'ng

```bat
composer install --no-dev
php artisan migrate --force
php artisan optimize:clear
```

Docker: `./deploy/deploy.sh`. Migratsiyalar faqat qo'shimcha — mavjud ma'lumot buzilmaydi. Har versiyaning o'ziga xos eslatmalari `CHANGELOG.md` da.

## 12. Loyiha tuzilmasi

```
app/Console/Commands   system:watch, telegram:debts, tenancy:audit, tenancy:scan, sms:*, backup:*, ...
app/Models             Modellar (Concerns/BelongsToCenter, BelongsToBranch)
app/Services           Pul servislari, SystemWatch, SupportService, Telegram/{TelegramBot, TelegramClient,
                       TelegramNotifier, TelegramFamilyService}, SubscriptionService, PayrollService, ...
app/Support            CenterContext, BranchContext, IntegrationSettings, PermissionRegistry, Format
config/permissions.php Ruxsatlar va rol shablonlari
database/tenancy-baseline.json   tenancy:scan chegarasi
deploy/                Dockerfile, docker-compose.yml, nginx.conf, deploy.sh, db-create.sh, modem xizmati
docs/                  DEPLOY.md, BACKUP_RESTORE.md, SMS_MODEM.md
resources/ai/knowledge.md        Yordam AI bilim bazasi (foydalanuvchiga ko'rinadigan har xususiyat uchun yangilanadi)
routes/                web.php, api.php (faqat Telegram webhook), console.php (jadval)
tests/Feature          Avtomatik testlar
```

## 13. Xavfsizlik

- Parollar, tokenlar va `.env` repoga kirmaydi (`.gitignore`, `.dockerignore`). Tasodifan chiqib ketgan kalit — darhol almashtiring.
- Production: `APP_DEBUG=false`, `APP_URL=https://...` (havolalar https bo'ladi), `SESSION_SECURE_COOKIE=true`.
- Nginx faqat `public/` ni ko'rsatadi; yuklangan fayllar orqali kod bajarilmaydi; veb-port faqat `127.0.0.1` da (internet — faqat Cloudflare Tunnel orqali).
- Cloudflare tavsiyalari (login'ga tezlik chegarasi, Bot Fight) — `docs/DEPLOY.md`.
- Zaxira kunlik, shifrlangan, Telegram orqali; tiklash tartibi — `docs/BACKUP_RESTORE.md`. Bazani tashqarida ham zaxiralang.

## 14. Ma'lum cheklovlar va reja

- Huquqiy hujjat matnlari (oferta, maxfiylik, shartlar) — **qoralama**; e'lon qilishdan oldin yurist ko'rib chiqishi kerak.
- Modem ishchisi haqiqiy USB modem bilan server muhitida sinab ko'rilishi kerak (testlarda soxtalashtirilgan).
- Video qo'llanma hozir YouTube havolasi orqali; o'z serverida saqlash alohida qaror talab qiladi.
- Laravel 13 ga o'tish, Web Push — tashqi paket/vendor yangilashni talab qiladi (rejada).
- Onlayn karta to'lovi, 2FA, SMS matnlarini AI bilan tayyorlash — keyingi bosqichlar.
