<?php

namespace App\Console\Commands;

use App\Services\BackupEncryptor;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Kunlik zaxirani shifrlab, Telegram bot orqali sAdmin(lar)ga yuboradi (50 MB dan kattasi bo'laklanadi).
 * `--files`: cheklar va yuklangan fayllar (haftalik). Shifrlanmagan hech narsa yuborilmaydi.
 */
class BackupTelegram extends Command
{
    protected $signature = 'backup:telegram {--files : Baza o\'rniga cheklar va yuklangan fayllar zaxirasi} {--part-mb=45 : Bo\'lak hajmi (MB)}';

    protected $description = 'Shifrlangan zaxirani Telegram bot orqali sAdmin ga yuboradi';

    public function handle(BackupEncryptor $enc, TelegramClient $tg, TelegramNotifier $notifier): int
    {
        $tmp = storage_path('app/backups/tmp');
        File::ensureDirectoryExists($tmp);

        try {
            if (! $tg->configured()) {
                throw new \RuntimeException('TELEGRAM_BOT_TOKEN sozlanmagan.');
            }

            $chats = $notifier->sadminChats();
            if (! $chats) {
                throw new \RuntimeException("Telegram ulangan sAdmin yo'q (profilda «Telegramni ulash»).");
            }

            $enc->publicKey();   // kalit bo'lmasa shu yerda xato - shifrlanmagan holda hech narsa yuborilmaydi

            $plain = $this->option('files') ? $this->archiveFiles($tmp) : $this->dumpDatabase();
            $name = basename($plain);
            $encrypted = $tmp.'/'.$name.'.enc';
            $enc->encryptFile($plain, $encrypted);

            $parts = $this->split($encrypted, max(1, (int) $this->option('part-mb')) * 1024 * 1024);
            foreach ($chats as $chat) {
                foreach ($parts as $i => $part) {
                    $label = count($parts) > 1 ? ' (bo\'lak '.($i + 1).'/'.count($parts).')' : '';
                    if (! $tg->sendDocument($chat, $part, basename($part), '🗄 '.$name.$label)) {
                        throw new \RuntimeException('Telegram\'ga yuborib bo\'lmadi: '.basename($part));
                    }
                }
            }

            $this->cleanup($tmp);
            Cache::put('system.telegram_backup', ['ok' => true, 'at' => now()->timestamp, 'message' => $name.' yuborildi'], now()->addDays(10));
            $this->info("Yuborildi: {$name} (".count($parts).' bo\'lak)');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->cleanup($tmp);
            Cache::put('system.telegram_backup', ['ok' => false, 'at' => now()->timestamp, 'message' => $e->getMessage()], now()->addDays(10));
            $notifier->toSadmins('⚠️ Zaxira yuborilmadi: '.e($e->getMessage()));
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function dumpDatabase(): string
    {
        $dir = storage_path('app/backups');
        Artisan::call('app:backup', ['--keep' => 14]);
        $latest = collect(File::files($dir))->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'))->sortByDesc(fn ($f) => $f->getMTime())->first();

        if (! $latest) {
            throw new \RuntimeException('Baza zaxirasi yaratilmadi.');
        }

        return $latest->getPathname();
    }

    private function archiveFiles(string $tmp): string
    {
        $zipPath = $tmp.'/files_'.now()->format('Y-m-d').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach (['app/topups', 'app/public'] as $rel) {
            $base = storage_path($rel);
            if (! is_dir($base)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $zip->addFile($file->getPathname(), $rel.'/'.substr($file->getPathname(), strlen($base) + 1));
                }
            }
        }

        if ($zip->count() === 0) {
            $zip->addFromString('README.txt', 'Fayl yo\'q');
        }
        $zip->close();

        return $zipPath;
    }

    /** @return list<string> */
    private function split(string $file, int $size): array
    {
        if (filesize($file) <= $size) {
            return [$file];
        }

        $parts = [];
        $in = fopen($file, 'rb');
        for ($i = 1; ! feof($in); $i++) {
            $data = fread($in, $size);
            if ($data === '' || $data === false) {
                break;
            }
            $path = sprintf('%s.part%03d', $file, $i);
            file_put_contents($path, $data);
            $parts[] = $path;
        }
        fclose($in);

        return $parts;
    }

    private function cleanup(string $tmp): void
    {
        File::deleteDirectory($tmp);
    }
}
