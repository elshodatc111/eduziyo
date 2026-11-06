# EDUZIYO — GitHub, Docker va Cloudflare orqali joylashtirish

Maqsad: loyihani GitHub'ga SSH bilan yuklash, uy serveridagi Docker'da ishga tushirish va Cloudflare Tunnel orqali
`https://educrm.elshod.date` manzilida internetga chiqarish — **serverdagi boshqa loyihalarga (KinderDesk, clinik_crm,
MyPartfolio, stack, edu_novo_crm_laravel) tegmasdan**.

## 0. Qanday ajratilgan (nega boshqa loyihalarga xalaqit bermaydi)

| Nima | EDUZIYO | Izolyatsiya |
|---|---|---|
| Papka | `~/progects/eduziyo` | alohida papka |
| Compose loyiha nomi | `eduziyo` | konteynerlar `eduziyo_*`, tarmoq `eduziyo_internal` |
| Tashqi port | `127.0.0.1:7005` | faqat serverning o'zidan; internetga ochilmaydi |
| Baza | alohida baza va foydalanuvchi `eduziyo` | boshqa bazalarga huquq yo'q |
| Fayllar | `eduziyo_storage`, `eduziyo_uploads` hajmlari | boshqa hajmlarga tegmaydi |
| Resurs | har xizmatga `mem_limit`/`cpus` | boshqa loyihalar xotirasini olmaydi |
| Cloudflare | tunnelga **bitta yangi marshrut** qo'shiladi | mavjud marshrutlar o'zgarmaydi |
| GitHub | alohida deploy kaliti + `~/.ssh/config` alias | boshqa repolar kalitlari aralashmaydi |

> 7005-port band bo'lsa `.env` da `WEB_PORT` ni o'zgartiring (`ss -ltnp | grep 7005` bilan tekshiring).

## 1. GitHub'ga yuklash (kompyuteringizdan, SSH)

1. GitHub'da bo'sh **Private** repo bor: `elshodatc111/eduziyo`.
2. Kompyuteringizda (`C:\xampp\htdocs\EDUZIYO`):
   ```bash
   git init -b main            # agar hali git bo'lmasa
   git remote add origin git@github.com:elshodatc111/eduziyo.git
   git add . && git status     # .env, vendor, node_modules, storage/app KIRMAGANini tekshiring
   git commit -m "EDUZIYO: birinchi yuklash"
   git push -u origin main
   ```
3. SSH kaliti yo'q bo'lsa: `ssh-keygen -t ed25519 -C "eduziyo-dev"` → `~/.ssh/id_ed25519.pub` ni GitHub → Settings → SSH keys ga qo'shing.
4. `.env`, parollar va kalitlar `.gitignore` da — hech qachon commit qilinmaydi. Tasodifan commit qilgan bo'lsangiz, kalitlarni **almashtiring**.

## 2. Server tayyorlash (bir marta)

### 2.1 Deploy kaliti (faqat shu repo uchun, faqat o'qish)
```bash
ssh-keygen -t ed25519 -f ~/.ssh/eduziyo_deploy -N "" -C "eduziyo-server"
cat ~/.ssh/eduziyo_deploy.pub        # GitHub > repo > Settings > Deploy keys > Add (Write access BERMANG)
cat >> ~/.ssh/config <<'CFG'

Host github-eduziyo
    HostName github.com
    User git
    IdentityFile ~/.ssh/eduziyo_deploy
    IdentitiesOnly yes
CFG
chmod 600 ~/.ssh/config ~/.ssh/eduziyo_deploy
```
`IdentitiesOnly yes` va alohida `Host` alias boshqa loyihalarning kalitlariga tegmaydi.

### 2.2 Klonlash
```bash
cd ~/progects
git clone git@github-eduziyo:elshodatc111/eduziyo.git eduziyo
cd eduziyo
```

### 2.3 Baza
Bazangiz Docker'da ishlayotgan bo'lsa (`docker ps` da MariaDB/MySQL konteynerini toping, `docker network ls` da tarmog'ini):
```bash
./deploy/db-create.sh <db-konteyner-nomi>      # alohida baza + foydalanuvchi, parolni chop etadi
```
Faqat `eduziyo` bazasi va foydalanuvchisi yaratiladi (`IF NOT EXISTS`) — mavjudlari o'zgarmaydi.

### 2.4 .env
```bash
cp deploy/env.production.example .env
nano .env     # DB_HOST (db konteyner nomi), DB_PASSWORD, DB_NETWORK (docker network ls), APP_URL
```
`APP_KEY` ni `deploy.sh` o'zi yaratadi. Telegram/SMS/OpenAI kalitlarini keyin **sAdmin → Integratsiyalar** sahifasida kiriting.

### 2.5 Birinchi ishga tushirish
```bash
./deploy/deploy.sh --no-pull
```
So'ng birinchi sAdmin (`.env` dagi `SADMIN_*` qiymatlari bilan):
`docker compose --env-file .env -f deploy/docker-compose.yml exec app php artisan db:seed --force`

Tekshirish: `curl -I http://127.0.0.1:7005/up` → `200`.

## 3. Cloudflare Tunnel (mavjud "HomeServer" tunneliga yangi marshrut)

Mavjud marshrutlarni **o'zgartirmaysiz**, faqat bittasini qo'shasiz.

1. Cloudflare Zero Trust → Networks → Tunnels → **HomeServer** → *Published application routes* → **Add**:
   - Subdomain: `educrm`, Domain: `elshod.date`
   - Service: `HTTP` → `localhost:7005`
     (cloudflared Docker konteynerida ishlasa `localhost` o'rniga server IP yoki `host.docker.internal:7005` ni ishlating; bunda `WEB_BIND=0.0.0.0` va xavfsizlik devori bilan faqat LAN'ga ruxsat bering.)
2. Cloudflare DNS'da `educrm` CNAME yozuvi avtomatik qo'shiladi (Proxied/oranjevoy bulut).
3. SSL/TLS: rejim **Full** (tunnel uchun odatda "Flexible/Full" ishlaydi, tunnel o'zi shifrlaydi). *Always Use HTTPS* — yoqing.
4. Tavsiya etiladigan Cloudflare sozlamalari (faqat `educrm.elshod.date` uchun, WAF qoidasi hostname bo'yicha):
   - **Rate limiting**: `/login` — 10 so'rov/daqiqa/IP.
   - **Bot Fight Mode** yoqing; Telegram webhook yo'li `/api/telegram/webhook` ni (Telegram IP'laridan keladi) bloklamang.
   - Keshlash: `/build/*` — Cache Everything; qolgan sahifalar — *Bypass* (dinamik, sessiya).
5. Natija: `https://educrm.elshod.date`.

> Eslatma: domen `elshod.date` (siz yozgan `.data` emas). Boshqa subdomenlar (`kinderdesk`, `edunova`, `clinics`...) o'z holicha qoladi.

## 4. Telegram bot

1. @BotFather'da bot yarating, tokenni **sAdmin → Integratsiyalar → Telegram** ga kiriting.
2. "Webhook o'rnatish" tugmasini bosing (URL: `https://educrm.elshod.date/api/telegram/webhook`, maxfiy sarlavha bilan). Holatini shu sahifada ko'rasiz.
3. sAdmin o'z profilida botga ulanadi → ogohlantirishlar (cron/navbat to'xtashi, to'lov cheki, murojaat) shu chatga keladi. Qo'shimcha chatlar — "Ogohlantirish chatlari" maydoni.

## 5. SMS Gateway

SMS lar `https://sms.elshod.date` gateway orqali ketadi (sAdmin → Integratsiyalar → SMS Gateway: manzil, API kaliti, webhook siri). Gateway loyihasida
Webhook URL: `https://educrm.elshod.date/api/sms-gateway/webhook` va shu sir kiritiladi. Gateway ishlamasa SMS avtomatik Eskiz orqali ketadi.
Eski USB modem konteyneri qolgan bo'lsa: `docker rm -f eduziyo_modem`.

## 6. Yangilash va orqaga qaytarish

```bash
cd ~/progects/eduziyo && ./deploy/deploy.sh      # git pull → build → tenancy:scan → migrate → qayta ishga tushirish → tenancy:audit
./deploy/deploy.sh --rollback                    # oldingi tasvirga qaytish (migratsiya qaytmaydi, faqat additive)
```
Chiqarish oldidan `tenancy:scan` (yangi xom SQL / `withoutGlobalScopes()` topsa jarayon to'xtaydi) va chiqarishdan keyin `tenancy:audit`
(bazadagi markazlararo bog'lanish xatolari) avtomatik ishlaydi. Yangi, ongli istisno bo'lsa: `php artisan tenancy:scan --update` va diffni ko'rib commit qiling.

Zaxira: ilova ichidagi zaxira `storage` hajmiga yoziladi; bazani tashqariga ham zaxiralang:
`docker exec <db> mysqldump -u... eduziyo | gzip > eduziyo_$(date +%F).sql.gz`.

## 7. Boshqa loyihalarga tegmaganini tekshirish ro'yxati

- [ ] `docker ps` — eduziyo_* dan boshqa konteynerlar holati o'zgarmagan
- [ ] `ss -ltnp | grep 7005` — faqat `127.0.0.1:7005`
- [ ] Cloudflare tunnel marshrutlari — eski marshrutlar joyida
- [ ] `~/.ssh/config` — faqat yangi `github-eduziyo` bloki qo'shilgan
- [ ] `docker stats --no-stream` — eduziyo xizmatlari chegaralar ichida

## 8. Muammolar

| Alomat | Sabab / yechim |
|---|---|
| 502 | `docker logs eduziyo_app`; `.env` DB sozlamasi; `docker compose ... ps` |
| Sahifa http havolalar bilan | `APP_URL=https://...` bo'lishi kerak (shunda https majburlanadi) |
| DB ulanmaydi | `DB_NETWORK` to'g'ri tarmoq nomimi; `DB_HOST` shu tarmoqdagi nom |
| Telegram xabar kelmaydi | Integratsiyalar sahifasida webhook holati; sAdmin botga ulanganmi |
| Fayl yuklash xatosi | `public/uploads` hajmi huquqi (`entrypoint` o'zi sozlaydi) |
