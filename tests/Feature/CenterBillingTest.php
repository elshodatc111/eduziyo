<?php

namespace Tests\Feature;

use App\Enums\CenterStatus;
use App\Enums\Role;
use App\Models\Center;
use App\Models\CenterBalanceTransaction;
use App\Models\PaymentCard;
use App\Models\Tariff;
use App\Models\TopupRequest;
use App\Services\CenterAlerts;
use App\Services\CenterBalanceService;
use App\Services\CenterLifecycle;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** E4: tarif, obuna, markaz balansi, to'ldirish, limit, hayot sikli. */
class CenterBillingTest extends TestCase
{
    use RefreshDatabase;

    private function tariff(string $name, int $monthly, array $over = []): Tariff
    {
        return Tariff::forceCreate(['slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(), 'name' => $name, 'price_monthly' => $monthly, 'price_yearly' => $monthly * 10, 'max_branches' => 2, 'max_students' => 5,
            'sms_monthly' => 3, 'ai_monthly' => 2, 'is_active' => true, 'sort_order' => 50, ...$over]);
    }

    private function newPendingCenter(): Center
    {
        return Center::create(['name' => 'Yangi markaz '.uniqid(), 'code' => Center::makeCode('Yangi markaz '.uniqid()), 'status' => CenterStatus::Pending]);
    }

    public function test_balance_moves_are_logged_and_never_negative(): void
    {
        $c = $this->center();
        $b = app(CenterBalanceService::class);

        $b->credit($c, 100_000, CenterBalanceTransaction::TOPUP, 'test');
        $b->debit($c, 40_000, CenterBalanceTransaction::TARIFF_PURCHASE);
        $this->assertSame(60_000, $b->balance($c));

        $this->expectException(DomainException::class);
        try {
            $b->debit($c, 60_001, CenterBalanceTransaction::TARIFF_PURCHASE);
        } finally {
            $this->assertSame(60_000, $b->balance($c));
            $this->assertSame(2, CenterBalanceTransaction::withoutGlobalScopes()->where('center_id', $c->id)->count());
        }
    }

    public function test_purchase_activates_pending_center_and_snapshots_limits(): void
    {
        $c = $this->newPendingCenter();
        $t = $this->tariff('Mini', 100_000);
        app(CenterBalanceService::class)->credit($c, 150_000, 'topup');

        $sub = app(SubscriptionService::class)->purchase($c, $t, 'month');

        $c->refresh();
        $this->assertSame(CenterStatus::Active, $c->status);
        $this->assertSame(50_000, app(CenterBalanceService::class)->balance($c));
        $this->assertEquals($sub->ends_at->timestamp, $c->tariff_ends_at->timestamp);

        $t->update(['max_students' => 999]);   // yangi narx/limit eski obunaga ta'sir qilmaydi
        $this->assertSame(5, app(SubscriptionService::class)->limits($c)['max_students']);
    }

    public function test_purchase_fails_without_enough_balance(): void
    {
        $c = $this->newPendingCenter();
        $this->expectException(DomainException::class);
        app(SubscriptionService::class)->purchase($c, $this->tariff('Mini', 100_000), 'month');
    }

    public function test_upgrade_starts_now_with_refund_and_downgrade_is_queued(): void
    {
        $c = $this->newPendingCenter();
        $svc = app(SubscriptionService::class);
        $small = $this->tariff('Kichik', 100_000);
        $big = $this->tariff('Katta', 300_000);
        app(CenterBalanceService::class)->credit($c, 1_000_000, 'topup');

        $svc->purchase($c, $small, 'month');
        $this->travel(15)->days();

        $svc->purchase($c, $big, 'month');
        $this->assertSame('Katta', $svc->current($c)->name());
        $refund = CenterBalanceTransaction::withoutGlobalScopes()->where('center_id', $c->id)->where('type', 'tariff_refund')->first();
        $this->assertNotNull($refund);
        $this->assertEqualsWithDelta(50_000, $refund->amount, 4_000);   // ~yarim oy qoldig'i

        $svc->purchase($c, $small, 'month');   // arzonroq - navbatga
        $this->assertSame('Katta', $svc->current($c)->name());
        $this->assertSame(1, $svc->upcoming($c)->count());
        $this->assertSame('Kichik', $svc->upcoming($c)->first()->name());
    }

    public function test_sadmin_can_grant_tariff_and_old_center_is_unlimited(): void
    {
        $legacy = $this->center();
        $this->assertTrue(app(SubscriptionService::class)->limits($legacy)['unlimited']);

        $c = $this->newPendingCenter();
        app(SubscriptionService::class)->grant($c, $this->tariff('Sovg\'a', 100_000), 45, 'aksiya');
        $c->refresh();
        $this->assertSame(CenterStatus::Active, $c->status);
        $this->assertEquals(now()->addDays(45)->toDateString(), $c->tariff_ends_at->toDateString());
        $this->assertFalse(app(SubscriptionService::class)->limits($c)['unlimited']);
    }

    public function test_branch_and_student_limits_are_enforced(): void
    {
        $c = $this->newPendingCenter();
        app(SubscriptionService::class)->grant($c, $this->tariff('Kichik', 1, ['max_branches' => 1, 'max_students' => 1]), 30);

        $svc = app(SubscriptionService::class);
        $branch = $this->branch('Birinchi', $c);
        $this->expectException(ValidationException::class);
        try {
            $svc->assertCanAddBranch($c);
        } finally {
            $this->student($branch);
            try {
                $svc->assertCanAddStudents($c);
                $this->fail("O'quvchi limiti ishlamadi");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_pending_center_is_read_only_for_writes_but_can_read_and_pay(): void
    {
        $c = $this->newPendingCenter();
        $branch = $this->branch('Filial', $c);
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
        $admin = $this->user(Role::Admin, $branch, ['staff.create', 'students.create', 'students.view']);

        $this->actingAs($admin)->post(route('students.store'), ['name' => 'Ali', 'phone' => '+998 90 111 2233'])
            ->assertSessionHas('error');
        $this->assertSame(0, \App\Models\User::where('center_id', $c->id)->where('role', Role::Student)->count());

        $this->actingAs($owner)->get(route('center.tariffs'))->assertOk();
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Tarif tanlanmagan');
    }

    public function test_sadmin_is_never_blocked_and_active_center_writes_normally(): void
    {
        $c = $this->center();
        $branch = $this->branch('F', $c);
        $admin = $this->user(Role::Admin, $branch, ['students.create', 'students.view']);

        $this->actingAs($admin)->post(route('students.store'), ['name' => 'Ali Valiyev', 'phone' => '+998 90 111 2233'])->assertSessionMissing('error');
        $this->assertSame(1, \App\Models\User::where('center_id', $c->id)->where('role', Role::Student)->count());
    }

    public function test_lifecycle_reminds_once_then_goes_read_only_then_deletes(): void
    {
        $c = $this->newPendingCenter();
        $branch = $this->branch('Filial', $c);
        $this->user(Role::Admin, $branch);
        app(SubscriptionService::class)->grant($c, $this->tariff('Mini', 1), 10);
        CenterAlerts::$sent = [];
        $life = app(CenterLifecycle::class);

        $this->travel(4)->days();                       // 6 kun qoldi → T-7
        $life->run();
        $life->run();                                   // ikkinchi marta qayta yubormaydi
        $this->assertCount(1, CenterAlerts::$sent);

        $this->travel(7)->days();                       // tugagan
        $stats = $life->run();
        $this->assertSame(1, $stats['read_only']);
        $c->refresh();
        $this->assertSame(CenterStatus::ReadOnly, $c->status);
        $this->assertNotNull($c->delete_after);

        $this->travel(28)->days();                      // T+27: oxirgi ogohlantirish
        $life->run();
        $this->assertStringContainsString("o'chiriladi", collect(CenterAlerts::$sent)->last()['text']);

        $this->travel(5)->days();                       // T+30 dan keyin
        $stats = $life->run();
        $this->assertSame(1, $stats['deleted']);
        $c->refresh();
        $this->assertSame(CenterStatus::Deleted, $c->status);
        $this->assertSame(0, \App\Models\User::where('center_id', $c->id)->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('branches')->where('center_id', $c->id)->count());
    }

    public function test_lifecycle_ignores_demo_and_legacy_centers_and_renewal_clears_read_only(): void
    {
        $legacy = $this->center();
        $demo = Center::create(['name' => 'Demo X', 'code' => 'demo-x', 'status' => 'active', 'is_demo' => true, 'tariff_ends_at' => now()->subDay()]);
        $stats = app(CenterLifecycle::class)->run();
        $this->assertSame(0, $stats['read_only']);
        $this->assertSame(CenterStatus::Active, $demo->refresh()->status);

        $c = $this->newPendingCenter();
        app(SubscriptionService::class)->grant($c, $this->tariff('Mini', 1), 5);
        $this->travel(6)->days();
        app(CenterLifecycle::class)->run();
        $this->assertSame(CenterStatus::ReadOnly, $c->refresh()->status);

        app(CenterBalanceService::class)->credit($c, 100_000, 'topup');
        app(SubscriptionService::class)->purchase($c, $this->tariff('Kun', 50_000), 'month');
        $c->refresh();
        $this->assertSame(CenterStatus::Active, $c->status);
        $this->assertNull($c->delete_after);
    }

    public function test_usage_counters_respect_tariff_limits(): void
    {
        $c = $this->newPendingCenter();
        app(SubscriptionService::class)->grant($c, $this->tariff('Mini', 1, ['sms_monthly' => 2, 'ai_monthly' => 0]), 30);
        $u = app(UsageService::class);

        $this->assertTrue($u->consume($c, 'sms'));
        $this->assertTrue($u->consume($c, 'sms'));
        $this->assertFalse($u->consume($c, 'sms'));
        $this->assertSame(2, $u->used($c, 'sms'));
        $this->assertFalse($u->consume($c, 'ai'));   // 0 = tarifda yo'q
    }

    public function test_topup_flow_owner_uploads_receipt_sadmin_approves_balance_grows(): void
    {
        Storage::fake('local');
        $c = $this->center();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
        $sadmin = $this->user(Role::SAdmin);
        $card = PaymentCard::create(['number' => '8600 1111 2222 3333', 'holder' => 'ATKO', 'bank' => 'Test']);

        $payload = fn () => [
            'card_id' => $card->id, 'amount_claimed' => 250000, 'paid_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'receipt' => UploadedFile::fake()->createWithContent('chek.png', 'bir-xil-chek'),
        ];

        $this->actingAs($owner)->get(route('center.topup'))->assertOk()
            ->assertSee('8600 1111 2222 3333')                 // guruhlangan ko'rinish
            ->assertSee('8600111122223333')                    // nusxalanadigan toza raqam
            ->assertSee('Nusxalash');

        $this->actingAs($owner)->post(route('center.topup.store'), $payload())->assertRedirect(route('center.topup'));
        $topup = TopupRequest::withoutGlobalScopes()->first();
        $this->assertSame('pending', $topup->status);
        $this->assertSame(0, app(CenterBalanceService::class)->balance($c));

        // Bir xil chek qayta yuklanmaydi
        $this->actingAs($owner)->post(route('center.topup.store'), $payload())->assertSessionHasErrors('receipt');

        $this->actingAs($sadmin)->post(route('platform.topups.approve', $topup->id), ['approved_amount' => 240000])->assertSessionHas('success');
        $this->assertSame(240000, app(CenterBalanceService::class)->balance($c));
        $this->assertSame('approved', $topup->refresh()->status);

        // Qayta tasdiqlab bo'lmaydi
        $this->actingAs($sadmin)->post(route('platform.topups.approve', $topup->id), ['approved_amount' => 240000])->assertStatus(409);
        $this->assertSame(240000, app(CenterBalanceService::class)->balance($c));
    }

    public function test_topup_reject_and_receipt_isolation(): void
    {
        Storage::fake('local');
        $c = $this->center();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
        $other = $this->newCenter('Boshqa markaz');
        $otherOwner = $this->user(Role::Owner, null, [], ['center_id' => $other->id]);
        $sadmin = $this->user(Role::SAdmin);
        $card = PaymentCard::create(['number' => '8600 9999', 'holder' => 'ATKO']);

        $this->actingAs($owner)->post(route('center.topup.store'), [
            'card_id' => $card->id, 'amount_claimed' => 5000, 'paid_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'receipt' => UploadedFile::fake()->createWithContent('a.pdf', 'pdf-1'),
        ]);
        $topup = TopupRequest::withoutGlobalScopes()->first();

        $this->actingAs($otherOwner)->get(route('center.receipt', $topup->id))->assertNotFound();
        $this->actingAs($owner)->get(route('center.receipt', $topup->id))->assertOk();

        $this->actingAs($sadmin)->post(route('platform.topups.reject', $topup->id), ['reject_reason' => 'Chek o\'qilmaydi'])->assertSessionHas('success');
        $this->assertSame('rejected', $topup->refresh()->status);
        $this->assertSame(0, app(CenterBalanceService::class)->balance($c));
    }

    public function test_only_owner_opens_center_panel_and_only_sadmin_platform(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, []);
        $owner = $this->user(Role::Owner);

        $this->actingAs($admin)->get(route('center.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('platform.centers.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('platform.centers.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('center.index'))->assertOk()->assertSee('Limitlar');
        $this->actingAs($owner)->get(route('center.analytics'))->assertOk()->assertSee('Yangi o&#039;quvchilar', false);
    }

    public function test_sadmin_creates_center_with_owner_and_pending_status(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $res = $this->actingAs($sadmin)->post(route('platform.centers.store'), [
            'name' => 'Yulduz Academy', 'branch_name' => 'Chilonzor', 'owner_name' => 'Aziz Karimov', 'owner_phone' => '+998 90 555 1122',
        ]);

        $center = Center::where('name', 'Yulduz Academy')->firstOrFail();
        $res->assertRedirect(route('platform.centers.show', $center))->assertSessionHas('credentials');
        $this->assertSame(CenterStatus::Pending, $center->status);
        $this->assertSame(1, \App\Models\Branch::where('center_id', $center->id)->count());
        $owner = \App\Models\User::where('center_id', $center->id)->where('role', Role::Owner)->firstOrFail();
        $this->assertSame($owner->id, $center->owner_user_id);

        $this->actingAs($sadmin)->get(route('platform.centers.show', $center))->assertOk()->assertSee('Yulduz Academy');
        $this->actingAs($sadmin)->get(route('platform.analytics'))->assertOk();
        $this->actingAs($sadmin)->get(route('platform.topups'))->assertOk();
    }

    public function test_sadmin_suspend_blocks_login_and_resume_restores(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $c = $this->center();
        $admin = $this->user(Role::Admin, $this->branch('F', $c), [], ['username' => 'adminx', 'password' => 'parol12345']);

        $this->actingAs($sadmin)->post(route('platform.centers.suspend', $c))->assertSessionHas('success');
        $this->assertSame(CenterStatus::Suspended, $c->refresh()->status);

        $this->actingAs($sadmin)->post(route('platform.centers.resume', $c));
        $this->assertNotSame(CenterStatus::Suspended, $c->refresh()->status);
    }

    public function test_owner_manages_own_branches_within_limit(): void
    {
        $c = $this->newPendingCenter();
        app(SubscriptionService::class)->grant($c, $this->tariff('Bir', 1, ['max_branches' => 1]), 30);
        $this->branch('Mavjud', $c);
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);

        $this->actingAs($owner)->get(route('branches.index'))->assertOk();
        $this->actingAs($owner)->post(route('branches.store'), ['name' => 'Ikkinchi'])->assertSessionHasErrors('name');
        $this->assertSame(1, \App\Models\Branch::where('center_id', $c->id)->count());
    }

    public function test_owner_exports_excel_and_read_only_export_follows_setting(): void
    {
        $c = $this->center();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
        $this->student($this->branch('F', $c));

        $res = $this->actingAs($owner)->get(route('center.export', 'students'));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('content-type'));

        $c->update(['status' => CenterStatus::ReadOnly]);
        $this->actingAs($owner)->get(route('center.export', 'groups'))->assertOk();
        \App\Models\PlatformSetting::put('allow_export_in_read_only', '0');
        $this->actingAs($owner)->get(route('center.export', 'groups'))->assertForbidden();
        $this->actingAs($owner)->get(route('center.export', 'nomalum'))->assertNotFound();
    }
}
