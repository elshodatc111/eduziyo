<?php

namespace App\Services;

use App\Enums\CenterStatus;
use App\Models\Center;
use App\Models\CenterBalanceTransaction;
use App\Models\CenterSubscription;
use App\Models\Tariff;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tarif obunasi. Qoidalar:
 *  - Obuna narxi va limitlari sotib olingan paytdagi nusxada (snapshot) saqlanadi.
 *  - Tarif yo'q va holat "faol" bo'lgan eski markaz cheksiz hisoblanadi (limit yo'q).
 *  - Yangi (pending) markaz tarif olinmaguncha faqat ko'rish rejimida.
 *  - Arzonroq/bir xil tarif joriy davr tugagach boshlanadi; qimmatroq tarif darhol, eski qoldiq balansga qaytadi.
 */
class SubscriptionService
{
    public function __construct(private CenterBalanceService $balance) {}

    public function current(Center|int $center): ?CenterSubscription
    {
        $id = $center instanceof Center ? $center->id : $center;

        return CenterSubscription::withoutGlobalScopes()->where('center_id', $id)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->orderByDesc('starts_at')->first();
    }

    /** Joriy obunadan keyin boshlanadigan (navbatdagi) obunalar. */
    public function upcoming(Center|int $center)
    {
        $id = $center instanceof Center ? $center->id : $center;

        return CenterSubscription::withoutGlobalScopes()->where('center_id', $id)->where('starts_at', '>', now())->orderBy('starts_at')->get();
    }

    public function history(Center|int $center, int $limit = 20)
    {
        $id = $center instanceof Center ? $center->id : $center;

        return CenterSubscription::withoutGlobalScopes()->where('center_id', $id)->orderByDesc('starts_at')->limit($limit)->get();
    }

    /**
     * Joriy limitlar. null = cheksiz. Tarifsiz faol (eski) markaz: hammasi cheksiz.
     *
     * @return array{max_branches:?int,max_students:?int,sms_monthly:?int,ai_monthly:?int,unlimited:bool,name:?string}
     */
    public function limits(Center|int $center): array
    {
        $model = $center instanceof Center ? $center : Center::findOrFail($center);
        $sub = $this->current($model);

        return $this->buildLimits($model, $sub, $sub ? true : CenterSubscription::withoutGlobalScopes()->where('center_id', $model->id)->exists());
    }

    /**
     * Ko'p markaz uchun limitlar (ro'yxat sahifalari): joriy obunalar va «obunasi bor-yo'q» 2 ta so'rovda yuklanadi.
     *
     * @param  \Illuminate\Support\Collection<int,Center>  $centers
     * @return array<int, array>
     */
    public function limitsMany(\Illuminate\Support\Collection $centers): array
    {
        $ids = $centers->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $current = CenterSubscription::withoutGlobalScopes()->whereIn('center_id', $ids)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())->orderByDesc('starts_at')->get()->unique('center_id')->keyBy('center_id');
        $any = CenterSubscription::withoutGlobalScopes()->whereIn('center_id', $ids)->distinct()->pluck('center_id')->flip();

        $out = [];
        foreach ($centers as $c) {
            $out[$c->id] = $this->buildLimits($c, $current[$c->id] ?? null, isset($any[$c->id]));
        }

        return $out;
    }

    private function buildLimits(Center $model, ?CenterSubscription $sub, bool $hasAnySubscription): array
    {
        if ($sub) {
            $s = $sub->snapshot;

            return [
                'max_branches' => $s['max_branches'] ?? null, 'max_students' => $s['max_students'] ?? null,
                'sms_monthly' => (int) ($s['sms_monthly'] ?? 0), 'ai_monthly' => (int) ($s['ai_monthly'] ?? 0),
                'unlimited' => false, 'name' => $s['name'] ?? null,
            ];
        }

        $legacy = $model->status === CenterStatus::Active && $model->tariff_ends_at === null && ! $hasAnySubscription;

        return $legacy
            ? ['max_branches' => null, 'max_students' => null, 'sms_monthly' => null, 'ai_monthly' => null, 'unlimited' => true, 'name' => null]
            : ['max_branches' => 0, 'max_students' => 0, 'sms_monthly' => 0, 'ai_monthly' => 0, 'unlimited' => false, 'name' => null];
    }

    public function priceFor(Tariff $tariff, string $period): int
    {
        $price = $period === 'year' ? $tariff->price_yearly : $tariff->price_monthly;

        if ($price === null) {
            throw new DomainException("Bu tarifning narxi belgilanmagan.");
        }

        return (int) $price;
    }

    public function purchase(Center $center, Tariff $tariff, string $period, ?int $userId = null): CenterSubscription
    {
        $this->assertPeriod($period);
        if (! $tariff->is_active) {
            throw new DomainException("Bu tarif sotuvda emas.");
        }
        $price = $this->priceFor($tariff, $period);

        return DB::transaction(function () use ($center, $tariff, $period, $price, $userId) {
            $now = now();
            $current = $this->current($center);
            $starts = $now;

            if ($current) {
                $currentPrice = (int) ($current->snapshot['period_price'] ?? $current->price_paid);

                if ($price > $currentPrice) {
                    $current->source === 'purchase'
                        ? $this->refundRemainder($center, $current, $userId)
                        : $current->update(['ends_at' => $now]);
                } else {
                    $starts = $this->lastEnd($center);
                }
            }

            $this->balance->debit($center, $price, CenterBalanceTransaction::TARIFF_PURCHASE, $tariff->name.' ('.($period === 'year' ? 'yillik' : 'oylik').')', $userId);

            $sub = $this->create($center, $tariff, $period, $starts, $price, $price, 'purchase', $userId, null);
            $this->sync($center);

            return $sub;
        });
    }

    /** Tanlangan tarif qachon kuchga kiradi: qimmatroq — darhol, arzonroq/teng — joriy davr tugagach. */
    public function startsAtFor(Center $center, Tariff $tariff, string $period): Carbon
    {
        $current = $this->current($center);
        if (! $current) {
            return now();
        }

        $price = $this->priceFor($tariff, $period);
        $currentPrice = (int) ($current->snapshot['period_price'] ?? $current->price_paid);

        return $price > $currentPrice ? now() : $this->lastEnd($center);
    }

    /** sAdmin: tarifni to'lovsiz beradi (kun soni bo'yicha). */
    public function grant(Center $center, Tariff $tariff, int $days, ?string $note = null, ?int $userId = null): CenterSubscription
    {
        if ($days < 1 || $days > 3660) {
            throw new DomainException("Kunlar soni 1 dan 3660 gacha bo'lishi kerak.");
        }

        return DB::transaction(function () use ($center, $tariff, $days, $note, $userId) {
            $current = $this->current($center);
            $starts = now();

            if ($current) {
                $starts = $this->lastEnd($center);
            }

            $sub = $this->create($center, $tariff, 'month', $starts, 0, 0, 'grant', $userId, $note, $starts->copy()->addDays($days));
            $this->sync($center);

            return $sub;
        });
    }

    /** Markaz holati va `tariff_ends_at` ni obunalarga moslaydi. */
    public function sync(Center $center): void
    {
        $end = CenterSubscription::withoutGlobalScopes()->where('center_id', $center->id)->max('ends_at');
        $hasCurrent = $this->current($center) !== null;

        $center->forceFill(['tariff_ends_at' => $end ? Carbon::parse($end) : null]);

        if ($hasCurrent && in_array($center->status, [CenterStatus::Pending, CenterStatus::ReadOnly], true)) {
            $center->status = CenterStatus::Active;
            $center->read_only_since = null;
            $center->delete_after = null;
            $center->reminders_sent = null;
        } elseif ($hasCurrent) {
            $center->reminders_sent = null;
        }

        $center->save();
    }

    private function lastEnd(Center $center): Carbon
    {
        $end = CenterSubscription::withoutGlobalScopes()->where('center_id', $center->id)->max('ends_at');

        return $end ? Carbon::parse($end) : now();
    }

    private function create(Center $center, Tariff $tariff, string $period, Carbon $starts, int $paid, int $periodPrice, string $source, ?int $userId, ?string $note, ?Carbon $ends = null): CenterSubscription
    {
        return CenterSubscription::withoutGlobalScopes()->create([
            'center_id' => $center->id, 'tariff_id' => $tariff->id, 'period' => $period,
            'starts_at' => $starts, 'ends_at' => $ends ?? ($period === 'year' ? $starts->copy()->addYear() : $starts->copy()->addMonth()),
            'price_paid' => $paid, 'source' => $source, 'created_by' => $userId, 'note' => $note,
            'snapshot' => [
                'name' => $tariff->name, 'period_price' => $periodPrice,
                'max_branches' => $tariff->max_branches, 'max_students' => $tariff->max_students,
                'sms_monthly' => (int) $tariff->sms_monthly, 'ai_monthly' => (int) $tariff->ai_monthly,
            ],
        ]);
    }

    private function refundRemainder(Center $center, CenterSubscription $current, ?int $userId): void
    {
        $total = max(1, $current->starts_at->diffInSeconds($current->ends_at));
        $left = max(0, now()->diffInSeconds($current->ends_at, false));
        $refund = (int) floor($current->price_paid * $left / $total);

        $current->update(['ends_at' => now()]);

        if ($refund > 0) {
            $this->balance->credit($center, $refund, CenterBalanceTransaction::TARIFF_REFUND, $current->name().' qoldig\'i', $userId);
        }
    }

    private function assertPeriod(string $period): void
    {
        if (! in_array($period, ['month', 'year'], true)) {
            throw new DomainException("Davr noto'g'ri.");
        }
    }

    /** Yangi filial ochish mumkinmi (tarif limiti). */
    public function assertCanAddBranch(Center|int $center): void
    {
        $id = $center instanceof Center ? $center->id : $center;
        $limit = $this->limits($center)['max_branches'];

        if ($limit !== null && \App\Models\Branch::withoutGlobalScopes()->where('center_id', $id)->where('status', 'active')->count() >= $limit) {
            throw \Illuminate\Validation\ValidationException::withMessages(['name' => "Tarifingizda {$limit} ta filialgacha ruxsat. Ko'proq filial uchun tarifni almashtiring."]);
        }
    }

    /** Yangi faol o'quvchi qo'shish mumkinmi (tarif limiti). */
    public function assertCanAddStudents(Center|int $center, int $count = 1): void
    {
        $id = $center instanceof Center ? $center->id : $center;
        $limit = $this->limits($center)['max_students'];

        if ($limit !== null && $this->activeStudents($id) + $count > $limit) {
            throw \Illuminate\Validation\ValidationException::withMessages(['name' => "Tarifingizda {$limit} tagacha faol o'quvchiga ruxsat. Ko'proq o'quvchi uchun tarifni almashtiring."]);
        }
    }

    public function activeStudents(int $centerId): int
    {
        return \App\Models\User::where('center_id', $centerId)->where('role', \App\Enums\Role::Student)->where('status', 'active')->whereNull('archived_at')->count();
    }
}
