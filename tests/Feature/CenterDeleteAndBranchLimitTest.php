<?php

namespace Tests\Feature;

use App\Enums\CenterStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Center;
use App\Models\CenterBalanceTransaction;
use App\Models\LegalDocument;
use App\Models\Tariff;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\BranchLimitService;
use App\Services\CenterBalanceService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CenterDeleteAndBranchLimitTest extends TestCase
{
    use RefreshDatabase;

    private function tariff(string $name, int $branches, int $price = 100_000): Tariff
    {
        return Tariff::forceCreate(['slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(), 'name' => $name, 'price_monthly' => $price, 'price_yearly' => $price * 10, 'max_branches' => $branches,
            'max_students' => 100, 'sms_monthly' => 10, 'ai_monthly' => 0, 'is_active' => true, 'sort_order' => 1]);
    }

    private function threeBranches(): array
    {
        return [$this->branch('Birinchi'), $this->branch('Ikkinchi'), $this->branch('Uchinchi')];
    }

    public function test_extra_branches_become_read_only_after_downgrade_and_unlock_on_upgrade(): void
    {
        [$b1, $b2, $b3] = $this->threeBranches();
        $subs = app(SubscriptionService::class);
        $center = $this->center();
        $subs->grant($center, $this->tariff('Start', 1), 30);

        $limits = app(BranchLimitService::class);
        $this->assertSame([$b2->id, $b3->id], $limits->lockedIds($center));

        $first = $this->user(Role::Admin, $b1, ['students.view', 'students.create']);
        $second = $this->user(Role::Admin, $b2, ['students.view', 'students.create']);
        $payload = ['name' => 'Yangi', 'phone' => '+998 90 111 2233'];

        // faqat ko'rish: GET ochiq, yozish yopiq
        $this->actingAs($second)->get(route('students.index'))->assertOk();
        $this->post(route('students.store'), $payload)->assertSessionHas('error');
        $this->assertSame(0, User::withoutGlobalScopes()->where('role', Role::Student)->count());

        // birinchi filial ishlaydi
        $this->actingAs($first)->post(route('students.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, User::withoutGlobalScopes()->where('role', Role::Student)->count());

        // qimmatroq tarif — hammasi ochiladi
        $subs->grant($center, $this->tariff('Pro', 3), 30);
        // grant navbatga qo'yiladi; joriy tarif hali Start. Joriy obunani tugatib, tekshiramiz
        \App\Models\CenterSubscription::withoutGlobalScopes()->where('center_id', $center->id)->orderBy('id')->first()->update(['ends_at' => now()->subSecond()]);
        \App\Models\CenterSubscription::withoutGlobalScopes()->where('center_id', $center->id)->orderByDesc('id')->first()->update(['starts_at' => now()->subMinute()]);
        $this->assertSame([], $limits->lockedIds($center));
        $this->actingAs($second)->post(route('students.store'), ['name' => 'Ikkinchi', 'phone' => '+998 90 222 3344'])->assertSessionHasNoErrors();
    }

    public function test_owner_cannot_edit_records_of_locked_branch_via_route_binding(): void
    {
        [$b1, $b2] = $this->threeBranches();
        app(SubscriptionService::class)->grant($this->center(), $this->tariff('Start', 1), 30);
        $student = $this->student($b2);
        $owner = $this->user(Role::Owner);

        $this->actingAs($owner)->put(route('students.update', $student), ['name' => 'Boshqa', 'phone' => $student->phone])->assertSessionHas('error');
        $this->assertNotSame('BOSHQA', $student->fresh()->name);
    }

    public function test_purchase_of_lower_tariff_requires_branch_warning_acknowledgement(): void
    {
        $this->threeBranches();
        $center = $this->center();
        $owner = $this->user(Role::Owner);
        app(CenterBalanceService::class)->credit($center, 500_000, CenterBalanceTransaction::TOPUP, 'test');
        $small = $this->tariff('Kichik', 1);

        $page = $this->actingAs($owner)->get(route('center.tariffs'))->assertOk();
        $page->assertSee('faqat ko', false)->assertSee('Ikkinchi');

        $this->post(route('center.tariffs.buy', $small), ['period' => 'month'])->assertSessionHas('error');
        $this->assertNull(app(SubscriptionService::class)->current($center));

        $this->post(route('center.tariffs.buy', $small), ['period' => 'month', 'ack_branches' => 1])->assertSessionHas('success');
        $this->assertNotNull(app(SubscriptionService::class)->current($center));
        $this->assertCount(2, app(BranchLimitService::class)->lockedIds($center));
    }

    public function test_sadmin_deletes_center_with_all_branches_after_confirmation(): void
    {
        $victim = $this->newCenter('Ochiriladigan markaz');
        $b1 = $this->branch('F1', $victim);
        $this->branch('F2', $victim);
        $this->branch('F3', $victim);
        $staff = $this->user(Role::Admin, $b1);
        $staff->forceFill(['center_id' => $victim->id])->save();
        $other = $this->center();
        $this->branch('Boshqa filial', $other);

        // rozilik jurnali markaz o'chganda saqlanadi
        UserConsent::create(['user_id' => $staff->id, 'user_name' => $staff->name, 'center_ref' => $victim->id, 'center_name' => $victim->name,
            'legal_document_id' => LegalDocument::first()->id, 'accepted_at' => now(), 'method' => 'web']);

        $sadmin = $this->user(Role::SAdmin);
        $url = route('platform.centers.destroy', $victim);

        $this->actingAs($this->user(Role::Owner))->delete($url, ['confirm_name' => $victim->name, 'password' => 'parol12345'])->assertForbidden();
        $this->actingAs($sadmin)->delete($url, ['confirm_name' => 'Noto\'g\'ri', 'password' => 'parol12345'])->assertSessionHasErrors('confirm_name');
        $this->delete($url, ['confirm_name' => $victim->name, 'password' => 'xato-parol'])->assertSessionHasErrors('password');
        $this->assertSame(3, Branch::withoutGlobalScopes()->where('center_id', $victim->id)->count());

        $this->delete($url, ['confirm_name' => $victim->name, 'password' => 'parol12345'])->assertRedirect(route('platform.centers.index'));

        $this->assertSame(0, Branch::withoutGlobalScopes()->where('center_id', $victim->id)->count());
        $this->assertSame(0, User::withoutGlobalScopes()->where('center_id', $victim->id)->count());
        $this->assertSame(CenterStatus::Deleted, $victim->fresh()->status);
        $this->assertSame(1, Branch::withoutGlobalScopes()->where('center_id', $other->id)->count());   // boshqa markazga tegmadi
        $this->assertSame(1, UserConsent::where('user_id', $staff->id)->count());                       // rozilik jurnali qoldi
        $this->get(route('platform.centers.show', $victim))->assertNotFound();

        // nom bo'shadi: xuddi shu nom bilan yangi markaz ochish mumkin
        $this->post(route('platform.centers.store'), ['name' => 'Ochiriladigan markaz', 'branch_name' => 'Asosiy', 'owner_name' => 'Ali', 'owner_phone' => '+998 90 765 4321'])->assertSessionHasNoErrors();
    }

    public function test_demo_center_cannot_be_deleted(): void
    {
        $demo = $this->newCenter('Demo markaz');
        $demo->forceFill(['is_demo' => true])->save();
        $this->actingAs($this->user(Role::SAdmin))->delete(route('platform.centers.destroy', $demo), ['confirm_name' => $demo->name, 'password' => 'parol12345'])->assertSessionHas('error');
        $this->assertSame(CenterStatus::Active, $demo->fresh()->status);
    }
}
