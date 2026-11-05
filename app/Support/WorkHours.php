<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Carbon;

/** Qo'llab-quvvatlash ish vaqti: kunlar (1=Du..7=Yak), boshlanish/tugash soati. SLA faqat ish soatlarida hisoblanadi. */
class WorkHours
{
    public static function config(): array
    {
        $v = PlatformSetting::many(['support_start', 'support_end', 'support_days', 'support_sla_hours']);

        return [
            'start' => $v['support_start'] ?? '09:00',
            'end' => $v['support_end'] ?? '18:00',
            'days' => array_map('intval', explode(',', $v['support_days'] ?? '1,2,3,4,5,6')),
            'sla_hours' => (float) ($v['support_sla_hours'] ?? 4),
        ];
    }

    private static function bounds(Carbon $day, array $c): array
    {
        return [$day->copy()->setTimeFromTimeString($c['start']), $day->copy()->setTimeFromTimeString($c['end'])];
    }

    /** $from dan boshlab $hours ish soati o'tgach qaysi vaqt bo'lishini hisoblaydi. */
    public static function addHours(Carbon $from, float $hours, ?array $c = null): Carbon
    {
        $c ??= self::config();
        $left = (int) round($hours * 60);
        $t = $from->copy();

        for ($i = 0; $i < 400 && $left > 0; $i++) {
            [$s, $e] = self::bounds($t, $c);
            if (! in_array($t->dayOfWeekIso, $c['days'], true) || $e->lte($s)) {
                $t = $t->copy()->addDay()->startOfDay();

                continue;
            }
            if ($t->lt($s)) {
                $t = $s->copy();
            }
            if ($t->gte($e)) {
                $t = $t->copy()->addDay()->startOfDay();

                continue;
            }
            $avail = (int) $t->diffInMinutes($e);
            if ($left <= $avail) {
                return $t->copy()->addMinutes($left);
            }
            $left -= $avail;
            $t = $t->copy()->addDay()->startOfDay();
        }

        return $t;
    }
}
