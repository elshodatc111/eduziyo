<?php

namespace App\Services;

use App\Enums\CenterStatus;
use App\Models\AuditLog;
use App\Models\Center;

/**
 * Markaz hayot sikli (soatiga bir marta):
 *  - tugashiga 7/3/1 kun qolganda direktorga eslatma;
 *  - tarif tugasa (navbatdagi obuna ham yo'q) → "faqat ko'rish", 30 kundan keyin o'chirish sanasi belgilanadi;
 *  - o'chirishga 3 kun qolganda oxirgi ogohlantirish;
 *  - muddat tugagach markaz ma'lumoti butunlay o'chadi (markaz qatori "deleted" bo'lib qoladi).
 * Demo markaz va tarifsiz eski (cheksiz) markazlarga tegmaydi.
 */
class CenterLifecycle
{
    public const GRACE_DAYS = 30;

    public function __construct(private SubscriptionService $subs, private CenterAlerts $alerts, private CenterWiper $wiper) {}

    /** @return array<string,int> */
    public function run(bool $dryRun = false): array
    {
        $stats = ['reminders' => 0, 'read_only' => 0, 'warned' => 0, 'deleted' => 0, 'invoices' => 0];
        $stats['invoices'] = $dryRun ? 0 : app(SmsInvoiceService::class)->expireOld();

        $centers = Center::where('is_demo', false)->whereIn('status', [CenterStatus::Active, CenterStatus::ReadOnly])->whereNotNull('tariff_ends_at')->get();

        foreach ($centers as $center) {
            if ($center->status === CenterStatus::Active) {
                $this->handleActive($center, $dryRun, $stats);
            } else {
                $this->handleReadOnly($center, $dryRun, $stats);
            }
        }

        return $stats;
    }

    private function handleActive(Center $center, bool $dry, array &$stats): void
    {
        if ($this->subs->current($center)) {
            $daysLeft = (int) floor(now()->diffInDays($center->tariff_ends_at, false));
            foreach ([7, 3, 1] as $mark) {
                $key = "t-{$mark}";
                if ($daysLeft <= $mark && $daysLeft >= 0 && ! in_array($key, $center->reminders_sent ?? [], true) && $this->subs->upcoming($center)->isEmpty()) {
                    if (! $dry) {
                        $this->alerts->send($center, "Tarifingiz {$center->tariff_ends_at->format('d.m.Y')} kuni tugaydi. Markaz balansini to'ldirib, tarifni uzaytiring.");
                        $center->forceFill(['reminders_sent' => [...($center->reminders_sent ?? []), $key]])->save();
                    }
                    $stats['reminders']++;
                    break;
                }
            }

            return;
        }

        if (! $dry) {
            $center->forceFill([
                'status' => CenterStatus::ReadOnly, 'read_only_since' => now(),
                'delete_after' => $center->tariff_ends_at->copy()->addDays(self::GRACE_DAYS),
            ])->save();
            $this->alerts->send($center, "Tarif muddati tugadi: tizim faqat ko'rish rejimida. ".self::GRACE_DAYS." kun ichida tarifni yangilamasangiz, ma'lumot o'chiriladi.");
            AuditLog::record('center.read_only', null, "Markaz faqat ko'rish rejimiga o'tdi: {$center->name}");
        }
        $stats['read_only']++;
    }

    private function handleReadOnly(Center $center, bool $dry, array &$stats): void
    {
        if (! $center->delete_after) {
            return;
        }

        if ($center->delete_after->isPast()) {
            if (! $dry) {
                $this->wiper->wipe($center);
                $center->forceFill(['status' => CenterStatus::Deleted, 'deleted_at' => now()])->save();
                AuditLog::record('center.deleted', null, "Tarif yangilanmadi, markaz ma'lumoti o'chirildi: {$center->name}");
            }
            $stats['deleted']++;

            return;
        }

        if (now()->diffInDays($center->delete_after, false) <= 3 && ! in_array('delete-3', $center->reminders_sent ?? [], true)) {
            if (! $dry) {
                $this->alerts->send($center, "DIQQAT: {$center->delete_after->format('d.m.Y')} kuni markaz ma'lumoti butunlay o'chiriladi. Tarifni yangilang yoki Excel'ga yuklab oling.");
                $center->forceFill(['reminders_sent' => [...($center->reminders_sent ?? []), 'delete-3']])->save();
            }
            $stats['warned']++;
        }
    }
}
