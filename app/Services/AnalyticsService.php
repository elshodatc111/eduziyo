<?php

namespace App\Services;

use App\Models\Center;
use App\Models\CenterBalanceTransaction;
use App\Models\CenterSubscription;
use App\Models\UsageCounter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Markazlar tahlili. Barcha so'rovlar aniq `center_id` bo'yicha (global scope'ga tayanmaydi), oy bo'yicha guruhlash PHP'da
 * (SQLite/MariaDB farqlariga bog'liq bo'lmaslik uchun). Shaxsiy ma'lumot (ism/telefon) qaytarilmaydi - faqat sonlar.
 */
class AnalyticsService
{
    public function __construct(private SubscriptionService $subs, private CenterBalanceService $balance) {}

    /** @return list<string> 'Y-m' ko'rinishidagi oxirgi $months oy (eskidan yangiga) */
    public function months(int $months = 6): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $out[] = now()->startOfMonth()->subMonths($i)->format('Y-m');
        }

        return $out;
    }

    /**
     * Bitta markaz bo'yicha batafsil tahlil.
     *
     * @return array<string,mixed>
     */
    public function forCenter(Center $center, int $months = 6): array
    {
        $labels = $this->months($months);
        $from = Carbon::createFromFormat('Y-m', $labels[0])->startOfMonth();

        $series = fn (iterable $rows, callable $value) => $this->bucket($labels, $rows, $value);

        $newStudents = DB::table('users')->where('center_id', $center->id)->where('role', 'student')->where('created_at', '>=', $from)->pluck('created_at');
        $leads = DB::table('leads')->join('branches', 'branches.id', '=', 'leads.branch_id')->where('branches.center_id', $center->id)->where('leads.created_at', '>=', $from)->pluck('leads.created_at');
        $payments = DB::table('payments')->join('branches', 'branches.id', '=', 'payments.branch_id')->where('branches.center_id', $center->id)
            ->where('payments.type', 'payment')->whereNull('payments.reversed_at')->where('payments.created_at', '>=', $from)->get(['payments.created_at', 'payments.amount']);
        $topups = CenterBalanceTransaction::withoutGlobalScopes()->where('center_id', $center->id)->where('type', 'topup')->where('created_at', '>=', $from)->get(['created_at', 'amount']);
        $archived = DB::table('users')->where('center_id', $center->id)->where('role', 'student')->whereNotNull('archived_at')->where('archived_at', '>=', $from)->pluck('archived_at');

        $usage = UsageCounter::withoutGlobalScopes()->where('center_id', $center->id)->where('period_start', '>=', $from->toDateString())->get();
        $usageSeries = fn (string $kind) => array_map(
            fn ($m) => (int) $usage->first(fn ($u) => $u->kind === $kind && $u->period_start->format('Y-m') === $m)?->used,
            $labels
        );

        $limits = $this->subs->limits($center);
        $activeStudents = $this->subs->activeStudents($center->id);
        $debtors = DB::table('users')->where('center_id', $center->id)->where('role', 'student')->where('status', 'active')->where('balance', '<', 0)->count();

        return [
            'labels' => array_map(fn ($m) => Carbon::createFromFormat('Y-m', $m)->format('m.Y'), $labels),
            'new_students' => $series($newStudents, fn () => 1),
            'leads' => $series($leads, fn () => 1),
            'revenue' => $series($payments, fn ($r) => (int) $r->amount),
            'topups' => $series($topups, fn ($r) => (int) $r->amount),
            'churn' => $series($archived, fn () => 1),
            'sms' => $usageSeries('sms'),
            'ai' => $usageSeries('ai'),
            'totals' => [
                'active_students' => $activeStudents,
                'debtors' => $debtors,
                'branches' => DB::table('branches')->where('center_id', $center->id)->where('status', 'active')->count(),
                'groups' => DB::table('groups')->join('branches', 'branches.id', '=', 'groups.branch_id')->where('branches.center_id', $center->id)->whereDate('groups.ends_on', '>=', today())->count(),
                'balance' => $this->balance->balance($center),
                'limits' => $limits,
            ],
        ];
    }

    /**
     * Platforma (sAdmin) umumiy ko'rinishi: har bir markaz uchun bitta qator.
     *
     * @return array{rows: list<array<string,mixed>>, summary: array<string,int>}
     */
    public function platform(): array
    {
        $since = now()->subDays(30);
        $monthStart = now()->startOfMonth()->toDateString();

        $revenue = DB::table('payments')->join('branches', 'branches.id', '=', 'payments.branch_id')
            ->where('payments.type', 'payment')->whereNull('payments.reversed_at')->where('payments.created_at', '>=', $since)
            ->groupBy('branches.center_id')->selectRaw('branches.center_id as cid, SUM(payments.amount) as total')->pluck('total', 'cid');
        $students = DB::table('users')->where('role', 'student')->where('status', 'active')->whereNull('archived_at')->whereNotNull('center_id')
            ->groupBy('center_id')->selectRaw('center_id as cid, COUNT(*) as n')->pluck('n', 'cid');
        $branches = DB::table('branches')->where('status', 'active')->groupBy('center_id')->selectRaw('center_id as cid, COUNT(*) as n')->pluck('n', 'cid');
        $logins = DB::table('users')->whereNotNull('center_id')->groupBy('center_id')->selectRaw('center_id as cid, MAX(last_login_at) as last_login')->pluck('last_login', 'cid');
        $usage = UsageCounter::withoutGlobalScopes()->where('period_start', $monthStart)->get()->groupBy('center_id');
        $topups = CenterBalanceTransaction::withoutGlobalScopes()->where('type', 'topup')->where('created_at', '>=', $since)->selectRaw('center_id, SUM(amount) as total')->groupBy('center_id')->pluck('total', 'center_id');
        $balances = DB::table('center_wallets')->pluck('balance', 'center_id');

        $rows = [];
        $centers = Center::where('status', '!=', 'deleted')->where('is_demo', false)->orderBy('name')->get();
        $allLimits = $this->subs->limitsMany($centers);
        foreach ($centers as $c) {
            $u = $usage->get($c->id, collect());
            $limits = $allLimits[$c->id];
            $rows[] = [
                'center' => $c,
                'tariff' => $limits['unlimited'] ? 'Cheksiz (eski)' : ($limits['name'] ?? '—'),
                'students' => (int) ($students[$c->id] ?? 0),
                'branches' => (int) ($branches[$c->id] ?? 0),
                'revenue30' => (int) ($revenue[$c->id] ?? 0),
                'topups30' => (int) ($topups[$c->id] ?? 0),
                'balance' => (int) ($balances[$c->id] ?? 0),
                'sms' => (int) ($u->firstWhere('kind', 'sms')?->used ?? 0),
                'ai' => (int) ($u->firstWhere('kind', 'ai')?->used ?? 0),
                'last_login' => ($logins[$c->id] ?? null) ? Carbon::parse($logins[$c->id]) : null,
            ];
        }

        $all = Center::where('is_demo', false)->get();

        return [
            'rows' => $rows,
            'summary' => [
                'centers' => $all->where('status', '!=', \App\Enums\CenterStatus::Deleted)->count(),
                'active' => $all->where('status', \App\Enums\CenterStatus::Active)->count(),
                'read_only' => $all->where('status', \App\Enums\CenterStatus::ReadOnly)->count(),
                'pending' => $all->where('status', \App\Enums\CenterStatus::Pending)->count(),
                'students' => (int) array_sum(array_column($rows, 'students')),
                'sales30' => (int) CenterSubscription::withoutGlobalScopes()->where('source', 'purchase')->where('created_at', '>=', $since)->sum('price_paid'),
                'topups30' => (int) array_sum(array_column($rows, 'topups30')),
            ],
        ];
    }

    /**
     * @param  list<string>  $labels
     * @return list<int>
     */
    private function bucket(array $labels, iterable $rows, callable $value): array
    {
        $out = array_fill_keys($labels, 0);
        foreach ($rows as $row) {
            $at = is_object($row) ? ($row->created_at ?? null) : $row;
            $key = Carbon::parse($at)->format('Y-m');
            if (isset($out[$key])) {
                $out[$key] += $value($row);
            }
        }

        return array_values($out);
    }
}
