# O'zgarishlar tarixi

## EDUZIYO E3 (2026-11-17) — tezlik, izolyatsiya nazorati, Telegram ogohlantirish va oila boti, deploy

- **N+1 va indekslar**: ish haqi sahifasi (`PayrollService::teacherAccrualsMany`) va platforma tahlili/markazlar (`SubscriptionService::limitsMany`) bitta guruhli so'rovlarga o'tkazildi. `QueryBudgetTest` asosiy ro'yxat sahifalarida so'rovlar soni qator soniga bog'liq emasligini tekshiradi. Migratsiya `2026_11_17_000001_eduziyo_center_indexes` markaz bo'yicha qo'shma indekslarni qo'shadi (additive).
- **Markaz izolyatsiyasi nazorati**: yangi `tenancy:scan` (kod: xom SQL va `withoutGlobalScopes()` joylari `database/tenancy-baseline.json` dan oshmasin) — `deploy.sh` har chiqarishda ishlatadi; `tenancy:audit` kunlik va deploy'dan keyin. `TenantLeakCanaryTest` ikki markazli sinov.
- **Haqiqiy xatolar topilib tuzatildi**: (1) `/staff-directory` boshqa markaz xodimlarini ko'rsatishi mumkin edi (markaz filtri qo'shildi, sAdmin akkauntlari yashirildi); (2) platforma tahlili va markazlar sahifasi xodimsiz markazda 500 berardi.
- **Watchdog**: `system:watch` (+ `--loop`) cron, navbat va modem ishchisi, disk va zaxirani tekshiradi; to'xtaganda va tiklanganda **botga ulangan barcha sAdminlarga** Telegram xabari. To'lov cheki yuborilganda va murojaat ochilganda/javob berilganda ham sAdminga Telegram xabari (havola bilan). «Ogohlantirish chatlari» — Integratsiyalar sahifasida.
- **Telegram: o'quvchi va ota-ona**: `/ulanish` (telefon bilan), `/jadval`, `/guruhlar`, `/qarz`, `/davomat`, `/bildirishnoma`, `/chiqish`; darsga kelmaganda davomat xabari, to'lov tasdig'i, kunlik qarz eslatmasi (`telegram:debts`, 09:20). Kabinetda bot kartasi, o'quvchi sahifasida ulanishlar soni. Migratsiya `2026_11_17_000002_eduziyo_telegram_guardians`.
- **Deploy**: `deploy/` — Dockerfile (nginx + php-fpm), `docker-compose.yml` (alohida loyiha `eduziyo`, `127.0.0.1:7005`, resurs chegaralari), `deploy.sh` (git pull → build → tenancy:scan → migrate → audit, `--rollback`), `db-create.sh`, `env.production.example`; `APP_URL` https bo'lsa havolalar https majburlanadi. `docs/DEPLOY.md`: GitHub SSH, deploy kaliti, Cloudflare Tunnel (`educrm.elshod.date`), boshqa loyihalarga tegmaslik ro'yxati.
- README to'liq qayta yozildi.
- Yangilash: `php artisan migrate && php artisan optimize:clear`; fon jarayonlariga `system:watch --loop` qo'shing (Docker'da `watchdog` xizmati).

## EDUZIYO E2 (2026-11-16) — video muqova, markazni o'chirish, filial limiti, Integratsiyalar sahifasi

- **Video qo'llanma muqovasi**: sAdmin har videoga muqova rasm yuklaydi (PNG/JPG/WebP); ro'yxatda telefon ikonkasi o'rniga muqova va «play» belgisi chiqadi. Muqova bo'lmasa — YouTube kadri yoki standart belgi.
- **Markazni o'chirish** (sAdmin → Markazlar → markaz → «Xavfli zona»): markaz nomini yozib tasdiqlanadi; barcha filiallar, foydalanuvchilar va ma'lumotlar o'chadi. Rozilik/shartnoma dalillari (`center_ref`) saqlanib qoladi.
- **Arzonroq tarifga o'tish**: yangi tarifda filial limiti kam bo'lsa, faqat birinchi N ta filial ishlaydi, qolganlari **faqat ko'rish** rejimida (yozish 403/ogohlantirish). Tarif sotib olishda qaysi filiallar yopilishi ogohlantirib ko'rsatiladi va tasdiq (`ack_branches`) so'raladi. Holat saqlanmaydi, har safar hisoblanadi — tarif qayta ko'tarilsa, filiallar o'zi ochiladi.
- **Integratsiyalar** (sAdmin → «Integratsiyalar (bot, modem)»): Telegram bot tokeni (Telegram'da tekshiriladi, shifrlab saqlanadi, to'liq ko'rsatilmaydi), bot nomi (avtomatik), webhook manzili va maxfiy kaliti, «Webhook o'rnatish / holatni tekshirish / olib tashlash»; USB modem portlari, tezlik (baud), xabarlar orasidagi tasodifiy pauza va **SIM kunlik chegarasi** (chegaraga yetsa SMS Eskiz orqali ketadi). Sahifadagi qiymat `.env` dan ustun; bo'sh qoldirilsa avvalgisi saqlanadi. `.env` dagilar zaxira sifatida ishlayveradi.
- Migratsiyalar: `2026_11_16_000001_eduziyo_guide_cover`, `2026_11_16_000002_eduziyo_modem_daily` (additive). Yangilash: `php artisan migrate && php artisan optimize:clear`; modem ishchisi qayta ishga tushirilsin (`sudo systemctl restart eduziyo-modem`).
- Testlar: 670 (SQLite va MariaDB strict) — `CenterDeleteAndBranchLimitTest`, `IntegrationSettingsTest`, `SupportAndGuidesTest`.

## EDUZIYO E1c (2026-11-15) — murojaatga rasm (skrinshot), menyu ikonkalari

- **Murojaatga rasm biriktirish**: direktor ham, sAdmin ham xabarga 3 tagacha rasm (PNG/JPG/WebP, har biri 5 MB gacha) biriktiradi — fayl tanlab yoki matn maydoniga **Ctrl+V** bilan qo'yib. Rasmlar yopiq diskda (`storage/app/private/support`) saqlanadi va faqat shu markaz direktori hamda sAdmin ochadi (boshqa markazga 404). Markaz o'chirilganda rasmlar ham o'chadi.
- **Menyu ikonkalari**: «SMS jurnali (platforma)» va «Murojaatlar»da ikonka yo'q edi (`chat` ikonkasi mavjud emasdi). Yangi ikonkalar: murojaat, video, hujjat, USB modem, brending, rasm. Test har bir menyu ikonkasi mavjudligini tekshiradi.
- Migratsiya `2026_11_15_000002_eduziyo_support_attachments` (additive). Yangilash: `php artisan migrate && php artisan optimize:clear`. Testlar: `SupportAndGuidesTest` (10), `SidebarIconsTest`.

## EDUZIYO E1b (2026-11-15) — o'qituvchi sahifalari, Telegram zaxira holati

- **O'qituvchi**: yon menyuda «Mening jadvalim» (kelgusi 14 kun, faqat o'z guruhlari) va «Mening ish haqim» (guruhlar bo'yicha hisoblangan/to'langan/qoldiq, so'nggi to'lovlar; faqat ko'rish). Boshqa o'qituvchi ma'lumoti ochilmaydi, boshqa rollar 403.
- **Tizim holati** (sAdmin): yangi qator «Zaxira (Telegram)» — `backup:telegram` oxirgi natijasi (muvaffaqiyat/xato sababi, 2 kundan eski bo'lsa ogohlantirish).
- Migratsiya yo'q. Testlar: `TeacherSelfTest` (2).
- Eslatma: mobil API olib tashlangan, lekin `device_tokens`, `app_versions`, `personal_access_tokens` jadvallari va Sanctum izi ma'lumotni yo'qotmaslik uchun qoldirildi (ishlatilmaydi).

## EDUZIYO E1 (2026-11-15) — murojaat tiketlari, video qo'llanma, huquqiy hujjatlar va majburiy rozilik

- **Murojaat (tiket)**: direktor «Markaz → Murojaat»da sAdmin'ga yozadi (bir vaqtda bitta ochiq murojaat; markaz faqat o'qish rejimida ham yoza oladi). Holatlar: ochiq / mijoz javobi kutilmoqda / yopilgan. **SLA** ish vaqti (standart 09:00–18:00, Du–Sh, 4 soat; sAdmin sozlaydi) bo'yicha hisoblanadi, 4 soatda eslatma, mijoz 7 kun javob bermasa avtomatik yopiladi (`support:tick`, har soat). Yopilgach 1–5 baho.
- **Video qo'llanma**: sAdmin YouTube havolasini kiritib, qaysi rollarga ko'rinishini belgilaydi (o'quvchi ko'rmaydi); foydalanuvchi faqat o'z roliga tegishlisini ko'radi («Video qo'llanma»).
- **Huquqiy hujjatlar** (sAdmin → «Hujjatlar va rozilik»): oferta (direktorlar), maxfiylik siyosati va foydalanish shartlari (hamma). Versiyalanadi: e'lon qilingan versiya o'zgarmaydi, o'zgartirish — yangi versiya (qayta rozilik talab qilish belgisi bilan). Dastlabki 3 matn **qoralama** sifatida keladi — yurist ko'rib chiqib, «Qoralama» eslatmasini o'chirgandan keyingina e'lon qilinadi.
- **Majburiy rozilik**: e'lon qilingandan keyin har bir foydalanuvchi (sAdmin va demo'dan tashqari) keyingi kirishda rozilik oynasini ko'radi; rozi bo'lmaguncha boshqa sahifa ochilmaydi (o'quvchi kabineti va PWA'da ham). «Rozi emasman» — chiqish va bosh sahifa. Rozilik sana, IP va brauzer bilan o'zgarmas jurnalga yoziladi va markaz o'chirilsa ham saqlanadi. Direktor ofertani qabul qilganda markaz nomidan `center_agreements` yozuvi (tashkilot, shaxs, versiya, vaqt) yaratiladi.
- **Ommaviy sahifalar**: `/oferta`, `/maxfiylik`, `/shartlar` (sotuv sahifasi pastida va kirish sahifasida havola).
- **Shakllarda rozilik**: sotuv arizasi va `/apply/{filial}` murojaat shaklida maxfiylik siyosatiga rozilik belgisi majburiy (siyosat e'lon qilingach); versiya va vaqt `consent_*` ustunlariga yoziladi.
- **18 yoshgacha o'quvchi**: xodim o'quvchi qo'shganda ota-ona (vakil) F.I.O va roziligi majburiy (`guardian_name`, `guardian_consent_at`).
- **sAdmin hisoboti**: kim qaysi hujjatning qaysi versiyasini qachon qabul qilgan, qabul qilmaganlar soni, markaz bo'yicha filtr, Excel.
- Migratsiyalar (additive): `2026_11_14_000001_eduziyo_support_videos`, `2026_11_15_000001_eduziyo_legal`. Yangilash: `php artisan migrate && php artisan optimize:clear`; cron `schedule:run` ishlashi kerak. Testlar: `SupportAndGuidesTest` (8), `LegalConsentTest` (11); jami 649 (SQLite va MariaDB strict).

## EDUZIYO E10 (2026-11-14) — chiqish tasdig'i, API olib tashlandi, direktor qo'shish, USB modemlar, brending

- **Chiqish**: tugma bosilganda «Tizimdan chiqasizmi?» tasdiqlash oynasi; tasdiqlansa **bosh (sotuv) sahifasiga** o'tadi (login sahifasiga emas).
- **Mobil API va API hujjati olib tashlandi**: `/api/v1/*`, `/docs`, Postman kolleksiyasi, «Ilova versiyasi» sozlamasi, SMS-gateway API. Faqat Telegram bot webhook'i (`POST /api/telegram/webhook`) qoldi. (Eski jadvallar — `device_tokens`, `app_versions`, `personal_access_tokens` — ma'lumotni yo'qotmaslik uchun bazada qoldi.)
- **Markazga direktor qo'shish**: direktor «Markaz → Direktorlar»da, sAdmin markaz sahifasidagi «Direktorlar»da yangi direktor qo'shadi/bloklaydi (o'zini bloklab bo'lmaydi, kamida bitta faol direktor qoladi). Login/parol Telegram havolasi orqali beriladi. Admin, menejer, o'qituvchi uchun yopiq (403).
- **USB modemlar (Lubuntu serverda)**: tizim `/dev/ttyUSB*`/`ttyACM*` portlarini AT buyruq bilan **o'zi topadi** (IMEI bo'yicha guruhlaydi), SIM/tarmoq/signal/operatorni o'qiydi, yangi modemni **avtomatik faollashtiradi**; bir nechta modem orasida SMS navbatma-navbat taqsimlanadi. `sms:modem-run` (systemd xizmat, `deploy/eduziyo-modem.service`) har 60 soniyada qayta qidiradi va navbatni yuboradi; xato/yo'qolgan modem bo'lsa avtomatik Eskiz. sAdmin → «USB modemlar»: holat, qayta qidirish, faollashtirish/o'chirish, nom, sinov SMS, diagnostika. PDU: lotin — GSM 7-bit, kirill/`ʻ` — UCS2, uzun xabar bo'laklarga. Qo'llanma: `docs/SMS_MODEM.md`. (E5'dagi gateway API o'rniga.)
- **Brending**: sAdmin → «Brending»: loyiha nomi (EDUZIYO ni boshqa nomga almashtirish), logotip (PNG/JPG/WebP, SVG ruxsat etilmaydi), favicon — panel, kirish, sotuv sahifasi, kabinet, chek/shartnoma, PWA manifest (`/manifest.webmanifest` endi dinamik), Telegram matnlarida. Direktor «Markaz»da o'z markazi logotipini qo'yadi (yon panelda markaz nomi bilan ko'rinadi; markaz o'chirilganda fayl ham o'chadi).
- Migratsiyalar (additive): `2026_11_13_000001_eduziyo_sms_modems`, `2026_11_13_000002_eduziyo_branding`. Yangilash: `php artisan migrate`, `optimize:clear`, modem uchun `docs/SMS_MODEM.md`. `.env` dagi `SMS_GATEWAY_TOKEN` endi kerak emas.

## EDUZIYO E5 (2026-11-13) — SMS: USB modem + Eskiz zaxirasi, jurnal, SMS faktura

- **USB modem (gammu) kanali**: SMS avval modem navbatiga (`sms_outbox`) tushadi; serverdagi skript `POST /api/sms-gateway/{heartbeat,claim,report}` orqali oladi/yuboradi (Bearer `SMS_GATEWAY_TOKEN`, token yo'q yoki noto'g'ri bo'lsa 403). Modem 3 daqiqa signal bermasa kanal o'chiq hisoblanadi va SMS to'g'ridan-to'g'ri Eskiz'ga ketadi. Qo'llanma: `docs/SMS_MODEM.md`.
- **Avtomatik Eskiz zaxirasi** (`sms:dispatch`, har daqiqa): modem 2 daqiqada olmagan, 5 daqiqada natija bermagan yoki xato qaytargan SMS Eskiz orqali yuboriladi. Limit ikki marta hisoblanmaydi.
- **SMS jurnali**: sAdmin — barcha markazlar (markaz/holat/kanal/telefon filtrlari, modem holati); direktor — «Markaz → SMS» (o'z markazi). Kanal (Modem/Eskiz) ko'rsatiladi.
- **SMS matnini saqlash muddati** (sAdmin sozlaydi, standart 30 kun, 0 = cheksiz): `sms:prune-text` (har kuni 04:00) eski matnni tozalaydi, yozuv va holat qoladi.
- **SMS faktura**: sAdmin markazga qo'shimcha SMS paketi (soni va summa qo'lda) taklif qiladi; bir vaqtda bitta faol faktura; 3 kunda qabul qilinmasa avtomatik bekor (`centers:lifecycle`). Direktor qabul qilsa summa balansdan atomik yechiladi (tur `sms_pack`), SMS soni joriy tarif davriga qo'shiladi va oylik limit tugagach ishlatiladi. Balans yetmasa hech narsa o'zgarmaydi.
- Migratsiya `2026_11_12_000001_eduziyo_sms_platform` (additive). Yangilash: `php artisan migrate`, `.env` ga `SMS_GATEWAY_TOKEN` (ixtiyoriy), `optimize:clear`. Testlar: `SmsPlatformTest` (12).

## EDUZIYO E8 (2026-11-12) — Telegram bot, parolni faqat bot orqali tiklash, shifrlangan zaxira

**Yangi: Telegram bot** (webhook `POST /api/telegram/webhook`, maxfiy sarlavha `X-Telegram-Bot-Api-Secret-Token` bo'lmasa 403)
- **Hisobni ulash**: Profil (va o'quvchi kabineti profili) → «Telegramni ulash» → botda «Start» (bir martalik havola, 15 daqiqa). Bir chat — bir hisob.
- **Parolni faqat bot orqali tiklash**: saytda «Parolni unutdingizmi?» (`/forgot-password`) → login → bot **6 xonali kod** yuboradi (10 daqiqa, 5 urinish, soatiga 5 so'rov, kod bazada hash) → yangi parol (kamida 8 belgi, harf+raqam) → **barcha sessiya va API tokenlar yopiladi**, botga xabar boradi. Login mavjudligi oshkor qilinmaydi.
- **Telegram ulanmagan/yo'qotgan**: botda `/parol` → o'z kontaktini ulashadi (faqat o'zining; Telegram tasdiqlagan raqam tizimdagi raqamga mos bo'lsa hisob ulanadi; bir raqamga bir nechta hisob bo'lsa login so'raladi). **sAdmin hisobi kontakt orqali ulanmaydi.**
- **Qo'lda parol tiklash olib tashlandi**: o'quvchi sahifasidagi «Parolni yangilash» va xodim tahrirlashdagi parol maydoni (parol faqat yaratishda beriladi).
- **Direktorning birinchi kirishi**: sAdmin markaz yaratganda parolni ko'rmaydi — direktorga bir martalik havola beradi, direktor botda «Start» bosib login va parolni o'zi oladi (bot sozlanmagan bo'lsa eski usul: login/parol ekranda).
- **Sotuv arizasi tasdig'i**: ariza yuborilgach «Telegram'da tasdiqlash» tugmasi; botda telefon kontakti arizadagi raqamga mos bo'lsa `phone_verified_at` qo'yiladi va sAdmin'ga xabar boradi.
- **Xabarlar**: sAdmin'ga yangi ariza, balans to'ldirish so'rovi, zaxira xatosi; direktorga tarif eslatmalari (`CenterAlerts` orqali).

**Yangi: kunlik shifrlangan zaxira (`backup:telegram`, 03:20)**
- Baza zaxirasi shifrlanib (AES-256-GCM, kalit RSA ochiq kalit bilan o'ralgan) Telegram bot orqali sAdmin'ga yuboriladi; 45 MB dan katta bo'lsa bo'laklanadi. **Serverda faqat ochiq kalit** turadi. Kalit yo'q bo'lsa hech narsa yuborilmaydi va ogohlantirish keladi. Yakshanba 04:00 — cheklar va yuklangan fayllar zaxirasi (`--files`).
- `php artisan backup:keygen` (kalit juftligi), `bin/backup-decrypt.php` (ochish), `docs/BACKUP_RESTORE.md` (tiklash qo'llanmasi).

**Texnik**: yangi jadvallar `telegram_links`, `telegram_reset_codes`; `sales_applications.telegram_token` (faqat qo'shimcha migratsiya `2026_11_11_000001_eduziyo_telegram`); `php artisan telegram:webhook`; `.env`: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET`. 12 ta yangi test.

**Yangilash**: `php artisan migrate`; `.env` ga Telegram kalitlarini yozing; `php artisan telegram:webhook`; zaxira kalitini `docs/BACKUP_RESTORE.md` bo'yicha sozlang; `php artisan optimize:clear`.

## EDUZIYO E4 + E9 (2026-11-11) — Tarif, obuna, markaz balansi, limitlar, hayot sikli va tahlil

**Yangi: markaz balansi va to'ldirish**
- Har markazning **balansi** (faqat direktor va sAdmin ko'radi) va to'liq jurnal (`center_balance_transactions`: to'ldirish, tarif sotib olish, qoldiq qaytarish, sAdmin tuzatishi). Balans faqat `CenterBalanceService` orqali o'zgaradi (qulflangan tranzaksiya, manfiyga tushmaydi).
- Direktor **Markaz → Balans** sahifasida platforma kartasini tanlab, summa, to'lov vaqti va **chek** (JPG/PNG/PDF, 5 MB gacha) bilan so'rov yuboradi. Chek SHA-256 bilan solishtiriladi — takroriy chek qabul qilinmaydi. sAdmin **Balans to'ldirish** sahifasida chekni ko'rib, summani tasdiqlaydi yoki sabab bilan rad etadi (qayta tasdiqlab bo'lmaydi). To'lov kartalari sAdmin tomonidan boshqariladi.

**Yangi: tarif obunasi**
- Direktor **Markaz → Tariflar** da oylik yoki yillik tarifni balansdan sotib oladi. Narx va limitlar sotib olingan paytda nusxalanadi (keyin tarif o'zgarsa, joriy obuna o'zgarmaydi).
- Qimmatroq tarifga o'tish — darhol, eski tarif qoldig'i balansga qaytadi; arzonroq yoki bir xil tarif — joriy davr tugagach boshlanadi (navbatda ko'rinadi). sAdmin tarifni **to'lovsiz kun bo'yicha sovg'a** qila oladi.
- Tarifsiz **eski faol markazlar cheksiz** ishlaydi (limit yo'q). **Yangi markaz «tarif kutilmoqda»** holatida ochiladi va tarif olinmaguncha faqat ko'rish rejimida.

**Yangi: limitlar**
- Filiallar soni va faol o'quvchilar soni tarif limiti bilan cheklanadi (o'quvchi qo'shish, import, murojaatdan o'tkazish — hammasi bir joydan tekshiriladi). Oylik **SMS** va **AI** so'rovlar sanaladi: tarifda 0 bo'lsa xizmat o'chiq, tugasa xabar chiqadi. Direktor Markaz sahifasida sarfni ko'radi.

**Yangi: hayot sikli (`centers:lifecycle`, har soat)**
- Tugashiga 7/3/1 kun qolganda direktorga eslatma (bir marta). Tarif tugasa markaz **faqat ko'rish rejimiga** o'tadi (yozish amallari to'xtaydi, tepada qizil lenta, GET va Excel eksport ishlaydi — eksport sAdmin sozlamasi `allow_export_in_read_only` bilan boshqariladi). 30 kun ichida yangilanmasa — o'chirishga 3 kun qolganda oxirgi ogohlantirish va muddat tugagach **markaz ma'lumoti butunlay o'chadi** (`CenterWiper`; markaz qatori «o'chirilgan» bo'lib qoladi). Tarif sotib olinsa/berilsa markaz darhol tiklanadi. Demo va tarifsiz eski markazlarga tegmaydi. `--dry-run` bor.
- Direktor ma'lumotni **Excel**'ga yuklay oladi (o'quvchilar, to'lovlar, guruhlar).

**Yangi: sAdmin «Markazlar» konsoli**
- Markazlar ro'yxati (holat, tarif, tugash, o'quvchi, balans), **yangi markaz yaratish** (markaz + birinchi filial + direktor; login/parol bir marta ko'rsatiladi), markaz sahifasi: tarif sovg'a qilish, balans tuzatish (sabab majburiy), to'xtatish/tiklash, 6 oylik tahlil.
- Direktor endi **o'z markazining filiallarini** ochadi/yopadi/o'chiradi (limit doirasida); eski «faqat sAdmin» qoidasi shu darajada yumshatildi.

**Yangi: har markaz tahlili (E9)**
- Direktor **Markaz → Tahlil**, sAdmin **Platforma tahlili** va markaz sahifasida: faol o'quvchilar, qarzdorlar, filiallar, davom etayotgan guruhlar, oylar bo'yicha yangi o'quvchi, ketgan o'quvchi, tushum, murojaat, balans to'ldirish, SMS va AI sarfi. Har diagrammada **Grafik/Jadval** almashtirgichi. Shaxsiy ma'lumot (ism, telefon) ishlatilmaydi.

**Texnik**: yangi jadvallar `center_wallets`, `center_balance_transactions`, `payment_cards`, `topup_requests`, `center_subscriptions`, `usage_counters` (faqat qo'shimcha migratsiya `2026_11_10_000001_eduziyo_billing`); `EnsureCenterWritable` middleware (`writable`) web va API'da; yangi xizmatlar `CenterBalanceService`, `SubscriptionService`, `UsageService`, `CenterLifecycle`, `AnalyticsService`, `CenterAlerts` (Telegram bosqichida shu joydan yuboriladi). 18 ta yangi test.

**Yangilash**: `php artisan migrate`, `php artisan optimize:clear`. Cron/`schedule:work` ishlab turishi kerak (`centers:lifecycle` har soat). Platforma sozlamasi: sAdmin to'lov kartasini «Balans to'ldirish» sahifasida kiritishi kerak.

## EDUZIYO E6 + E7d (2026-11-10) — O'quvchi kabineti, PWA va ommaviy demo

**Yangi: o'quvchi veb-kabineti** (`/cabinet`, faqat `student` roli)
- O'quvchi endi veb'dan kiradi (login sahifasi umumiy). Bo'limlar: Bosh sahifa (balans/qarz, davomat %, bugungi darslar, guruhlar, so'nggi harakatlar), Guruhlar va guruh sahifasi (jadval, o'qituvchi, davomat tarixi), To'lovlar (balans harakatlari), Kurslar (video, audio, kitoblar, test), Xabarlar, Profil (telefon va parolni o'zgartirish). Pastki navigatsiyali, mobil-birinchi maket.
- Qoidalar: o'quvchi faqat `/cabinet` ni ko'radi (xodim sahifalari uni kabinetga qaytaradi); xodim `/cabinet` ga kira olmaydi (403); boshqa filial/markaz guruhi va kursi 404; test javob kaliti topshirilguncha yuborilmaydi; sinfdosh va o'qituvchi telefoni ko'rsatilmaydi.
- Eski xabar ("o'quvchilar faqat mobil ilovadan foydalanadi") olib tashlandi. Mobil API hozircha qoldi (keyinroq olib tashlanadi).

**Yangi: PWA (o'rnatiladigan ilova)** — barcha rollar uchun: `manifest.webmanifest`, `sw.js` (faqat statik fayllar keshlanadi; pul va davomat sahifalari hech qachon keshlanmaydi), `offline.html`, ikonkalar (192/512/maskable/Apple), «O'rnatish» taklifi (Android/Chrome/Edge) va iOS yo'riqnomasi.

**Yangi: ommaviy demo (har rol uchun bir bosishda kirish)**
- Sotuv sahifasida 6 ta tugma: Direktor, Admin, Menejer, Operator, O'qituvchi, O'quvchi «sifatida kirish». Login/parol ko'rsatilmaydi (demo foydalanuvchilarning paroli har tozalashda tasodifiy).
- Alohida **Demo markaz** (`is_demo`): 2 filial, o'quvchilar, guruhlar, to'lovlar, davomat, murojaatlar, kassa, test savollari. Haqiqiy markazlar bilan aralashmaydi.
- **Har tun 03:10 da `demo:reset`** (`--dry-run` ham bor): faqat demo markaz ma'lumoti o'chib, qayta yaratiladi. sAdmin «Demo kirishlar» sahifasidan qo'lda ham tiklay oladi.
- Himoya: sessiya 30 daqiqa; parol/profil, xodim yaratish/tahrirlash, import, ommaviy SMS, bildirishnoma, AI va filial amallari yopiq; demo markazda haqiqiy SMS yuborilmaydi; yuqorida «Demo rejim» lentasi («Ariza qoldirish», «Demo'dan chiqish»).
- sAdmin → Sotuv → **Demo kirishlar**: rol bo'yicha va kunlar bo'yicha kirishlar. Arizada «Demo ko'rdi: …» (arizachi qaysi rollarni ko'rgani). Sotuv sozlamalarida demo yoqish/o'chirish kaliti; eski «demo login/parol» maydonlari olib tashlandi.
- Yangi umumiy xizmat `CenterWiper` (markaz ma'lumotini xavfsiz o'chirish; keyingi bosqichda tarif tugagandan keyingi o'chirish ham shuni ishlatadi).
- Yangi jadval `demo_visits`; `sales_applications.demo_roles`. Migratsiya faqat qo'shimcha.
- 23 ta yangi test (jami 657; SQLite va MariaDB qat'iy rejimida o'tadi).

**Yangilash**: `php artisan migrate`, so'ng `php artisan demo:reset` (birinchi marta demo markazni yaratadi), `php artisan optimize:clear`. Cron/`schedule:work` ishlab turishi kerak (demo har tun tiklanishi uchun).

## EDUZIYO E7c (2026-11-09) — Sotuv sahifasi doim `/` da, yangi animatsion dizayn

**O'zgardi**
- **Sotuv sahifasi `/` manzilida doim ochiq** — kirgan foydalanuvchi uchun ham. Boshqaruv paneli endi **`/dashboard`** manzilida (`route('dashboard')`, nomi o'zgarmadi). Login'dan keyin avtomatik `/dashboard` ga o'tiladi. Sahifa tepasida kirganlarga «Panelga o'tish», mehmonlarga «Kirish» va «Ariza qoldirish» tugmalari ko'rinadi. Panel sarlavhasida «Sayt» havolasi (yangi oynada) qo'shildi.
- **Sotuv sahifasi qayta ishlandi**: zamonaviy dizayn, har bir element animatsiyali (ekranga kelganda paydo bo'lish, suzuvchi kartalar, aylanuvchi sarlavha so'zi, yuqoridagi o'qish chizig'i, marquee, rollar tabi, tarifda oylik/yillik almashtirgich, FAQ akkordeon, tepaga qaytish tugmasi), 40 ga yaqin mos SVG ikonka (`<x-lp-icon>`), qorong'i footer, mobil menyu. Animatsiyalar `prefers-reduced-motion` ni hisobga oladi. Stil va skript: `public/css/landing.css`, `public/js/landing.js`.

**Yangilash**: `php artisan optimize:clear` (migratsiya kerak emas). Eski xatcho'plar `/` ga kirsa sotuv sahifasi ochiladi; panelga `/dashboard` orqali kiring.

## EDUZIYO E7b (2026-11-08) — Tariflar, demo, rasmli yangiliklar va videolar

**Yangi**
- **3 ta tarif** (Start, Standart, Premium): sotuv sahifasida kartalar, oylik/yillik almashtirgich (yillik narx kiritilsa), limitlar (filial, o'quvchi, oylik SMS va AI), imkoniyatlar ro'yxati, «Mashhur» belgisi va yillik tejash summasi. «Shu tarifni tanlash» tugmasi arizadagi tarifni oldindan tanlaydi.
- **sAdmin → Sotuv → Tariflar**: narx, limit, imkoniyatlar, tartib, ko'rinish va «Mashhur» ni tahrirlash. Soni qat'iy 3 (qo'shish/o'chirish yo'q). Narxlar ataylab bo'sh: kiritilmaguncha sahifada «Narxni bilish» ko'rinadi.
- **Demo bo'limi**: demo login/parol (ikkalasi kiritilsa ochiq ko'rsatiladi) yoki «Demo so'rash». Arizada «Demo ko'rsatishingizni xohlayman» belgisi; sAdmin ro'yxatida «Demo so'radi» va tanlangan tarif ko'rinadi.
- **Yangiliklarga rasm va YouTube video**: rasm (JPG/PNG/WEBP, 3 MB gacha, SVG va boshqa fayllar rad etiladi) `public/uploads/news` ga saqlanadi (symlink shart emas); almashtirilsa yoki o'chirilsa eski fayl ham o'chadi. YouTube havolasidan faqat 11 belgili ID olinadi, video `youtube-nocookie.com` orqali ko'rsatiladi.
- **sAdmin → Sotuv → Videolar**: sotuv sahifasidagi «Video qo'llanmalar» bo'limi uchun YouTube videolar (qo'shish, yashirish, o'chirish).
- Yangi jadvallar: `tariffs` (3 ta yozuv bilan), `sales_videos`; `news_posts` ga `image_path`, `youtube_id`; `sales_applications` ga `wants_demo`, `tariff_slug`. Migratsiya faqat qo'shimcha.
- 12 ta yangi test (jami 634; SQLite va MariaDB qat'iy rejimida o'tadi).

**Diqqat**
- Tarif limitlari (SMS, AI, filial, o'quvchi) hozircha faqat katalogda ko'rsatiladi; ularni tizimda majburlash va obuna/balans keyingi bosqichda (E4/E5).

## EDUZIYO E7 (2026-11-07) — Sotuv sahifasi

**Yangi**
- **Ochiq sotuv sahifasi** `/` manzilida (mehmon uchun): imkoniyatlar, rollar, tariflar, yangiliklar, ko'p so'raladigan savollar, aloqa. Kirgan foydalanuvchi `/` da avvalgidek boshqaruv panelini ko'radi (`route('dashboard')` o'zgarmadi).
- **Ariza shakli** (`POST /ariza`): ism, markaz nomi, telefon (`+998 90 123 4567`), shahar, filial va o'quvchi soni, izoh, UTM belgilari. Botlardan himoya (yashirin maydon + soatiga 5 ta cheklov), 24 soat ichida shu raqamdan takror ariza yaratilmaydi.
- **sAdmin → Sotuv**: *Arizalar* (holat voronkasi: yangi → bog'lanildi → demo → taklif → mijoz/rad; qayta bog'lanish sanasi, ichki izoh, qidiruv va filtr), *Yangiliklar* (yozish, qoralama/chop etish, kelajak vaqtiga rejalash; faqat sotuv sahifasida ko'rinadi), *Sotuv sozlamalari* (telefonlar, email, manzil, Telegram va Instagram havolasi, tarif matni). Arizalarni faqat sAdmin ko'radi.
- Yangi jadvallar: `sales_applications`, `news_posts`, `platform_settings` (platforma darajasida, markazga bog'liq emas). Migratsiya faqat qo'shimcha.
- 12 ta yangi test (jami 622; SQLite va MariaDB qat'iy rejimida o'tadi). `AuthTest`: mehmon `/` da endi sotuv sahifasini ko'radi, himoyalangan sahifalar esa loginga yo'naltiradi.
- `storage/` va `bootstrap/cache` ichidagi bo'sh papkalarga `.gitkeep` qo'shildi («Please provide a valid cache path» xatosi zip'dan ochilganda kelib chiqmasligi uchun).

**Hali yo'q (keyingi bosqichlar)**
- Arizadagi telefonni Telegram bot orqali tasdiqlash (jadvalda `phone_verified_at` va `telegram_chat_id` tayyor; arizalar ro'yxatida hozir «Telefon tasdiqlanmagan» ko'rinadi).
- Tarif jadvali va kalkulyator (tariflar tizimi E4 da), video qo'llanmalar, ekran rasmlari va demo markazga kirish tugmasi, shartnoma/maxfiylik havolalari.

## EDUZIYO E0 (2026-11-06) — Ko'p markazli (multi-tenant) asos

Edunova kodi asos qilib olindi (v13). Platforma → **Markaz** → Filial tuzilmasi qo'shildi. Mavjud 585 test o'zgarishsiz o'tadi, yana 25 ta yangi test qo'shildi (jami 610; SQLite va MariaDB 10.11 qat'iy `ONLY_FULL_GROUP_BY` rejimida tekshirilgan).

**Yangi**
- `centers` jadvali va `Center` modeli (holatlar: kutilmoqda, faol, faqat ko'rish, to'xtatilgan, o'chirilgan). Markaz hayot sikli, tarif va balans keyingi bosqichda (E4).
- `center_id` barcha filialga bog'liq jadvallarga (39 ta) qo'shildi + `branches`, `users`, `group_days`, `lead_notes`, `import_batches`. Mavjud ma'lumot bo'lsa, migratsiya bitta «Asosiy markaz» yaratib hammasini unga biriktiradi (ma'lumot buzilmaydi, sAdmin markazsiz qoladi).
- `CenterContext` + `BelongsToCenter` + `CenterScope`: har bir so'rovga avtomatik `center_id` sharti. Markazi yo'q foydalanuvchi hech narsa ko'rmaydi; `center_id = NULL` qatorlar markaz foydalanuvchilariga ko'rinmaydi (xato bo'lsa ma'lumot «yo'qoladi», boshqa markazga «oqib» o'tmaydi).
- Yangi rol **Direktor (`owner`)**: filialsiz, o'z markazining barcha filiallari va hamma ruxsatlari; filial tanlagichi faqat o'z markazining filiallarini ko'rsatadi; xodim qo'shish/ruxsat berish o'z markazi bilan cheklangan. Boshqa markaz, sAdmin va platforma sahifalari (Filiallar, Super adminlar, Tizim holati) yopiq.
- sAdmin uchun **markaz tanlagich** (yuqori panelda) va yangi filial ochishda markaz tanlash; filial nomi markaz ichida noyob, filial kodi (`/apply/{kod}`) global noyob.
- Markaz holati (to'xtatilgan/o'chirilgan) bo'lsa — kirish va ochiq sessiya to'xtatiladi.
- `php artisan tenancy:audit` (har kuni 03:30): `center_id` bo'sh yoki filial markaziga mos kelmaydigan qatorlarni topadi.
- Meta-test: `center_id` ustuni bor har bir jadvalning modeli `BelongsToCenter` ishlatishi shart (yangi jadval skoupsiz qolsa test yiqiladi).
- DemoSeeder endi «Demo markaz» va `demo.direktor` ni yaratadi.

**Tuzatildi (sinov kodida)**
- `V13UslugaTest` admin'ga `staff.manage` ruxsatini bermagani uchun 403 olardi; `V13ListsAndRegistrarTest` apostrofni noto'g'ri escape qilardi. Mahsulot kodi o'zgarmadi.

**Diqqat**
- `Model::insert()`, `DB::table()` va `upsert()` model hodisalarini aylanib o'tadi — ularda `center_id` qo'lda beriladi (`GroupDay`, `Holiday` tuzatildi). `tenancy:audit` shuni tekshiradi.
- Laravel 13 ga ko'tarish (E0a) bu muhitda bajarilmadi: Packagist'ga ulanish yopiq. Serverda: PHP 8.3+ da `composer require laravel/framework:^13.0 -W`, so'ng to'liq testlar.

## 1-bosqich (2026-09-20) — Yangi asos

Loyiha noldan, toza arxitektura bilan qayta yozildi (eski koddagi xatolar ko'chirilmadi).

**Xavfsizlik**
- `.env`, `.sql`, `vendor` kabi maxfiy fayllar ildiz `.htaccess` orqali yopildi; ma'lumotlar bazasi dumpi va parollar loyihadan chiqarildi.
- Kirishda urinishlar cheklovi (5 ta xato → 1 daqiqa kutish), bloklangan foydalanuvchi va yopilgan filial xodimlari darhol chiqariladi.
- Mobil tokenlar 30 kunda tugaydi; foydalanuvchi bloklansa yoki parol almashsa, tokenlar bekor qilinadi.
- Boshqa filial ma'lumotlariga kirish tuzilma darajasida yopilgan (global filial filtri + siyosatlar).

**Yangiliklar**
- Ko'p filiallik: filial ochish, tahrirlash, yopish (arxiv), qayta ochish; sAdmin uchun filial almashtirgich.
- Filialning o'z Eskiz akkaunti (bo'sh bo'lsa, umumiy akkaunt).
- Batafsil ruxsatlar tizimi (`config/permissions.php`): sAdmin admin/menejer/o'qituvchi ruxsatlarini belgilaydi; admin faqat o'zidagi ruxsatlarni o'z filialidagi menejer va o'qituvchilarga beradi.
- Hodimlar (admin, menejer) boshqaruvi, harakatlar jurnali (audit), profil.
- Yangi ATKO dizayni: qizil-oq, yorug'/qorong'i rejim, telefonga moslashgan.
- Mobil API v1 asosi: login, profil, parol, chiqish; yagona javob va xato formati.
- Barcha interfeys va xato xabarlari o'zbek tilida.

**Sifat** 
- 33 ta avtomatik test (kirish, filial cheklovi, ruxsat qoidalari, API).
- Nomlar tozalandi: `techer`→`teacher`, `meneger`→`manager` va h.k.
- Keraksiz paketlar (`laravel/ui`, Bootstrap, Sass va boshqalar) olib tashlandi.

**1.1 (tuzatish):** Laravel 11 dagi ochiq zaifliklar sababli `composer install` bloklanardi - framework `^12.0` ga o'tkazildi.

## 2-bosqich (2026-09-21) — O'quvchilar, guruhlar, davomad

**Yangiliklar**
- **O'quvchilar:** qo'shish, tahrirlash, qidiruv, qarzdorlar, arxiv; profil (balans, guruhlar tarixi, balans harakatlari, harakatlar tarixi). Mobil logini telefondan, paroli tasodifiy va bir marta ko'rsatiladi.
- **Guruhlar:** dars kunlari (toq/juft/har kuni) dam olish kunlarini o'tkazib avtomatik tuziladi; xona va o'qituvchi band bo'lsa xato (bazada ham unique-cheklov); narx guruh ochilganda saqlanadi; guruhni davom ettirish.
- **Balans jurnali:** har bir yechish/qaytarish/jarima/chegirma `balance_transactions` da qoladi, balans qulflangan holda yangilanadi.
- **Davomad:** faqat dars kunida, faqat bugungi kun; olingan davomadni shu kun tahrirlash mumkin; o'qituvchi faqat o'z guruhida; davomad jadvali (olingan/olinmagan/kelgusi kunlar).
- **Davomad statistikasi:** kunlik (guruhlar kesimida) va oylik (guruhlar, kunlar dinamikasi, eng ko'p qoldirayotganlar).
- **Sozlamalar:** xonalar, dars vaqtlari, narx rejalari, o'quvchi manbalari, dam olish kunlari (1 yilga avtomatik), kurslar. Eskini o'chirish o'rniga faolsizlantiriladi.
- O'qituvchilar boshqaruvi (Hodimlar bo'limida), menejerga `teachers.manage` ruxsati bilan.
- Mobil API: guruhlar, guruh ma'lumoti, davomad jadvali va davomad saqlash.

**Eski koddagi kamchiliklar tuzatildi**
- Xona/o'qituvchi band bo'lganda ham guruh ochilardi - endi taqiqlanadi.
- Davomadni istalgan kun uchun saqlash mumkin edi - endi faqat dars kuni va faqat bugun.
- Guruhga qo'shish va chiqarish tranzaksiyasiz edi (ikki marta bosilsa balans buzilardi) - endi qulflangan tranzaksiyada.
- Chegirma qoidasi va jarima chegaralari aniq va testlangan.

**Sifat:** avtomatik testlar 66 tagacha ko'paydi; MariaDB (MySQL) bilan ham sinaldi.

## 3-bosqich (2026-09-22) — To'lovlar, kassa, moliya, ish haqi

**Yangiliklar**
- **To'lovlar:** naqt/plastik qabul qilish, to'lovlar ro'yxati (davr, tur, kassir bo'yicha jami summalar), qarzdorlar; o'quvchi sahifasida to'lov/chegirma/qaytarish paneli.
- **Chegirmalar:** oldindan to'lov chegirmasi (guruh bo'yicha jami to'lov asosida, bir marta), admin chegirmasi (narx rejasidagi chegara bilan), aksiyalar (davr + minimal summa, o'quvchiga bir marta).
- **Qaytarish:** balans va kassa bilan tekshiriladi, admin tasdiqlaydi.
- **Kassa:** chiqim/xarajat so'rovi → tasdiqlash/bekor qilish; bugungi tushum.
- **Moliya:** naqt, plastik va ehson balanslari, ehson foizi, moliyadan chiqim va xarajat, 90 kunlik harakatlar.
- **Ish haqi:** o'qituvchi hisob-kitobi (stavka, bonus, davomad bo'yicha), to'lash, tarix; hodimlar ish haqi.
- Bosh sahifada bugungi tushum va qarzdorlar.
- Mobil API: `GET /me/balance`.

**Eski koddagi kamchiliklar tuzatildi**
- Barcha pul harakati bitta jurnal (`wallet_transactions`) orqali o'tadi; qoldiq qulflab yangilanadi, manfiyga tushmaydi (eski tizimda kassa/balans manfiy bo'lishi mumkin edi).
- Plastik chiqimda ehson foizi naqt balansdan olinib, uni manfiyga tushirardi — endi o'sha chiqim summasidan ajratiladi.
- Chegirma faqat bitta to'lov aynan (narx − chegirma) ga teng bo'lsa berilardi; endi guruh bo'yicha jami to'lov hisobga olinadi va ikki marta berilmaydi.
- Qaytarish balansdan ko'p bo'lishi mumkin edi — endi taqiqlangan.
- Menejer statistikasi hisoblagichlari (qo'lda "tozalash") o'rniga to'lovlar jadvalidan davr bo'yicha hisob (5-bosqichda hisobotlarda).

**Sifat:** 90 ta avtomatik test; 2-bosqichdan yangilash (migratsiya) MariaDB'da mavjud ma'lumotlar bilan sinaldi.

## 4-bosqich (2026-09-23) — Varonka, kurslar, SMS

**Yangiliklar**
- **Varonka:** ochiq murojaat shakli (`/apply/{filial}`, bot va takror yuborishdan himoyalangan), holatlar, izohlar tarixi, o'quvchiga aylantirish (dublikatsiz), manbalar bo'yicha statistika va qabul foizi.
- **Kurslar:** video, audio (fayl yuklash yoki havola), test savollari; kitoblar ro'yxati.
- **O'quvchi testlari:** server tomonida tekshiriladi, natijalar saqlanadi (mobil API).
- **SMS (Eskiz):** filial bo'yicha yoqish/o'chirish, shablonlar, filialning o'z akkaunti yoki umumiy akkaunt, navbat orqali yuborish (3 marta qayta urinish), tarix va xato sabablari, ommaviy yuborish, tug'ilgan kun tabriklari, telefon raqamini avtomatik tekshirish/formatlash.
- Mobil API: kurslar, video/audio, test boshlash/topshirish/natijalar, kitoblar.

**Eski koddagi kamchiliklar tuzatildi**
- Eskiz login/paroli kodga yozib qo'yilgan edi va log fayliga yozilardi — endi faqat `.env`/filial sozlamasida (shifrlangan), logga yozilmaydi.
- Testning to'g'ri javobi mijozga yuborilardi va natijani mijoz o'zi bildirardi — endi server tekshiradi.
- SMS xabar hisoblagichi qo'lda yuritilib manfiyga tushardi (−1009) — endi tarixdan hisoblanadi.
- SMS o'quvchiga "Parol: password" deb yuborilardi — endi har bir o'quvchiga tasodifiy parol.
- Murojaatni ro'yxatga olishda telefon takrorlansa dublikat o'quvchi yaratilardi.
- Filiallar SMS'i standart holatda o'chiq (kutilmagan xarajatning oldini olish uchun).

**Sifat:** 117 ta avtomatik test; 3-bosqichdan yangilash MariaDB'da mavjud ma'lumotlar bilan sinaldi.

## 5-bosqich (2026-09-24) — Statistika, hisobotlar, import, AI

**Yangiliklar**
- **Statistika:** yagona hisoblash manbasi (`StatisticsService`), aniq ta'riflar, davr tanlash, 12 oylik dinamika, filiallar solishtiruvi (sAdmin). Ruxsatga qarab pul ko'rsatkichlari yashiriladi.
- **8 ta hisobot** + Excelga yuklash (o'zimizning .xlsx yozuvchi: sarlavha, ustun kengligi, qotirilgan sarlavha qatori, yig'indilar).
- **Excel import:** namuna fayl, ikki bosqichli (tekshiruv → tasdiq), takror/xato aniqlash, yangi filial ochish (faqat sAdmin, tasdiqdan keyin), boshlang'ich balans, bir martalik login/parol fayli. `.xlsx` va `.csv`, Excel sana raqamlari qo'llab-quvvatlanadi.
- **AI yordamchi (OpenAI):** ruxsat va filial doirasida ishlaydigan tahlilchi (asboblar orqali), ism/telefonsiz, kunlik limit, suhbatlar tarixi; AI bilan test savoli yaratish (ko'rib chiqib saqlash).
- **Mobil API to'liq:** xodimlar uchun o'quvchilar va to'lov qabul qilish, statistika; o'qituvchi uchun ish haqi.

**Eski koddagi kamchiliklar tuzatildi**
- Hisobotlar va statistika turli joylarda alohida hisoblanardi — endi bitta manba, testlar bilan aniq raqamlar tekshirilgan.
- Excel import eski tizimda ma'lumotlarni ko'rib chiqmasdan yozardi va dublikat yaratardi — endi tekshiruv oynasi va takror himoyasi bor.
- Excel uchun tashqi paket kerak emas (Composer xavfsizlik ogohlantirishlari xavfi yo'q).

**Sifat:** 144 ta avtomatik test; MariaDB'da `ONLY_FULL_GROUP_BY` qat'iy rejimida ham sinaldi; hosil qilingan .xlsx fayllar mustaqil kutubxona (openpyxl) bilan tekshirildi.

## v6 (2026-09-25)

**Yangiliklar**
- Summa maydonlarida avtomatik xona ajratish (`1 000 000`); telefon maydonlarida qat'iy `+998 90 123 4567` niqobi.
- Telefon qoidalari: asosiy telefon majburiy, bir filialda bir rolda takrorlanmaydi (DB unique indeks + tekshiruv), rollar orasida ruxsat; import va murojaatlarda avtomatik formatlash.
- Chegirma muddati filial sozlamasida (oldin/keyin kun); guruhga qo'shilganda berilgan chegirma to'lovlarga yoziladi (ikkinchi marta berilmaydi); aksiyalar avtomatik (eng katta bonusli, bir martadan).
- Qarzi bor o'quvchi guruhga (va davomiy guruhga) qo'shilmaydi; balans ko'rinadi.
- Varonka: tanlangan filial bo'yicha forma havolasi, iframe kodi va yengil embed sahifa; har bir murojaat uchun AI tahlili.
- Yordam (AI): rol va ruxsatga mos, tizim qo'llanmasi (`resources/ai/knowledge.md`) asosida; tahlilchi AI ham shu qo'llanmadan foydalanadi.
- Kassa tarixi uchun alohida ruxsat (`cashbox.history`).
- 5 filialli sinov ma'lumotlari (`DemoSeeder`).

**Tuzatildi**
- Guruhga qo'shilganda berilgan chegirma to'lovlar jadvalida yo'q edi, shu sabab keyingi to'lovda ikkinchi marta chegirma berilishi mumkin edi.
- Oldindan to'lov chegirmasi muddati 3 kunga qattiq bog'langan edi.

**Sifat:** 168 ta avtomatik test; v5 → v6 migratsiyasi takrorlangan va noto'g'ri telefonli ma'lumotlar bilan MariaDB'da sinaldi; barcha rollar (admin, menejer, o'qituvchi) bilan 5 filialli sinov ma'lumotlarida qat'iy SQL rejimida sahifalar tekshirildi.

## v7 (2026-09-26)

**Yangiliklar**
- **Ikki bosqichli tasdiqlash:** moliyadan chiqim, xarajat, ehson chiqimi va ish haqi to'lovi «Tekshiring» sahifasidan o'tadi; tasdiq bir martalik, 15 daqiqalik, foydalanuvchiga bog'langan, bajarilishida ruxsat va mablag' qayta tekshiriladi.
- **Ehson naqt/plastik:** yangi hamyonlar `treasury_charity_cash` / `treasury_charity_card`, moliyada jami va bo'linma ko'rinadi, alohida «Ehson chiqimi» (`finance/charity-withdraw`).
- **Qarzdorni istisno tariqasida guruhga qo'shish:** yangi ruxsat `groups.enroll_debtor` (admin, sAdmin), jurnalga yoziladi.
- **Chegirma bir nechta to'lov bilan:** jami to'lov + umumiy qarz guruh narxidan oshmasligi sharti; guruhlar bo'yicha talab yig'ilib boradi (bitta to'lov ikki marta hisoblanmaydi).
- **Varonka voronkasi** va davr filtri; **Statistika** va **Davomad statistikasi** grafikli dashboardlarga aylantirildi (har birida jadval ko'rinishi), Chart.js qo'shildi (`npm`, tayyor `public/build` zip ichida).
- **Yordam** menyusi birlashtirildi (Qo'llanma + Tahlil (AI)); `/ai` eski manzili yo'naltiradi; suhbatlar rejim bo'yicha ajratilgan.
- **Harakatlar jurnali** filial bo'yicha, sAdmin uchun filial filtri; sAdmin harakatlari tanlangan filialga yoziladi.
- **Bosh sahifa kalendari** (dars kunlari, vaqt, xona, guruh, o'qituvchi; guruhga havola).
- **O'quvchi sahifasida** balans harakatlari va tarix bitta jadvalda, to'lov usuli (naqt/plastik) ko'rinadi.

**Ma'lumotlar bazasi** (`2026_09_26_000001_v7_charity_split_payment_link_permissions`, faqat qo'shimcha): `balance_transactions.payment_id` (eski yozuvlar mos to'lovga bog'lanadi), eski ehson qoldig'ini naqt/plastikka bo'lish (jurnal bilan), adminlarga yangi ruxsat, filialsiz jurnal yozuvlarini foydalanuvchi filialiga biriktirish. `down()` ehson qoldig'ini qaytaradi.

**Tuzatildi**
- Ish haqi va moliya amallarida noto'g'ri kiritilgan summani tuzatib bo'lmasdi (endi tasdiqlashdan oldin tekshiriladi).
- Davomad statistikasidagi CSS ustunlar ko'rinmas edi (yangi grafiklar bilan almashtirildi).
- Alohida «AI yordamchi» ro'yxati yordam suhbatlarini ham aralashtirib ko'rsatardi.

**Sifat:** 199 ta avtomatik test (31 ta yangi); MariaDB 10.11 da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami o'tdi; v6 → v7 migratsiyasi 5 filialli sinov ma'lumotlarida (290 ta to'lov bog'landi, ehson bo'lindi) va rollback bilan sinaldi; barcha sahifalar admin va sAdmin bilan, yorug' va qorong'i rejimda tekshirildi.

**Cheklovlar**
- «Guruhlar bo'yicha tushum» grafigi faqat guruh ko'rsatilgan to'lovlarni hisoblaydi (guruhsiz to'lovlar va qaytarishlar kirmaydi).
- Kalendarda o'qituvchi boshqa o'qituvchining guruhini ko'radi, lekin ocholmaydi (mavjud ruxsat qoidasi saqlangan).
- Eski o'quvchi balans yozuvlarini to'lovga bog'lash vaqt (±3 soniya), summa va kassir bo'yicha moslanadi; mos kelmaganlarida usul «—» ko'rinadi.

## v8 (2026-09-28)

**Yangiliklar**
- **Rebrend:** loyiha nomi "ATKO CRM" dan **Edunova CRM**ga o'zgardi — matnlar, logotip, brend rangi, favicon; sana/oy nomlari o'zbekcha (`uz_Latn`) chiqadi.
- **Ikki marta yuborishdan himoya:** pul va boshqa muhim formalar bir necha marta bosilganda bir necha marta bajarilib ketmaydi (`_once` token / `Idempotency-Key`); xavfli amallarda (bekor qilish, storno) oldin tasdiqlash oynasi chiqadi.
- **To'lovni bekor qilish (storno):** xato kiritilgan to'lov, chegirma yoki bonusni admin va sAdmin sabab ko'rsatib bekor qila oladi (`payments.reverse`, mavjud adminlarga avtomatik beriladi) — yozuv o'chirilmaydi, teskari yozuv bilan yopiladi; hisobot va o'quvchi sahifasida to'g'ri (ikki marta hisoblanmasdan) ko'rinadi. **Qaytarishni rad etish:** noto'g'ri yuborilgan qaytarish so'rovi ham sabab bilan rad etiladi.
- **Avtomatik zaxira nusxa** (`php artisan app:backup`, jadval bo'yicha kunlik, eskilari avtomatik o'chadi) va sAdmin uchun **"Tizim holati"** sahifasi: baza/zaxira holati, navbat (queue) va rejalashtiruvchi (cron) ishlayotgani, manfiy balanslar, `APP_DEBUG` production'da yoqiq qolmagani kabi muhim belgilar bir joyda.
- **Operator** — yangi lavozim: menejerga o'xshash mexanizmda, lekin standart holatda torroq (kassa va kurslarsiz — faqat o'quvchi qabul qilish, oddiy to'lov, davomad, lid bilan ishlash). Admin xohlasa kengaytiradi yoki toraytiradi.
- **Kunlik vazifalar paneli:** bosh sahifada rolga mos «bugun e'tibor kerak» ro'yxati (masalan yangi murojaatlar, kelmagan to'lovlar, davomad olinmagan guruhlar); ko'rib chiqilgan vazifani belgilab qo'yish mumkin.
- **Avtomatik SMS:** qarzi bor o'quvchiga eslatma va darsga kelmaganida ota-onaga xabar — ikkisi ham filial sozlamasida alohida yoqiladi/o'chiriladi.
- **Chop etish:** to'lov cheki (kassa printeri uchun 80mm yoki A5) va o'quv shartnomasi (A5, filial rekvizitlari bilan) brauzerdan to'g'ridan-to'g'ri chop etiladi.
- **O'quvchi eslatmalari:** xodimlar o'quvchi profiliga faqat xodimlarga ko'rinadigan ichki eslatma yoza oladi (o'quvchiga ko'rinmaydi).
- **Ommaviy SMS'da oldindan ko'rish:** yuborishdan oldin nechta o'quvchiga qanday matn ketishi ko'rsatiladi, faqat tasdiqlangandan keyin navbatga qo'yiladi.
- **O'tgan kun davomatini tuzatish** (`attendance.edit_past`, alohida beriladigan ruxsat): xato kiritilgan o'tgan kungi davomadni tuzatish, sababi jurnalga yoziladi.
- **Kassa smenasini yopish** (`cashbox.close`, admin/sAdmin): tizim kutgan (jurnal bo'yicha) va xodim qo'lda sanagan haqiqiy naqtni solishtirish; farq (kam/ortiqcha) faqat yoziladi — kassa balansi bu bilan hech qachon o'zgartirilmaydi. **Xarajat turlari:** xarajat so'roviga ixtiyoriy turkum (masalan Ijara, Kommunal) biriktiriladi, pul oqimi hisobotida turkum bo'yicha yig'indi chiqadi.

**Xavfsizlik**
- SMS tarixidagi parollar endi berkitib ko'rsatiladi (avval ochiq matn edi).
- HTTP xavfsizlik sarlavhalari, API so'rovlariga chegaralash (throttle) va `trustProxies` sozlandi (proksi/load-balancer orqasida ham foydalanuvchi IP'i to'g'ri aniqlanadi).
- Faqat HTTPS orqali ishlaganda avtomatik yoqiladigan **HSTS**; `.env.example` production uchun eslatmalar bilan to'ldirildi.
- Uzoq vaqt almashtirilmagan parol uchun foydalanuvchiga eslatma.
- Filtr/qidiruv sahifalari kutilmagan qiymat kiritilganda endi 500-xato bermaydi, tushunarli xabar ko'rsatadi.

**Ma'lumotlar bazasi** (10 ta yangi migratsiya, hammasi qo'shimcha — mavjud ma'lumot buzilmaydi): `submission_tokens`; `payments`ga storno va qaytarishni rad etish ustunlari (+ mavjud adminlarga `payments.reverse` avtomatik); SMS parolini berkitish; `users.password_changed_at`; `users.role` enumiga `operator`; `task_dismissals`; SMS avtomatik xabar bayroqlari; guruh/shartnoma maydonlari; `student_notes`; `expense_categories` + `cash_closings` + `cash_requests.category_id`.

**Tuzatildi**
- To'lovlar hisoboti bir nechta alohida yuruvchi hisob-kitob o'rniga bitta yurishda hisoblaydigan qilib soddalashtirildi (natija bir xil, ancha tezroq).
- Sana bo'yicha filtrlanadigan hisobotlar (to'lovlar, o'quvchilar, lidlar, davomad, ish haqi, pul oqimi) ma'lumotlar bazasi indeksidan to'g'ri foydalanadigan ko'rinishga o'tkazildi — katta hajmda sezilarli tezroq.
- Kichik tuzatishlar: kurslarni ko'rish ruxsati (`courses.view`) aniqlashtirildi.

**Sifat**
- **358 ta avtomatik test**, barchasi o'tdi (filial izolyatsiyasi, ruxsatlar, storno/rad etish, kassa smenasi va xarajat turkumlari, xavfsizlik sarlavhalari, ikki marta yuborishdan himoya va boshqalar uchun yangi testlar bilan).
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami tekshirildi; v7 → v8 migratsiyasi mavjud ma'lumotlar bilan sinaldi.
- **Ishlash tekshiruvi (A8):** 5000 o'quvchi, ~100 000 davomad yozuvi, 15 000 to'lovli sinov ma'lumotida barcha asosiy sahifalar o'lchandi — ko'pchiligi 15-125 ms; ikkita nisbatan sekinroq sahifa (Statistika ~470-490 ms, To'lovlar hisoboti ~400-600 ms) topildi, hisobot sahifasi tezlashtirildi (yuqorida), Statistika shu hajmda ham qabul qilinadigan chegarada qoldi (tez-tez emas, vaqti-vaqti bilan ochiladigan sahifa).

**Cheklovlar**
- O'quv shartnomasi shabloni — namuna matn, yurist tomonidan tasdiqlanmagan; ishlatishdan oldin o'z yuristingizga ko'rsating.
- O'tgan kun davomatini tuzatish o'sha kundagi emas, guruhning joriy tarkibidan foydalanadi.
- Mobil push-bildirishnoma, onlayn (kartadan internet orqali) to'lov va ikki bosqichli kirish (2FA) hali yo'q.
- Statistika sahifasining 5000 o'quvchilik hajmdagi ~470-490 ms yuklanish vaqti tubdan optimallashtirilmagan (admin uchun qabul qilinadigan chegarada deb baholandi).

## v9 (2026-09-29)

**Yangiliklar**
- **Filiallar xodimlari** (`staff.view_all_branches`, yangi ruxsat, standart holatda faqat yangi yaratiladigan Operatorga avtomatik beriladi): barcha filiallar bo'yicha faqat ism, rol va filial ko'rsatilgan, o'qish uchun ro'yxat — o'quvchilar kirmaydi, boshqa ma'lumot (telefon, to'lov, sozlama) ko'rinmaydi. Mavjud, oddiy filial ichidagi «Hodimlar» ro'yxatidan alohida, ataylab tor va faqat o'qish uchun sahifa — filial izolyatsiyasi buzilmaydi.
- **Moliyaga shaxsiy mablag' kiritish** (`finance.deposit`, yangi ruxsat, admin/sAdmin): egasi naqt yoki plastik shaxsiy mablag'ini moliya balansiga kiritadi. Bu o'quvchi to'lovi emas, shuning uchun ehson foizi ajratilmaydi; kirim xavfli emasligi uchun (pul chiqmaydi) boshqa moliya amallaridagi kabi «Tekshiring» bosqichisiz darhol yoziladi va jurnalga yoziladi.
- **Ochiq murojaat (Varonka) sahifasi endi filial brendida:** filial tahrirlash formasida rang (HEX, fayl yuklash yo'q) va qisqa «Filial haqida» matni kiritiladi; `/apply/{filial-kodi}` sahifasida Edunova nomi/logotipi o'rniga shu filialning nomi, rangi va ma'lumoti ko'rsatiladi (rang tanlanmasa, standart rang ishlatiladi). Bir nechta filial bo'lganda filial tanlash sahifasi (`/apply`) o'zgarishsiz qoladi.
- **Guruhga qo'shish ro'yxati** endi guruh nomi bo'yicha alifbo tartibida chiqadi (avval boshlanish sanasi bo'yicha edi).
- **Leadni o'quvchi qilib ro'yxatga olishda guruhga ham qo'shish:** shu forma ichida ixtiyoriy guruh tanlanadi (`groups.members` ruxsati kerak) — bitta amalda o'quvchi yaratiladi va tanlangan guruhga qo'shiladi; guruhga qo'shishda xatolik chiqsa (masalan, band yoki qarz cheklovi), butun amal (o'quvchi yaratish ham) bekor qilinadi.
- **Telefonni bir bosishda nusxalash:** murojaatlar ro'yxati va murojaat sahifasidagi telefon raqami yonidagi tugma raqamni probelsiz (masalan `+998901234567`) buferga nusxalaydi — tashqi platformalarga (masalan qo'ng'iroq markazi) tez joylashtirish uchun.

**Ma'lumotlar bazasi** (`2026_09_29_000001_v9_branch_public_branding`, faqat qo'shimcha): `branches` jadvaliga `brand_color` (nullable, 7 belgi) va `public_about` (nullable, matn) ustunlari qo'shildi. Mavjud filiallarda bu maydonlar bo'sh qoladi — ochiq murojaat sahifasida standart rang va matnsiz ko'rinish ishlatiladi.

**Sifat**
- **376 ta avtomatik test** (18 tasi yangi: filiallar xodimlari, moliyaga shaxsiy mablag' kiritish, guruh saralash, lead→o'quvchi+guruh, telefon nusxalash, filial brendlashi), barchasi o'tdi.
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami (376 ta) qayta tekshirildi.

**Cheklovlar**
- Filial rangi va «haqida» matni faqat sAdmin tomonidan kiritiladi (admin o'zgartira olmaydi) — mavjud filial boshqaruvi qoidasiga mos.
- "1Call.uz" qo'ng'iroq-markazi bilan webhook integratsiyasi (shartnoma jarayonida) v9'da **ISHLATILMAYDI** — foydalanuvchining talabiga ko'ra ataylab qoldirildi. V10'da qo'shilishi mo'ljallangan; mavjud arxitektura (murojaatlar allaqachon manba/izoh saqlaydi, yangi endpoint va ruxsatlar additiv migratsiya bilan qo'shiladi) buni bloklamaydi, lekin webhook autentifikatsiyasi, tashqi ID moslashtirish va xato holatlari kelishuv tugagach alohida loyihalanadi.

## v10 (2026-09-23)

**Yangiliklar**
- **Filiallarni faqat ko'zdan kechirish** (`staff.view_all_branches` egalari uchun, odatda Operator): «Filiallar xodimlari» sahifasidagi har bir filial yonida yangi tugma — bosilganda sAdmin kabi istalgan filialning o'quvchi, guruh va lidlar ro'yxati ochiladi. Bu **faqat o'qish uchun**: sahifada birorta ham forma yoki amal tugmasi yo'q, va ko'rib chiqish paytida xodimning haqiqiy ishchi filiali (`BranchContext`, ya'ni yozish huquqi) hech qachon o'zgarmaydi — yozuv huquqi boshqa filialga "sizib chiqish" xavfisiz, asosiy filial cheklovidan mustaqil, alohida o'qish yo'li orqali amalga oshirilgan.
- **Guruhni davom ettirishda qarzdorlarni o'tkazish:** `groups.enroll_debtor` ruxsati bor admin/sAdmin endi manfiy balansli o'quvchini ham yangi ochilayotgan davom guruhiga istisno tariqasida o'tkaza oladi (avval bunday o'quvchilar avtomatik cheklab qo'yilardi); ruxsati yo'q xodim uchun eski xatti-harakat o'zgarmadi.
- **Moliya sahifasi qatorlarga qayta tartiblandi:** «Balansdan chiqim», «Xarajat», «Shaxsiy mablag' kiritish» — bir qatorda teng kenglikda; «Ehson chiqimi», «Ehson foizi» — ikkinchi qatorda teng kenglikda. Tor ekranda noqulay bo'lib qolayotgan joylashuv tuzatildi.
- **Xarajat turkumi Moliyada ham:** avval faqat Kassa xarajat so'rovida bor edi (`expense_categories`), endi Moliya → Xarajat formasida ham ixtiyoriy turkum tanlanadi; `wallet_transactions` jadvaliga yangi `category_id` ustuni qo'shildi, pul harakati tarixida turkum nomi ko'rinadi.
- **Navbat (queue) ishchisi alohida tekshiriladi:** «Tizim holati» sahifasida cron bilan bir qatorda yangi qator — `php artisan queue:work` haqiqatan ishlab turganini (shunchaki navbatga topshirilganini emas) tasdiqlaydi: signal faqat vazifa haqiqatan BAJARILGANDA yangilanadi (`Schedule::job()`, `Schedule::call()` emas). Serverga chiqarilganda "qaysi qism ishlamayapti" savoliga tezroq javob beradi.
- **Filial rangini tanlashda qiymat ko'rinadi:** avval native rang tanlagich Tailwind stili bilan deyarli ko'rinmas edi; endi maxsus `.color-swatch` stil va tanlangan rangning HEX qiymati matn sifatida yonida aniq ko'rinadi.
- **Bosh sahifa yangilandi:** «Bugungi qarzdorlar» va «24 soatdan ortiq javobsiz lidlar» panellari endi scroll bo'ladi (ro'yxat uzun bo'lsa sahifa cho'zilib ketmaydi); yangi qator — faol guruhlar va boshlanishi kutilayotgan («boshlanmagan») guruhlar soni, har birida jami (faol) o'quvchilar soni bilan.
- **Filialni butunlay o'chirish** (`BranchDeletionService`, faqat sAdmin): «Filiallar» sahifasidagi yangi «Butunlay o'chirish» tugmasi filial va unga tegishli **BARCHA** ma'lumotni (hodim, o'quvchi, guruh, davomad, to'lov, kassa, lid, SMS, AI suhbat, kurs kontenti va h.k.) bitta tranzaksiyada, FK bog'liqligiga mos qat'iy tartibda, qaytarib bo'lmaydigan tarzda o'chiradi. Xato bosishdan himoya: filial nomini ANIQ (harfma-harf) qayta yozib tasdiqlash shart, aks holda hech narsa o'zgarmaydi. **Yagona istisno** — harakatlar jurnali (`audit_logs`): mavjud sxemadagi `nullOnDelete()` andozasiga mos, bu jadval o'chirilmaydi, faqat o'sha yozuvlarning `branch_id`si bo'shatiladi (o'chirish harakatining o'zi ham jurnalga yoziladi).
- **Sinov ma'lumotlari kichraytirildi:** `DemoSeeder` endi 5 ta emas, **2 ta filial** ("Test filiali", "Qarshi filiali") va sezilarli kamroq hajmda ma'lumot (15 o'quvchi, 2 o'qituvchi, 3 guruh har birida) yaratadi — tezroq ishlaydi va tekshirish osonroq. Bu sof demo/sinov muhiti uchun; production bazasiga (`db:seed`, sAdmin muhitiga) ta'siri yo'q.

**Ma'lumotlar bazasi** (1 ta yangi migratsiya, qo'shimcha): `wallet_transactions`ga `category_id` (nullable, `expense_categories`ga `nullOnDelete`).

**Sifat**
- **400 ta avtomatik test** (24 tasi yangi: filialni faqat ko'rish, guruhni davom ettirishda qarzdor istisnosi, Moliya xarajat turkumi, navbat tekshiruvi, filial rangi ko'rinishi, bosh sahifa guruh statistikasi va scroll, filialni butunlay o'chirish — to'liq o'chirish, boshqa filialga tegilmasligi, audit jurnali saqlanishi, faqat sAdmin, nom tasdiqlash), barchasi o'tdi.
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami (400 ta) qayta tekshirildi; filialni o'chirish real MariaDB bazasida (2 ta demo filial bilan) qo'lda ham sinaldi — faqat kerakli filial ma'lumoti o'chdi, ikkinchisi to'liq saqlanib qoldi.

**Cheklovlar**
- Filialni butunlay o'chirish operatsiyasi katta hajmdagi filial uchun (o'nlab minglab yozuv) bir necha soniya davom etishi mumkin — sahifa progress-bar ko'rsatmaydi, faqat tugagach natija chiqadi.
- Filiallarni faqat ko'rish sahifasida qidiruv/filtr yo'q — ro'yxatlar 15 tadan sahifalanadi (katta filialda ko'p sahifa kerak bo'lishi mumkin).
- "1Call.uz" qo'ng'iroq-markazi bilan webhook integratsiyasi hali ham **ISHLATILMAYDI** — shartnoma jarayoni tugamagan (v9'dagi cheklov o'zgarishsiz qoladi).

## v11 (2026-09-23)

**Yangiliklar — Mobil ilova (Flutter) uchun to'liq API**
- **Xodimlar bo'limi mobil API'da to'liq:** v10'da mobil API faqat o'quvchi/o'qituvchi uchun edi (balans, ish haqi, kurslar, davomad). v11'da admin/menejer/operator ham (ruxsatiga qarab) mobil orqali ishlay oladi: bosh sahifa vazifalari (`/dashboard/todo`), Varonka/lidlar (ro'yxat, qo'shish, izoh, bekor/qayta ochish, o'quvchiga aylantirish), Kassa (chiqim/xarajat so'rovi yaratish, ko'rish, tasdiqlash/bekor qilish), Moliya (umumiy holat ko'rish, shaxsiy mablag' kiritish), Hodimlar (ro'yxat, qo'shish, tahrirlash, ish haqi ko'rish), boshqa filiallarni faqat ko'rish (`staff.view_all_branches`), guruhga o'quvchi biriktirish/chiqarish, SMS (tarix, ommaviy yuborishni oldindan ko'rish). Har bir endpoint xuddi veb-sahifadagi bilan **bir xil ruxsat tekshiruvi va filial cheklovi**dan foydalanadi (asosan mavjud servislar — `LeadService`, `CashboxService`, `FinanceService`, `EnrollmentService` va h.k. — qayta ishlatildi, mantiq ikki marta yozilmadi).
- **Ataylab mobilga CHIQARILMAGAN amallar** (xavfsizlik uchun, faqat veb'da qoladi): moliyadan pul CHIQARISH (balansdan chiqim, xarajat, ehson chiqimi), ish haqi TO'LASH, ommaviy SMS'ni haqiqatan YUBORISH, kassa smenasini yopish, qaytarishni tasdiqlash/rad etish. Bularning barchasi veb'da ataylab ikki bosqichli tasdiqlash ("Tekshiring" sahifasi) bilan himoyalangan yuqori xavfli amallar — shu himoya darajasi mobilda hali yo'q, shuning uchun ataylab qoldirilgan.
- **O'quvchi uchun "Mening guruhlarim" va umumiy davomat:** `/me/groups` — o'quvchining barcha guruhlari (faol/tugagan) bitta ro'yxatda, holati bilan; `/me/attendance` — barcha guruhlar bo'yicha umumlashtirilgan davomat tarixi va foizi.
- **sAdmin mobil ilovada filial almashtirish (`X-Branch-Id`):** mobil ilova sessiya (cookie) ishlatmaydi, shuning uchun sAdmin har so'rovda `X-Branch-Id: <filial_id>` sarlavhasini yuborib qaysi filial bilan ishlashini ko'rsatadi (veb'dagi filial tanlagichning mobil muqobili). Sarlavha bo'lmasa, yozish amallari uchun `422` xatosi qaytadi; o'qish uchun bo'lsa — barcha filiallar ma'lumoti qaytadi. Boshqa hech qanday rolga bu sarlavha ta'sir qilmaydi (testlangan: `V11BranchHeaderTest`).

**Yangiliklar — To'liq push bildirishnoma (Firebase/FCM)**
- **sAdmin uchun yangi «Bildirishnomalar» bo'limi (veb):** sarlavha va matn yozib, «barchaga» yoki bitta filialga (filtrlab) push+ilova-ichi xabar yuboriladi. Yuborilgan xabarlar tarixi — har biri necha kishiga yetgani va nechtasi o'qilgani bilan.
- **Mobil API orqali bildirishnomalarni ko'rish:** `GET /me/notifications` — foydalanuvchiga yuborilgan xabarlar ro'yxati (o'qilgan/o'qilmagan holati bilan); `POST /me/notifications/{id}/read` va `/me/notifications/read-all` — o'qilgan deb belgilash.
- **Haqiqiy push (Firebase Cloud Messaging, HTTP v1 API):** qo'shimcha Composer paketsiz, native `openssl_sign`/`openssl_pkey_*` funksiyalari bilan xizmat hisobi (service account) JWT'si qo'lda imzolanadi va Firebase'ga so'rov yuboriladi. Har bir qabul qiluvchiga alohida fon vazifa (`SendPushNotificationJob`, 3 marta urinish) orqali yuboriladi — bittasi muvaffaqiyatsiz bo'lsa, qolganlariga ta'sir qilmaydi. Qurilma tokeni noto'g'ri/eskirgan bo'lsa (`UNREGISTERED`), avtomatik o'chiriladi.
- **Firebase sozlanmasa ham ishlaydi:** `.env`da `FIREBASE_PROJECT_ID`/`FIREBASE_CREDENTIALS_PATH` bo'lmasa, xabar baribir `notification_recipients` jadvaliga yoziladi va ilova ichidagi ro'yxatda ko'rinadi (`push_status = skipped`), faqat telefon ekraniga chiqmaydi — funksiya haqiqiy Firebase hisobisiz ham to'liq test qilinadi.
- **Qurilma tokeni ro'yxatdan o'tkazish:** `POST /me/device-token` (login'dan keyin Flutter tomonidan chaqiriladi), `DELETE /me/device-token`; chiqishda (`/auth/logout`) shu qurilmaning tokeni avtomatik o'chiriladi.

**Ma'lumotlar bazasi** (2 ta yangi migratsiya, faqat qo'shimcha):
- `device_tokens` — foydalanuvchi qurilmasining push tokeni (`user_id`, `token`, `platform`, `device_name`; bitta foydalanuvchi + qurilma nomi bo'yicha unikal).
- `notifications` + `notification_recipients` — yuborilgan bildirishnoma va har bir qabul qiluvchining o'qilgan holati/push holati.

**API hujjati**
- **`API_DOC.md`** — yangi, to'liq hujjat: barcha endpointlar (so'rov/javob JSON namunalari bilan), autentifikatsiya oqimi, xato formati, `X-Branch-Id` mexanizmi, Firebase sozlash qadamlari.

**Sifat**
- **429 ta avtomatik test** (29 tasi yangi: xodimlar mobil API'si — bosh sahifa, lidlar, kassa, moliya, hodimlar, boshqa filial, guruh-o'quvchi, SMS — 12 ta; o'quvchi mobil API'si — profil, guruhlar, davomat, xodimga taqiqlangan — 4 ta; bildirishnoma va push — sAdmin yuborish, ruxsatsiz taqiqlanishi, qurilma tokeni, chiqishda o'chirish, FCM integratsiyasi (soxta HTTP javob bilan), push holati — 7 ta; `X-Branch-Id` sarlavhasi — sAdmin bilan/bilansiz, yozish uchun majburiylik, boshqa rollarga ta'sir qilmasligi, veb sessiyaga tegilmasligi — 6 ta), barchasi o'tdi.
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami (429 ta) qayta tekshirildi.
- Real MariaDB bazasida (seed qilingan ma'lumot bilan) qo'lda ham sinaldi: bildirishnoma qabul qiluvchilar soni, sAdmin istisnosi, push holatlari to'g'ri ishlashi tasdiqlandi.

**Cheklovlar**
- Push xabar ishlashi uchun Firebase hisobi va xizmat kaliti (service account JSON) serverga qo'lda joylashtirilishi kerak — bu loyihaga kiritilmagan (har mijozning o'z Firebase loyihasi bo'ladi). Sozlash qadamlari: `README.md`, "Firebase (push bildirishnoma) sozlash".
- Flutter mobil ilovaning o'zi bu loyihaga kirmaydi — faqat server tomoni (API) tayyorlandi.
- Moliyadan pul chiqarish, ish haqi to'lash, ommaviy SMS yuborish, kassa smenasini yopish, qaytarishni tasdiqlash/rad etish hamon faqat veb'da (yuqorida sabab bilan izohlangan).
- Kurs materiallari (video/audio/test/kitob) va davomad olish/statistika API'lari v10'dan o'zgarishsiz qoldi — v11 faqat yangi bo'limlar (xodim, bildirishnoma) qo'shdi.

## v12 (2026-09-23)

**Yangiliklar — Mobil ilova**
- **Parolni unutgan (SMS-OTP):** `POST /auth/forgot-password` — login/email kiritilsa, shu hisobning telefoniga `SmsService` orqali 6 xonali kod yuboriladi (5 daqiqa amal qiladi, `password_reset_codes` jadvalida `Hash::make()` bilan xeshlangan holda saqlanadi, 5 marta xato urinishdan keyin bekor bo'ladi); `POST /auth/reset-password` — kod va yangi parol bilan tiklanadi, muvaffaqiyatli tiklanganda mavjud mobil tokenlar bekor qilinadi. Qidiruv faqat login/email orqali (telefon bo'yicha emas — bitta telefon bir necha rolda takrorlanishi mumkin, shuning uchun noaniqlik va hisob-sanash xavfining oldi olingan). **sAdmin uchun qo'llab-quvvatlanmaydi** (aniq bitta filiali yo'q, SMS filial orqali yuboriladi) — bu ataylab qilingan cheklov, kodda va hujjatda izohlangan.
- **Profilni tahrirlash:** `POST /me/profile` (multipart) — o'quvchi va xodim telefon raqami (`UzPhone`+`UniquePhonePerRole` bilan tekshiriladi) va profil rasmini (`Storage::disk('public')`, eski rasm avtomatik o'chiriladi) o'zgartira oladi; `remove_photo` bilan rasmni olib tashlash ham mumkin. F.I.O. va login mobil orqali o'zgartirilmaydi. Har bir o'zgarish `AuditLog`ga yoziladi.
- **Bildirishnoma deep link:** `notifications` jadvalidagi mavjud `data` ustuni endi veb compose formasida ham ishlatiladi — ixtiyoriy «Havola» (`link_type`: `payment`/`announcement`/... , `link_id`) tanlansa, FCM payload'ga ham qo'shiladi va mobil ilova bosilganda tegishli sahifaga o'tkazadi. Orqaga qarab mos: eski (havolasiz) xabarlar o'zgarishsiz ishlayveradi.
- **Ilova versiyasi tekshiruvi:** `POST /app-version/check` (`platform`, `version`) — `app_versions` jadvali (Android/iOS uchun alohida qator: `min_version`, `latest_version`, `update_url`, `message`) bilan solishtirib `force_update`/`update_available` belgisini qaytaradi. Yangi sAdmin sozlama sahifasi — **Ilova versiyasi** (yon menyu): ikkala platforma uchun alohida forma, `min_version <= latest_version` tekshiruvi bilan.

**Yangiliklar — sAdmin va tizim**
- **Filial-sarlavha birlashtirildi:** `POST /staff` (xodim qo'shish) avval sAdmin uchun so'rov tanasidagi alohida `branch_id` maydonidan foydalanardi; endi boshqa barcha sAdmin yozish amallari kabi `X-Branch-Id` sarlavhasidan foydalanadi — mobil dasturchi uchun bitta izchil qoida.
- **Navbat (queue) race condition tuzatildi:** `config/queue.php`da barcha ulanishlar uchun `after_commit => true` o'rnatildi — avval bildirishnoma yuborilgandan darhol keyin fon vazifa (push yuborish) ba'zan yozuv tranzaksiyasi hali commit bo'lmasdan ishga tushib, "topilmadi" xatosiga olib kelishi mumkin edi.
- **FCM "sozlanganmi" aniqlashi mustahkamlandi:** `SendPushNotificationJob` avval xato matnini satr sifatida solishtirar edi (`FcmService`dagi matn o'zgarsa, sinardi); endi `FcmService::isConfigured()` orqali aniq tekshiriladi.
- **Davomad tarixi so'rovi tezlashtirildi:** `AttendanceService::studentSummary()` — mobil `/me/attendance` endi butun matritsani emas, faqat kerakli o'quvchi/guruh bo'yicha yig'indini (`COUNT`/`SUM`) so'raydi.
- **Zaxira nusxa diagnostikasi:** `app:backup` endi har urinishdan keyin (muvaffaqiyatli yoki yo'q) `storage/app/backups/.status.json`ga natija va xato sababini yozadi (`mysqldump` topilmadi, `proc_open` o'chirilgan va h.k.); «Tizim holati» sahifasi shu faylni o'qib aniq sababni ko'rsatadi (avval faqat "hali zaxira yo'q" umumiy xabar chiqardi). Yangi **«Hozir zaxira olish»** tugmasi (sAdmin) navbatni kutmasdan darhol ishga tushiradi.
- **`/docs` — onlayn API hujjati:** `API_DOC.md` endi `/docs` manzilida (kirish talab qilinmaydi) o'qiladi — tashqi Composer paketsiz, o'zimizning `App\Support\MarkdownRenderer`i orqali. sAdmin menyusiga «API hujjati» havolasi qo'shildi (yangi oynada ochiladi).
- **Postman kolleksiyasi yangilandi:** `docs/Edunova_CRM_API.postman_collection.json` v12'dagi barcha 60 ta endpoint'ni qamrab oladi (bearer token avtomatik saqlanadi, `X-Branch-Id` sAdmin so'rovlarida ixtiyoriy sarlavha sifatida tayyor). `tests/Unit/PostmanCollectionTest.php` haqiqiy marshrutlar (`route:list`) bilan avtomatik solishtiradi.
- **Zip paketlash xatosi tuzatildi (v7'dan beri mavjud edi):** `public/storage` (odatda `storage:link` bilan yaratiladigan symlink) barcha oldingi zip'larda BO'SH PAPKA sifatida joylashtirilgan edi — bu esa `php artisan storage:link`ni "link already exists" xatosi bilan to'xtatib qo'yardi (audio/rasm fayllar ko'rinmay qolishi mumkin edi). v12'dan boshlab bu papka zip'ga umuman kiritilmaydi, `storage:link` toza o'rnatishda muammosiz ishlaydi. Aniqlangan usul: yangi zip'ni toza papkaga ochib, `composer install` + `migrate` + `storage:link`ni boshidan sinab ko'rish orqali (loyiha qoidasidagi majburiy tekshiruv qadami).
- **Hujjat tuzatildi (v11'dan qolgan xato):** `API_DOC.md`da bildirishnoma manzillari noto'g'ri `/me/notifications` deb yozilgan edi — haqiqiy (va v11'dan beri shunday ishlagan) yo'l `/notifications`. Shuningdek, avval umuman hujjatlanmagan `GET /branches` endpoint'i qo'shildi.

**Ma'lumotlar bazasi** (3 ta yangi migratsiya, faqat qo'shimcha):
- `password_reset_codes` — parolni tiklash kodlari (`user_id`, `code_hash`, `attempts`, `expires_at`, `used_at`).
- `users` jadvaliga `photo_path` (nullable) ustuni — profil rasmi.
- `app_versions` — platforma (unique), minimal/so'nggi versiya, do'kon havolasi, xabar matni; migratsiya android/ios qatorlarini avtomatik yaratadi.

**Sifat**
- **486 ta avtomatik test** (57 tasi yangi: parolni unutish/tiklash — 9, profilni tahrirlash — 6, bildirishnoma deep link — 5, ilova versiyasi — 8, zaxira diagnostikasi — 5, `/docs` sahifasi — 4, Markdown render'i — 9, Postman kolleksiyasi qamrovi — 2, sAdmin xodim qo'shishda `X-Branch-Id` — 4, FCM haqiqiy xatolik/eskirgan token holati — 2, bildirishnoma yuborish jurnali — 2, navbat `after_commit` sozlamasi — 1), barchasi o'tdi.
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami (486 ta) qayta tekshirildi — shu tekshiruv davomida bitta sinov faylidagi (`V12NotificationDeepLinkTest`) operator ustuvorligi xatosi (`&&` ustuvorligi tufayli noto'g'ri qiymat yozilishi) topildi va tuzatildi: SQLite'da chet kalit (foreign key) tekshiruvi yo'qligi sababli bu xato sezilmay qolgan edi, MariaDB'da chet kalit haqiqatan tekshirilgani uchun aniqlandi. Bu — production kodida emas, faqat sinov faylida bo'lgan xato edi.
- `MarkdownRenderer`dagi cheksiz tsikl xatosi (`API_DOC.md`dagi oddiy matn ichidagi `|` belgisi jadval deb noto'g'ri aniqlanib, paragraf tsiklini to'xtatib qo'yardi) haqiqiy hujjat ustida `timeout` bilan sinab topildi va tuzatildi; qo'shimcha xavfsizlik chizig'i (`$i` albatta oldinga siljishini kafolatlovchi tekshiruv) qo'shildi.

**Cheklovlar**
- Parolni unutish/tiklash faqat login/email orqali ishlaydi va sAdmin uchun mavjud emas (yuqorida izohlangan).
- Profil rasmini yuklash uchun serverda `php artisan storage:link` bajarilgan bo'lishi shart (README'da ko'rsatilgan).
- Ilova versiyasi tekshiruvi faqat ilova o'zi so'rov yuborsa ishlaydi — eski, hali yangilanmagan ilova versiyalari serverga umuman ulanmasa, bu haqda ma'lumot yo'q.
- `/docs` sahifasi ochiq (kirish talab qilinmaydi), chunki maqsadli auditoriya (tashqi mobil dasturchi) CRM hisobiga ega emas; qidiruv tizimlariga ko'rinmasligi uchun `noindex` belgilangan, lekin havolani bilgan har kim o'qiy oladi (maxfiy ma'lumot yo'q, faqat texnik API tavsifi).

## v13 (2026-10-08)

**Yangiliklar**
- **Eslatmalar qo'ng'iroqchasi:** yuqori panelda filialdagi faol o'quvchi eslatmalari soni va ro'yxati (har 15 soniyada yangilanadi); eslatmani o'chirmasdan «Yopish»/«Qayta ochish» (hamma uchun umumiy), son shunga qarab o'zgaradi.
- **Yangi ruxsat `payments.view_totals`:** To'lovlar sahifasidagi umumiy yig'indilarni alohida ko'rsatish/yashirish. Migratsiya mavjud adminlarga uni beradi; menejer/operatorga sAdmin yoki admin alohida beradi.
- **Lavozimni o'zgartirish:** hodimni tahrirlashda (ruxsatlar yangi lavozim shabloniga qaytadi, mobil seanslar tugaydi; tugamagan guruhli o'qituvchi uchun bloklanadi).
- **Bir nechta sAdmin:** Tizim → Super adminlar (qo'shish, bloklash/faollashtirish, olib tashlash; o'zini va oxirgi faol sAdmin'ni himoya).
- **Qo'shimcha filialli operator:** sAdmin operatorga qo'shimcha filiallarni berib/olib tashlaydi (`user_branches` jadvali); operator filial tanlagich (va mobil `X-Branch-Id`) orqali faqat ruxsat etilgan filiallarga o'tadi, boshqa filial yozuvlari ko'rinmaydi.
- **Hodim faoliyati statistikasi:** admin/menejer/operator ish haqi kartochkasida kunlik (30 kun) va oylik (12 oy) to'lovlar soni/summasi, murojaatlar, izohlar, o'quvchilar.
- **Tushum dinamikasi:** Statistikada kun/hafta/oy bo'yicha aniq dinamika, «Bugun/Hafta/Oy» kartalari va o'tgan davr bilan solishtirish.
- **Yon menyu:** bo'limlarga guruhlangan, yig'iladigan/ochiladigan menyu (holat brauzerda eslab qolinadi).

- **Kassa ruxsatlari ajratildi:** «Xarajat so'rovi» (`cashbox.request`) va yangi «Chiqim so'rovi» (`cashbox.withdraw`, moliya balansiga o'tkazish) alohida. Migratsiya `2026_10_08_000004` mavjud `cashbox.request` egalariga `cashbox.withdraw` ni ham beradi (imkoniyat o'zgarmaydi). Yangi menejerga standart shablon faqat xarajatni beradi. Mobil API: `kind=withdrawal` endi `cashbox.withdraw` talab qiladi.

- **To'liq standart shartnoma:** 10 bo'limli o'quv xizmati shartnomasi (predmet, narx va to'lov, davomat, huquq-majburiyatlar, bekor qilish va pulni qaytarish, shaxsiy ma'lumotlar, fors-major, nizolar, rekvizitlar). Sozlamalar → Shartnoma sahifasida standart matn endi oynada ko'rinadi (avval oyna bo'sh turardi) va tahrirlash mumkin. O'zi matn saqlagan filiallarga ta'sir qilmaydi.
- **Shartnoma chop etish ko'rinishi:** endi rasmiy hujjat ko'rinishida — A4, Times New Roman 12 pt, ikki chetga tekislangan bandlar, markazdagi qalin sarlavhalar, imzo bloki ikki ustunli jadvalda, sahifa raqamlari. STIR yoki direktor ismi kiritilmagan bo'lsa, qo'lda to'ldirish uchun chiziq (____) chiqadi. Oddiy matnli eski/maxsus shablonlar ham shu ko'rinishda chiqadi.

- **Guruhni to'liq tahrirlash:** xona, dars vaqti, dars kunlari, boshlanish sanasi, darslar soni va narx rejasi. O'tgan va davomad olingan darslar o'zgarmaydi, kelgusi darslar qayta tuziladi (bandlik tekshiriladi). Narx o'zgarsa faol o'quvchilar balansi servis orqali jurnalga yozib to'g'rilanadi (tasdiq bilan). Yangi ruxsat `groups.change_price` (migratsiya `2026_10_08_000005` mavjud adminlarga beradi).

**Tuzatishlar**
- Filial almashtirilganda oldingi filialning sahifasida (masalan, `/groups/6`) 404 chiqardi: endi filial almashganda har doim bosh sahifaga o'tiladi.
- Operatorga ish haqi to'lashda chiqadigan 404 xatosi (tasdiqlash sahifasi) tuzatildi.

**Yangilash:** `php artisan migrate`, `npm install && npm run build`, `php artisan test`. Yangi migratsiyalar: `2026_10_08_000001..000005`.

### v13.1 — Guruh tahriri qoidalari aniqlashtirildi
- **Yakunlangan guruh** (tugash sanasi o'tgan): xona, dars vaqti, dars kunlari, boshlanish sanasi va darslar soni o'zgarmaydi (forma maydonlari o'chiriladi, server ham rad etadi). Nom, kurs, o'qituvchi, stavka va narx tahrirlanadi.
- **Jarayondagi guruh**: boshlanish sanasi o'zgarmaydi; vaqt/xona/kunlar o'zgarsa faqat bugungi (davomad olinmagan) va kelgusi darslar yangilanadi, o'tgan darslar eski vaqtida qoladi. Davomad olingan bugungi dars o'zgarmaydi.
- **Boshlanmagan guruh**: boshlanish sanasi faqat bugun yoki keyingi kunlarga ko'chiriladi (forma `min` bilan ham cheklangan).
- Migratsiya yo'q. Testlar: `V13GroupEditTest`.
- Tuzatish: `phpunit.xml` ga `APP_URL=http://localhost` qo'shildi (testlar `.env` dagi manzilga bog'liq emas). Tasodifan o'chib ketgan mobil API kontrollerlari (`Group`, `Staff`, `Statistics`, `Finance`) qayta tiklandi, `GET /statistics/charts` qo'shildi, `routes/api.php` v13 (ikki bosqichli chiqim) holatiga keltirildi. Guruh formasidagi Blade sintaksis xatosi tuzatildi.

### v13.2 — sAdmin maxsus chegirmasi
- O'quvchi kartochkasida **«Maxsus chegirma»** bo'limi (faqat sAdmin): guruhga bog'lanmagan, narx rejasidagi `max_discount` bilan cheklanmagan bonus — kam ta'minlangan o'quvchilar uchun. Bitta chegirma **1 000 000 so'mdan oshmaydi**, sabab majburiy.
- Ikki bosqichli: «Tekshiring» sahifasida **sAdmin paroli** so'raladi (noto'g'ri parol bilan bajarilmaydi, 5 ta urinishdan keyin 10 daqiqa kutiladi).
- Faqat o'quvchi balansini oshiradi (`PaymentService::specialDiscount`, jurnalga yoziladi), kassadan pul chiqmaydi, SMS yuborilmaydi. Statistika va hisobotlarda «Chegirmalar» qatoriga kiradi, storno qilinadi.
- Ruxsatlar ro'yxatida yo'q: boshqa foydalanuvchiga (admin ham) berib bo'lmaydi. Mobil API'ga chiqarilmagan. Mavjud admin chegirmasi o'zgarmagan. Migratsiya yo'q.

### v13.3 — Boshlanmagan guruhni o'chirish (arxivlash)
- Guruh sahifasida «O'chirish»: faqat admin va sAdmin (yangi `groups.delete` ruxsati, mavjud adminlarga migratsiya bilan beriladi; sAdmin boshqaradi).
- Faqat boshlanmagan (boshlanish sanasi kelmagan), davomad olinmagan, faol o'quvchisiz guruh. Sabab majburiy, parol bilan tasdiqlanadi, harakatlar jurnaliga yoziladi.
- Soft delete (`groups.deleted_at`, `deleted_by`, `delete_reason`): ro'yxatdan yo'qoladi, dars kunlari bo'shatiladi, tarix saqlanadi. Boshqa filial guruhi — 404. Mobil API'ga chiqarilmagan.
- Yangilash: `php artisan migrate` (yangi migratsiya `2026_10_10_000001`).

### v13.4 — Ro'yxatlar, tablar, ro'yxatga olgan xodim
- **№ raqamlash**: O'quvchilar, Guruhlar ro'yxatlarida, guruh ichidagi o'quvchilar, davomad matritsasi va davomad olish ro'yxatida ketma-ket raqam (sahifalashda davom etadi).
- **Guruh sahifasi**: «O'quvchilar» tabi «Davomad»dan oldin. Bugun dars bo'lib davomad olish mumkin bo'lsa «Davomad» ochiq, aks holda «O'quvchilar».
- **Davomad olishda balans**: har bir o'quvchi yonida balansi ko'rinadi (o'qituvchidan tashqari hamma uchun; o'tgan kunni tuzatish sahifasida ham).
- **Qatorni bosib ochish**: O'quvchilar, Guruhlar, guruh o'quvchilari, Ish haqi, Murojaatlar, To'lovlar, Import, Hodimlar (tahrirlash huquqi bo'lsa) ro'yxatlarida butun qator bosiladi (Ctrl+bosish — yangi oyna). Ish haqi ro'yxatidagi «Ochish» tugmasi olib tashlandi.
- **Ro'yxatga olgan xodim**: yangi `users.created_by` va `users.registered_via` ustunlari (additive). Eski o'quvchilar uchun harakatlar jurnalidan best-effort to'ldiriladi (topilmasa bo'sh qoladi, hech narsa o'chmaydi). O'quvchi kartochkasi va «Yangi o'quvchilar hisoboti»da «Ro'yxatga olgan» ustuni; Excel import orqali kelganlar «Import (Excel)» deb belgilanadi, hisobot xulosasida «Import orqali» soni bor.
- **Hodim faoliyati**: «Ulardan to'lov qilganlar» (soni va to'lov summasi) — hodim ro'yxatga olgan o'quvchilardan to'lov qilganlar (storno qilingan to'lovlar hisobga olinmaydi).
- Yangilash: `php artisan migrate` (yangi `2026_10_17_000001`), `npm run build` shart emas (yangi CSS klass yo'q). Testlar: `V13ListsAndRegistrarTest`.

### v13.5 — O'qituvchi «Usluga» (staj ustamasi)
- Yangi **Sozlamalar → Usluga**: 4 pog'ona (0–6, 6–12, 12–18, 18 oydan keyin) uchun qat'iy «o'quvchi usluga» va «bonus usluga» summalari hamda «Usluga qo'llanadigan sana» (masalan, 01.10.2026). O'zgartirish faqat sAdmin; admin (`settings.branch`) ko'ra oladi. Harakatlar jurnaliga yoziladi.
- Hodim formasiga **«Ishga olingan sana»** (`users.hired_at`); o'zgarishi jurnalga yoziladi (`staff.hired_at_changed`).
- Ish haqi (o'qituvchi sahifasi): yangi ustunlar «Usluga (o'quvchi)» = o'quvchilar × summa, «Usluga (bonus)» = bonusli × summa va «Jami». Hozirgi hisob-kitob (`o'quvchi × stavka + bonusli × bonus`) o'zgarmagan, usluga ustiga qo'shiladi. «Davomad bo'yicha» usluga bilan birga mutanosib.
- Pog'ona guruh boshlangan sanadagi staj bo'yicha tanlanadi; guruh boshlangach summa guruhga qotiriladi (keyingi sozlama/sana o'zgarishi hisobga ta'sir qilmaydi). Sanadan oldin boshlangan guruhlarda usluga 0 — eski hisob va to'langan ish haqi o'zgarmaydi.
- Mobil API (`/me/payroll`, `/staff/{id}/payroll`): faqat yangi maydonlar qo'shildi (`usluga_students`, `usluga_student_amount`, `usluga_bonus`, `usluga_bonus_amount`, `usluga_tier`, `accrued_total`).
- Migratsiya `2026_10_20_000001` faqat yangi bo'sh ustunlar qo'shadi: `users.hired_at`, `branches.usluga_from/usluga_rates`, `groups.usluga_tier/usluga_student_amount/usluga_bonus_amount`. Mavjud ma'lumot o'chmaydi. Testlar: `V13UslugaTest`.
