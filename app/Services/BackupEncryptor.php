<?php

namespace App\Services;

use RuntimeException;

/**
 * Zaxira faylini shifrlaydi: tasodifiy AES-256-GCM kaliti (har zaxira uchun yangi), u esa OCHIQ RSA kalit bilan o'raladi.
 * Serverda faqat ochiq kalit turadi - serverga kirilsa ham zaxirani ochib bo'lmaydi. Yopiq kalit sAdmin'da.
 *
 * Fayl formati (EBK1): "EBK1" | uint16 o'ralgan kalit uzunligi | o'ralgan kalit (RSA-OAEP) | bloklar...
 * Blok: uint32 shifrlangan uzunlik | 12 bayt IV | 16 bayt tag | shifrlangan ma'lumot (har blok 4 MB gacha asl matn).
 * Ochish: bin/backup-decrypt.php (docs/BACKUP_RESTORE.md).
 */
class BackupEncryptor
{
    public const BLOCK = 4 * 1024 * 1024;

    public function publicKey(): string
    {
        $path = (string) config('services.backup.public_key_path');

        if (! is_file($path)) {
            throw new RuntimeException("Zaxira ochiq kaliti topilmadi ({$path}). `php artisan backup:keygen` bilan kalit juftini yarating.");
        }

        return (string) file_get_contents($path);
    }

    public function encryptFile(string $source, string $target): void
    {
        $key = random_bytes(32);
        $pub = openssl_pkey_get_public($this->publicKey());

        if (! $pub || ! openssl_public_encrypt($key, $wrapped, $pub, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException("Zaxira kalitini o'rab bo'lmadi (ochiq kalit noto'g'ri).");
        }

        $in = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        fwrite($out, 'EBK1'.pack('n', strlen($wrapped)).$wrapped);

        while (! feof($in)) {
            $chunk = fread($in, self::BLOCK);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            $iv = random_bytes(12);
            $cipher = openssl_encrypt($chunk, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
            if ($cipher === false) {
                throw new RuntimeException('Shifrlash xatosi.');
            }
            fwrite($out, pack('N', strlen($cipher)).$iv.$tag.$cipher);
        }

        fclose($in);
        fclose($out);
    }

    /** Sinov va tiklash uchun: yopiq kalit bilan ochadi. */
    public function decryptFile(string $source, string $target, string $privateKeyPem): void
    {
        $in = fopen($source, 'rb');
        if (fread($in, 4) !== 'EBK1') {
            throw new RuntimeException('Fayl formati noto\'g\'ri.');
        }
        $len = unpack('n', fread($in, 2))[1];
        $priv = openssl_pkey_get_private($privateKeyPem);
        if (! $priv || ! openssl_private_decrypt(fread($in, $len), $key, $priv, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException("Yopiq kalit mos kelmadi.");
        }

        $out = fopen($target, 'wb');
        while (($head = fread($in, 4)) !== '' && $head !== false) {
            $n = unpack('N', $head)[1];
            $iv = fread($in, 12);
            $tag = fread($in, 16);
            $plain = openssl_decrypt(fread($in, $n), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain === false) {
                throw new RuntimeException("Fayl buzilgan yoki o'zgartirilgan (autentifikatsiya xatosi).");
            }
            fwrite($out, $plain);
        }
        fclose($in);
        fclose($out);
    }
}
