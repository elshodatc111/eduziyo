<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lead;
use App\Models\SmsMessage;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * «Kanareyka» sinovi: B markazning noyob so'zli ma'lumotlari A markaz foydalanuvchilariga HECH QAYSI sahifada ko'rinmasligi
 * va B yozuvlarining tafsilot sahifalari 404 berishi kerak. Yangi sahifa qo'shilsa, QueryBudgetTest::PAGES ga qo'shing.
 */
class TenantLeakCanaryTest extends TestCase
{
    use RefreshDatabase;

    private const CANARY = 'ZZKANAREYKA';

    public function test_center_a_never_sees_center_b_data(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $perms = array_keys(array_merge(...array_values(array_map(fn ($g) => $g['items'], config('permissions.groups')))));

        // --- A markaz ---
        $a = $this->center('A markaz');
        $branchA = $this->branch('A filial', $a);
        $adminA = $this->user(Role::Admin, $branchA, $perms);
        $ownerA = $this->user(Role::Owner, null, [], ['center_id' => $a->id]);

        // --- B markaz (barcha ma'lumot noyob so'z bilan) ---
        $b = $this->newCenter(self::CANARY.' markaz');
        $branchB = $this->branch(self::CANARY.' filial', $b);
        $adminB = $this->user(Role::Admin, $branchB, $perms, ['name' => self::CANARY.' admin']);
        $this->actingAs($adminB);
        $cat = $this->catalog($branchB);
        $cat['teacher']->update(['name' => self::CANARY.' ustoz']);
        $cat['course']->update(['name' => self::CANARY.' kurs']);
        $group = app(GroupService::class)->create($this->groupPayload($cat, ['name' => self::CANARY.' guruh', 'starts_on' => '2026-09-01']), $adminB);
        $student = $this->student($branchB, ['name' => self::CANARY.' talaba']);
        app(EnrollmentService::class)->enroll($group, $student, null, $adminB);
        app(PaymentService::class)->receive($student, ['cash' => 100000], $group, self::CANARY.' izoh', $adminB);
        $lead = Lead::create(['branch_id' => $branchB->id, 'name' => self::CANARY.' lid', 'phone' => '+998 90 999 88 77', 'status' => 'new']);
        SmsMessage::create(['branch_id' => $branchB->id, 'recipient_id' => $student->id, 'phone' => '+998 90 999 88 77', 'message' => self::CANARY.' sms', 'status' => 'sent', 'created_by' => $adminB->id]);
        $ticketB = app(\App\Services\SupportService::class)->open($b, $this->user(Role::Owner, null, [], ['center_id' => $b->id]), self::CANARY.' murojaat', 'matn');

        $lists = ['/dashboard', '/students', '/students/search?q='.self::CANARY, '/groups', '/payments', '/leads', '/staff', '/staff-directory', '/sms', '/attendance', '/attendance/stats',
            '/finance', '/cashbox', '/payroll', '/reports', '/statistics', '/audit-log', '/notes/feed', '/branches', '/markaz', '/markaz/direktorlar', '/markaz/tahlil',
            '/markaz/murojaat', '/markaz/sms', '/markaz/balans', '/markaz/tariflar', '/imports'];
        $details = ["/students/{$student->id}", "/groups/{$group->id}", "/leads/{$lead->id}", "/payroll/{$cat['teacher']->id}", "/markaz/murojaat/{$ticketB->id}"];

        // A markaz o'qituvchisi va o'quvchisi ham B ma'lumotini ko'rmasligi kerak
        $catA = $this->catalog($branchA);
        $teacherA = $catA['teacher'];
        $studentA = $this->student($branchA);
        $selfPages = ['/mening/jadval', '/mening/ish-haqi', '/cabinet', '/cabinet/guruhlar', '/cabinet/tolovlar', '/cabinet/kurslar', '/cabinet/xabarlar', '/attendance', '/groups'];
        foreach ([$teacherA, $studentA] as $who) {
            $this->actingAs($who);
            foreach ($selfPages as $url) {
                $res = $this->get($url);
                $this->assertStringNotContainsString(self::CANARY, (string) $res->getContent(), "{$who->role->value}: B ma'lumoti {$url} da ko'rindi!");
            }
            foreach ($details as $url) {
                $this->assertContains($this->get($url)->getStatusCode(), [302, 403, 404], "{$who->role->value}: B yozuvi {$url} ochildi");
            }
        }

        foreach ([$adminA, $ownerA] as $who) {
            $this->actingAs($who);
            foreach ($lists as $url) {
                $res = $this->get($url);
                $this->assertContains($res->getStatusCode(), [200, 302, 403, 404], "{$url} kutilmagan javob");
                $this->assertStringNotContainsString(self::CANARY, (string) $res->getContent(), "{$who->role->value}: B markaz ma'lumoti {$url} sahifasida ko'rindi!");
            }
            foreach ($details as $url) {
                $res = $this->get($url);
                $this->assertContains($res->getStatusCode(), [403, 404], "{$who->role->value}: B yozuvi {$url} ochildi (".$res->getStatusCode().')');
            }
        }
    }

    public function test_tenancy_scan_baseline_is_current(): void
    {
        $this->artisan('tenancy:scan')->assertExitCode(0);
    }
}
