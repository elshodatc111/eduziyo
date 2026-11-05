<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupKeygen extends Command
{
    protected $signature = 'backup:keygen {--dir= : Kalitlar saqlanadigan papka (standart: joriy papka)}';

    protected $description = "Zaxira shifrlash uchun RSA kalit juftini yaratadi (yopiq kalitni serverda QOLDIRMANG)";

    public function handle(): int
    {
        $dir = rtrim($this->option('dir') ?: getcwd(), '/\\');
        $key = openssl_pkey_new(['private_key_bits' => 4096, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if (! $key) {
            $this->error('OpenSSL kalit yarata olmadi.');

            return self::FAILURE;
        }

        openssl_pkey_export($key, $private);
        $public = openssl_pkey_get_details($key)['key'];
        file_put_contents($dir.'/backup-private.pem', $private);
        file_put_contents($dir.'/backup-public.pem', $public);
        @chmod($dir.'/backup-private.pem', 0600);

        $this->info("Yaratildi: {$dir}/backup-public.pem va {$dir}/backup-private.pem");
        $this->warn("1) backup-public.pem ni serverda storage/app/backup-public.pem ga qo'ying.");
        $this->warn("2) backup-private.pem ni xavfsiz joyga (parol menejer/flesh) ko'chiring va serverdan O'CHIRING. Yo'qotilsa zaxiralarni ochib bo'lmaydi.");

        return self::SUCCESS;
    }
}
