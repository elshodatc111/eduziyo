<?php

namespace App\Services\Telegram;

use App\Models\SalesApplication;

/**
 * sAdminlarga Telegram xabarlari (ariza, ...). Barcha ulangan sAdminlarga va «Integratsiyalar»dagi qo'shimcha chatlarga
 * BIR XIL matn boradi; har bir xabarda to'g'ridan-to'g'ri ochiladigan havola bor. Telegram xatosi asosiy jarayonni buzmaydi.
 */
class SadminAlerts
{
    public function __construct(private TelegramNotifier $tg) {}

    public function application(SalesApplication $app, bool $verified): void
    {
        $lines = [
            $verified ? "🆕 <b>Yangi ariza (telefon tasdiqlangan)</b>" : "🆕 <b>Yangi ariza</b> (telefon hali tasdiqlanmagan)",
            'Markaz: '.e($app->center_name),
            'Ism: '.e($app->name),
            'Telefon: '.e($app->phone),
        ];
        if ($app->city) {
            $lines[] = 'Shahar: '.e($app->city);
        }
        if ($app->tariff_slug) {
            $lines[] = 'Tarif: '.e($app->tariff_slug);
        }
        if ($app->branches_count || $app->students_count) {
            $lines[] = 'Filial: '.(int) $app->branches_count.' · O\'quvchi: ~'.(int) $app->students_count;
        }
        if ($app->wants_demo) {
            $lines[] = '🎬 Demo so\'radi';
        }
        if ($app->message) {
            $lines[] = 'Izoh: '.e(mb_substr((string) $app->message, 0, 300));
        }
        $lines[] = "\n🔗 Ochish: ".route('sales.applications', ['id' => $app->id]);

        $this->safe(fn () => $this->tg->toSadmins(implode("\n", $lines)));
    }

    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
