<?php
/**
 * Zaxirani ochish (serverdan alohida, o'z kompyuteringizda ishlating):
 *   php backup-decrypt.php <backup.sql.gz.enc> <private.pem> <chiqish.sql.gz>
 * Telegram'da bo'laklab kelgan bo'lsa avval bo'laklarni birlashtiring: cat fayl.part* > fayl.enc
 */
if ($argc < 4) {
    fwrite(STDERR, "Ishlatish: php backup-decrypt.php <fayl.enc> <private.pem> <chiqish>\n");
    exit(1);
}
[, $src, $pem, $dst] = $argv;
$in = fopen($src, 'rb');
if (fread($in, 4) !== 'EBK1') { fwrite(STDERR, "Format noto'g'ri\n"); exit(1); }
$len = unpack('n', fread($in, 2))[1];
$priv = openssl_pkey_get_private(file_get_contents($pem));
if (! $priv || ! openssl_private_decrypt(fread($in, $len), $key, $priv, OPENSSL_PKCS1_OAEP_PADDING)) { fwrite(STDERR, "Yopiq kalit mos kelmadi\n"); exit(1); }
$out = fopen($dst, 'wb');
while (($head = fread($in, 4)) !== '' && $head !== false) {
    $n = unpack('N', $head)[1];
    $iv = fread($in, 12); $tag = fread($in, 16);
    $plain = openssl_decrypt(fread($in, $n), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) { fwrite(STDERR, "Fayl buzilgan\n"); exit(1); }
    fwrite($out, $plain);
}
echo "Tayyor: {$dst}\n";
