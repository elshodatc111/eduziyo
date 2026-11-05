<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Markazlar ma'lumoti aralashib ketishidan himoya («храповик»): `app/` ichida avtomatik markaz filtrini aylanib o'tadigan
 * konstruksiyalar (withoutGlobalScopes, DB::table, xom SQL) sonini sanaydi va `database/tenancy-baseline.json` bilan solishtiradi.
 * Har qanday o'sish (yangi fayl yoki ko'proq qo'llanish) chiqarishni to'xtatadi: dasturchi yangi joyni ko'rib chiqadi
 * (center_id filtri bormi?) va `php artisan tenancy:scan --update` bilan bazani yangilaydi.
 */
class TenancyScan extends Command
{
    protected $signature = 'tenancy:scan {--update : Joriy holatni ko\'rib chiqilgan deb qabul qilish}';

    protected $description = "Markaz filtrini aylanib o'tadigan kod (withoutGlobalScopes, DB::table, xom SQL) o'sganini tekshiradi";

    public const PATTERN = '/withoutGlobalScope|DB::table\(|DB::select|DB::statement|DB::unprepared|whereRaw|selectRaw|orderByRaw|groupByRaw|havingRaw|fromRaw|->toBase\(\)/';

    public static function baselinePath(): string
    {
        return database_path('tenancy-baseline.json');
    }

    /** @return array<string,int> fayl => qo'llanishlar soni */
    public static function scan(): array
    {
        $out = [];
        foreach ((new Finder)->files()->in(app_path())->name('*.php')->sortByName() as $file) {
            $n = 0;
            foreach (file($file->getRealPath()) as $line) {
                $trim = ltrim($line);
                if (str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '/*')) {
                    continue;   // izohlar sanalmaydi
                }
                $n += preg_match_all(self::PATTERN, $line);
            }
            if ($n > 0) {
                $out[str_replace('\\', '/', substr($file->getRelativePathname(), 0))] = $n;
            }
        }

        return $out;
    }

    public function handle(): int
    {
        $now = self::scan();

        if ($this->option('update')) {
            file_put_contents(self::baselinePath(), json_encode($now, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $this->info('Baza yangilandi: '.count($now).' fayl, '.array_sum($now).' ta qo\'llanish.');

            return self::SUCCESS;
        }

        $base = is_file(self::baselinePath()) ? (array) json_decode(file_get_contents(self::baselinePath()), true) : null;
        if ($base === null) {
            $this->error('database/tenancy-baseline.json topilmadi. Birinchi marta: php artisan tenancy:scan --update');

            return self::FAILURE;
        }

        $grew = [];
        foreach ($now as $file => $n) {
            if ($n > ($base[$file] ?? 0)) {
                $grew[] = [$file, $base[$file] ?? 0, $n];
            }
        }

        if ($grew) {
            $this->table(['Fayl', 'Oldin', 'Hozir'], $grew);
            $this->error("Markaz filtrini aylanib o'tadigan kod ko'paydi. Har bir yangi joyni ko'rib chiqing (center_id filtri bormi?), so'ng: php artisan tenancy:scan --update");

            return self::FAILURE;
        }

        $this->info('Tenancy skaner: o\'sish yo\'q ('.array_sum($now).' ta ko\'rib chiqilgan qo\'llanish).');

        return self::SUCCESS;
    }
}
