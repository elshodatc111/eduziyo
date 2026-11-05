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

        return $limit > 0 && ($this->used($center, $kind) + $count <= $limit || ($kind === 'sms' && $this->extra($center) >= $count));
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
                // Oylik limit tugadi: sotib olingan qo'shimcha SMS (tarif davri oxirigacha) dan olinadi
                return $kind === 'sms' && $limit > 0 && $this->takeExtra($center, $count);
            }

            $row->used += $count;
            $row->limit_snapshot = (int) ($limit ?? 0);
            $row->save();

            return true;
        });
    }

    /** Joriy obunadagi qolgan qo'shimcha SMS soni. */
    public function extra(Center $center): int
    {
        return (int) ($this->subs->current($center)?->extra_sms ?? 0);
    }

    private function takeExtra(Center $center, int $count): bool
    {
        $sub = \App\Models\CenterSubscription::withoutGlobalScopes()->where('center_id', $center->id)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())->orderByDesc('starts_at')->lockForUpdate()->first();

        if (! $sub || $sub->extra_sms < $count) {
            return false;
        }

        $sub->extra_sms -= $count;
        $sub->save();

        return true;
    }
}
