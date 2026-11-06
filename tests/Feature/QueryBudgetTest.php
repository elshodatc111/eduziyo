<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LessonTime;
use App\Models\PricePlan;
use App\Models\Room;
use App\Models\SmsMessage;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * N+1 himoyasi: ro'yxat sahifalarida so'rovlar soni qatorlar soniga bog'liq bo'lmasligi kerak.
 * Har sahifa oz (2) va ko'p (12) ma'lumot bilan ochiladi; so'rovlar soni deyarli bir xil bo'lishi shart.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /** Sahifa ochiladigan yo'llar (ro'yxatlar va hisobotlar). */
    public const PAGES = [
        '/dashboard', '/students', '/groups', '/payments', '/leads', '/staff', '/sms', '/attendance', '/attendance/stats',
        '/finance', '/cashbox', '/payroll', '/reports', '/statistics', '/audit-log', '/notifications', '/notes/feed',
    ];

    private int $seq = 0;

    private function fill(object $branch, object $admin, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->seq++;
            $t = $this->user(Role::Teacher, $branch, ['attendance.view', 'attendance.take']);
            $c = [
                'teacher' => $t,
                'course' => Course::create(['branch_id' => $branch->id, 'name' => "Kurs {$this->seq}"]),
                'room' => Room::create(['branch_id' => $branch->id, 'name' => "Xona {$this->seq}"]),
                'time' => LessonTime::create(['branch_id' => $branch->id, 'starts_at' => sprintf('%02d:00', 6 + ($this->seq % 14)), 'ends_at' => sprintf('%02d:30', 6 + ($this->seq % 14))]),
                'plan' => PricePlan::create(['branch_id' => $branch->id, 'name' => "Tarif {$this->seq}", 'amount' => 500000, 'early_discount' => 0]),
            ];
            $g = app(GroupService::class)->create($this->groupPayload($c, ['name' => "Guruh {$this->seq}", 'starts_on' => '2026-09-01']), $admin);

            $s = $this->student($branch, ['name' => "O'quvchi {$this->seq}"]);
            app(EnrollmentService::class)->enroll($g, $s, null, $admin);
            app(PaymentService::class)->receive($s, ['cash' => 100000], $g, 'Oylik', $admin);

            Lead::create(['branch_id' => $branch->id, 'name' => "Lid {$this->seq}", 'phone' => sprintf('+998 90 %03d %02d %02d', $this->seq, 10 + $this->seq % 80, 10 + $this->seq % 80), 'status' => 'new']);
            SmsMessage::create(['branch_id' => $branch->id, 'recipient_id' => $s->id, 'phone' => $s->phone ?? '+998 90 111 22 33', 'message' => 'Salom', 'status' => 'sent', 'created_by' => $admin->id]);
        }
    }

    private function queries(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $status = $this->get($url)->getStatusCode();
        $n = $status === 200 ? count(DB::getQueryLog()) : -$status;
        DB::disableQueryLog();

        return $n;
    }

    public function test_list_pages_do_not_grow_queries_with_row_count(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch('Toshkent');
        $perms = array_keys(array_merge(...array_values(array_map(fn ($g) => $g['items'], config('permissions.groups')))));
        $admin = $this->user(Role::Admin, $branch, $perms);
        $this->actingAs($admin);

        $this->fill($branch, $admin, 2);
        $few = [];
        foreach (self::PAGES as $url) {
            $this->queries($url);                    // issiq (kesh, sessiya)
            $few[$url] = $this->queries($url);
        }

        $this->fill($branch, $admin, 10);
        $report = [];
        foreach (self::PAGES as $url) {
            $many = $this->queries($url);
            $report[$url] = [$few[$url], $many];
        }

        $bad = array_filter($report, fn ($r) => $r[0] > 0 && $r[1] > $r[0] + 3);
        fwrite(STDERR, "\n".json_encode($report, JSON_UNESCAPED_SLASHES)."\n");
        $this->assertSame([], $bad, "N+1 gumon: so'rovlar soni qatorlar bilan o'sdi (oz → ko'p): ".json_encode($bad, JSON_UNESCAPED_SLASHES));
    }

    public function test_detail_pages_do_not_grow_queries_with_related_rows(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch('Toshkent');
        $perms = array_keys(array_merge(...array_values(array_map(fn ($g) => $g['items'], config('permissions.groups')))));
        $admin = $this->user(Role::Admin, $branch, $perms);
        $this->actingAs($admin);

        $c = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($c, ['starts_on' => '2026-09-01']), $admin);
        $first = $this->student($branch);
        app(EnrollmentService::class)->enroll($group, $first, null, $admin);

        $urls = ["/groups/{$group->id}", "/students/{$first->id}", "/payroll/{$c['teacher']->id}"];
        $few = [];
        foreach ($urls as $u) {
            $this->queries($u);
            $few[$u] = $this->queries($u);
        }

        for ($i = 0; $i < 12; $i++) {
            $s = $this->student($branch);
            app(EnrollmentService::class)->enroll($group, $s, null, $admin);
            app(PaymentService::class)->receive($s, ['cash' => 50000], $group, 'Oylik', $admin);
            app(PaymentService::class)->receive($first, ['cash' => 10000], $group, 'Qo\'shimcha', $admin);
        }

        $report = [];
        foreach ($urls as $u) {
            $report[$u] = [$few[$u], $this->queries($u)];
        }
        fwrite(STDERR, "\n".json_encode($report, JSON_UNESCAPED_SLASHES)."\n");
        $bad = array_filter($report, fn ($r) => $r[0] > 0 && $r[1] > $r[0] + 3);
        $this->assertSame([], $bad, "N+1 gumon (tafsilot sahifalari): ".json_encode($bad, JSON_UNESCAPED_SLASHES));
    }

    public function test_platform_lists_do_not_grow_queries_with_centers(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin);
        $urls = ['/platform/markazlar', '/platform/toldirish', '/platform/murojaatlar', '/platform/sms', '/platform/tahlil', '/platform/hujjatlar', '/superadmins', '/sales/applications'];

        $mk = function (int $n) {
            for ($i = 0; $i < $n; $i++) {
                $c = $this->newCenter('Markaz '.uniqid());
                $b = $this->branch('Filial '.uniqid(), $c);
                $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
                \App\Models\TopupRequest::create(['center_id' => $c->id, 'card_id' => null, 'amount_claimed' => 100000, 'paid_at' => now(), 'receipt_path' => 'x', 'receipt_hash' => uniqid().uniqid(), 'requested_by' => $owner->id]);
                app(\App\Services\SupportService::class)->open($c, $owner, 'Mavzu '.$i, 'Matn');
            }
        };

        $mk(2);
        $few = [];
        foreach ($urls as $u) {
            $this->queries($u);
            $few[$u] = $this->queries($u);
        }
        $mk(10);
        $report = [];
        foreach ($urls as $u) {
            $report[$u] = [$few[$u], $this->queries($u)];
        }
        fwrite(STDERR, "\n".json_encode($report, JSON_UNESCAPED_SLASHES)."\n");
        $bad = array_filter($report, fn ($r) => $r[0] > 0 && $r[1] > $r[0] + 3);
        $this->assertSame([], $bad, "N+1 gumon (platforma): ".json_encode($bad, JSON_UNESCAPED_SLASHES));
    }
}
