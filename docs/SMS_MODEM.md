# USB modem orqali SMS (Lubuntu server)

Laravel va USB modem **bir serverda** (Lubuntu). Tizim modemlarni **o'zi topadi va ulanadi**: bir nechta modem bo'lishi mumkin, SMS ular orasida navbatma-navbat taqsimlanadi. Modem bo'lmasa yoki xato bersa — SMS avtomatik **Eskiz** orqali ketadi.

## 1. Server tayyorlash (bir marta)

    sudo apt install usb-modeswitch usb-modeswitch-data       # Huawei/ZTE modemlarni modem rejimiga o'tkazadi
    sudo systemctl disable --now ModemManager                 # AT portlarni band qilmasligi uchun (yoki: nmcli bo'lmasa shunday qoldiring)
    sudo usermod -aG dialout www-data                         # web-server foydalanuvchisi (sAdmin «Qayta qidirish» tugmasi uchun)
    sudo usermod -aG dialout $USER                            # artisan'ni qo'lda ishlatadigan foydalanuvchi
    sudo systemctl restart php8.3-fpm apache2                 # (yoki nginx/php-fpm) guruh o'zgarishi kuchga kirishi uchun

SIM kartada **PIN-kod o'chirilgan** bo'lishi kerak (telefonda: SIM xavfsizligi → PIN so'rovini o'chirish).

## 2. Tekshirish

    ls /dev/ttyUSB* /dev/ttyACM*        # modem portlari ko'rinishi kerak
    php artisan sms:modem-scan          # har bir portni AT bilan tekshiradi, topilgan modemlarni jadvalda ko'rsatadi

Bitta modem odatda 3–4 port ochadi — tizim IMEI bo'yicha guruhlab, AT ga javob beradigan portni tanlaydi (qolganlari «qo'shimcha port» deb belgilanadi).

## 3. Doimiy ishchi (avtomatik topish va ulash)

    sudo cp deploy/eduziyo-modem.service /etc/systemd/system/     # ichidagi yo'l va foydalanuvchini tahrirlang
    sudo systemctl daemon-reload && sudo systemctl enable --now eduziyo-modem
    journalctl -u eduziyo-modem -f                                # jonli jurnal

Ishchi: har 60 soniyada modemlarni qayta qidiradi (yangi ulangan modem **avtomatik** faollashadi, uzilgani «Ulanmagan» bo'ladi, port raqami o'zgarsa topadi), har 3 soniyada navbatdagi SMS'larni yuboradi. Navbatni (queue worker) va rejalashtiruvchini (`php artisan schedule:run` har daqiqa) ham ishlatish kerak.

## 4. Boshqaruv (sAdmin → «USB modemlar»)

- Ishchi holati, tayyor modemlar soni, navbat.
- Har modem: IMEI, port, operator, SIM holati, signal (%), yuborilgan/xato soni, oxirgi xato.
- **Qayta qidirish**, **faollashtirish/o'chirish**, nom berish, **sinov SMS** (tanlangan modem orqali), xato hisoblagichini tozalash.
- **Diagnostika jadvali**: har port bo'yicha natija (ruxsat yo'q → `dialout`; AT javobi yo'q → xizmat porti).

## 5. Qanday ishlaydi

1. Xabar navbatga (`sms_outbox`) qo'yiladi — faqat ishchi tirik va kamida bitta tayyor modem bo'lsa; aks holda darhol Eskiz.
2. Ishchi eng uzoq dam olgan tayyor modemni tanlab, PDU rejimida yuboradi (lotin — GSM 7-bit, kirill/o'zbek `ʻ` — UCS2, uzun xabar bo'laklarga bo'linadi).
3. Xato bo'lsa yoki xabar 2 daqiqada olinmasa/5 daqiqada natija bermasa — avtomatik Eskiz (`sms:dispatch`). Ketma-ket 3 xato bergan modem «Xato» holatiga o'tadi va keyingi qidiruvda qayta tekshiriladi.
4. Limit (tarif) faqat bir marta hisoblanadi; markaz SMS jurnalida kanal (Modem/Eskiz) ko'rinadi.

## 6. Muammolar

| Belgi | Sabab / yechim |
|---|---|
| Port ko'rinmaydi | `usb_modeswitch` o'rnating, modemni qayta ulang; `lsusb` da modem «Mass storage» bo'lib qolgan bo'lishi mumkin |
| «Ruxsat yo'q» | foydalanuvchini `dialout` guruhiga qo'shing va xizmatni qayta ishga tushiring |
| AT javobi yo'q | xizmat porti — normal; boshqa portlardan biri javob beradi |
| «SIM muammosi» | SIM yo'q yoki PIN so'ralmoqda — PIN'ni o'chiring |
| «Tarmoq yo'q» | signal yo'q: antennani/joyni o'zgartiring, SIM'da hisob borligini tekshiring |
