<?php

namespace App\Services;

use App\Enums\CenterStatus;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Tariff;

/**
 * Tarif filial limitidan oshgan filiallar. Qoida: faol filiallar ochilgan tartibda (id bo'yicha) sanaladi;
 * limitga sig'ganlari ishlaydi, qolganlari «faqat ko'rish» rejimida (ma'lumot saqlanadi, yozish yopiq).
 * Holat saqlanmaydi, har safar joriy tarifdan hisoblanadi — tarif yangilansa avtomatik ochiladi.
 */
class BranchLimitService
{
    public function __construct(private SubscriptionService $subs) {}

    /** @return int[] faqat ko'rish rejimidagi filial ID lari */
    public function lockedIds(Center|int $center): array
    {
        return $this->locked($center)->pluck('id')->all();
    }

    public function locked(Center|int $center)
    {
        $model = $center instanceof Center ? $center : Center::find($center);
        if (! $model || $model->is_demo || $model->status !== CenterStatus::Active) {
            return collect();
        }

        $limit = $this->subs->limits($model)['max_branches'];
        if ($limit === null || $limit <= 0) {
            return collect();
        }

        return Branch::withoutGlobalScopes()->where('center_id', $model->id)->where('status', 'active')
            ->orderBy('id')->get(['id', 'name'])->slice($limit)->values();
    }

    public function isLocked(int $branchId, int $centerId): bool
    {
        return in_array($branchId, $this->lockedIds($centerId), true);
    }

    /**
     * Shu tarif tanlansa qaysi filiallar faqat ko'rish rejimiga o'tadi va qachondan.
     *
     * @return array{names: string[], from: \Illuminate\Support\Carbon}|null
     */
    public function previewFor(Center $center, Tariff $tariff, string $period): ?array
    {
        if ($tariff->max_branches === null) {
            return null;
        }

        $active = Branch::withoutGlobalScopes()->where('center_id', $center->id)->where('status', 'active')->orderBy('id')->get(['id', 'name']);
        $extra = $active->slice((int) $tariff->max_branches)->values();
        if ($extra->isEmpty()) {
            return null;
        }

        return ['names' => $extra->pluck('name')->all(), 'from' => $this->subs->startsAtFor($center, $tariff, $period)];
    }
}
