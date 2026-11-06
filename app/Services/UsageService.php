<?php

namespace App\Services;

use App\Models\Center;
use App\Models\UsageCounter;
use Illuminate\Support\Facades\DB;

/** Oylik SMS va AI so'rovlar hisobi (markaz bo'yicha, tarif limiti bilan). */
class UsageService
{
    public function __construct(private SubscriptionService $subs) {}

    public function limit(Center $center, string $kind): ?int
    {
        return $this->subs->limits($center)[$kind === 'sms' ? 'sms_monthly' : 'ai_monthly'];
    }

    public function used(Center $center, string $kind): int
    {
        return (int) (UsageCounter::withoutGlobalScopes()->where(['center_id' => $center->id, 'kind' => $kind])
            ->where('period_start', now()->startOfMonth()->toDateString())->value('used') ?? 0);
    }

    /** Foydalanish mumkinmi (limit tugamagan va xizmat yoqilgan). */
    public function allowed(Center $center, string $kind, int $count = 1): bool
    {
        $limit = $this->limit($center, $kind);

        if ($limit === null) {
            return true;
        }

        return $limit > 0 && ($this->used($center, $kind) + $count <= $limit);
    }

    /** Hisobni oshiradi; limit tugagan bo'lsa false (oshirmaydi). */
    public function consume(Center $center, string $kind, int $count = 1): bool
    {
        $limit = $this->limit($center, $kind);
        $period = now()->startOfMonth()->toDateString();

        return DB::transaction(function () use ($center, $kind, $count, $limit, $period) {
            UsageCounter::withoutGlobalScopes()->firstOrCreate(
                ['center_id' => $center->id, 'kind' => $kind, 'period_start' => $period],
                ['used' => 0, 'limit_snapshot' => (int) ($limit ?? 0)]
            );
            $row = UsageCounter::withoutGlobalScopes()->where(['center_id' => $center->id, 'kind' => $kind, 'period_start' => $period])->lockForUpdate()->firstOrFail();

            if ($limit !== null && ($limit <= 0 || $row->used + $count > $limit)) {
                return false;   // oylik limit tugadi
            }

            $row->used += $count;
            $row->limit_snapshot = (int) ($limit ?? 0);
            $row->save();

            return true;
        });
    }

    /** Xizmat ko'rsatilmagan bo'lsa (masalan, AI ikkala provayderda ham xato bersa) hisobni qaytaradi. */
    public function refund(Center $center, string $kind, int $count = 1): void
    {
        UsageCounter::withoutGlobalScopes()->where(['center_id' => $center->id, 'kind' => $kind])
            ->where('period_start', now()->startOfMonth()->toDateString())->where('used', '>=', $count)->decrement('used', $count);
    }
}
