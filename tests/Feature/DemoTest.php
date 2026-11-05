<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Center;
use App\Models\DemoVisit;
use App\Models\PlatformSetting;
use App\Models\SalesApplication;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\DemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** E7d: ommaviy demo - rol bo'yicha bir bosishda kirish, himoya va har tunlik tozalash. */
class DemoTest extends TestCase
{
    use RefreshDatabase;

    private function resetDemo(): array
    {
        return app(DemoService::class)->reset();
    }

    public function test_reset_creates_demo_center_with_one_user_per_role(): void
    {
        $this->resetDemo();
        $demo = app(DemoService::class);

        $this->assertTrue($demo->available());
        $center = $demo->center();
        $this->assertTrue($center->is_demo);

        foreach (DemoService::ROLES as $key => $_) {
            $u = $demo->userFor($key);
            $this->assertNotNull($u, "{$key} foydalanuvchisi yo'q");
            $this->assertSame($center->id, $u->center_id);
        }

        $this->assertSame(Role::Student, $demo->userFor('student')->role);
        $this->assertGreaterThan(0, $demo->userFor('student')->memberships()->where('is_active', true)->count());
        $this->assertSame(2, $center->branches()->count());
    }

    public function test_reset_is_repeatable_and_does_not_touch_real_centers(): void
    {
        // Haqiqiy markaz va uning ma'lumoti
        $real = $this->center('Haqiqiy markaz');
        $branch = $this->branch('Haqiqiy filial', $real);
        $student = $this->student($branch, ['name' => 'Haqiqiy Oquvchi']);
        $before = [User::where('center_id', $real->id)->count(), \App\Models\Branch::where('center_id', $real->id)->count()];

        $this->resetDemo();
        $firstIds = User::where('center_id', app(DemoService::class)->center()->id)->pluck('id')->all();
        $this->resetDemo();   // ikkinchi marta: xato bermaydi, eski ma'lumot almashadi

        $demoCenter = app(DemoService::class)->center();
        $this->assertSame([], array_intersect($firstIds, User::where('center_id', $demoCenter->id)->pluck('id')->all()));
        $this->assertSame($before, [User::where('center_id', $real->id)->count(), \App\Models\Branch::where('center_id', $real->id)->count()]);
        $this->assertSame('Haqiqiy Oquvchi', $student->fresh()->name);
        $this->assertSame(1, Center::where('code', 'demo')->count());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->resetDemo();
        $count = User::count();

        $report = app(DemoService::class)->reset(true);

        $this->assertGreaterThan(0, $report['users'] ?? 0);
        $this->assertSame($count, User::count());
    }

    public function test_reset_refuses_when_demo_code_belongs_to_non_demo_center(): void
    {
        Center::create(['name' => 'Soxta', 'code' => 'demo', 'status' => 'active', 'is_demo' => false]);

        $this->expectException(\LogicException::class);
        app(DemoService::class)->reset();
    }

    public function test_one_click_login_for_every_role(): void
    {
        $this->resetDemo();

        foreach (['owner', 'admin', 'manager', 'operator', 'teacher'] as $role) {
            $resp = $this->post("/demo/{$role}");
            $resp->assertRedirect(route('dashboard'));
            $this->assertSame(Role::from($role === 'owner' ? 'owner' : $role), auth()->user()->role);
            $this->get('/dashboard')->assertOk()->assertSee('Demo rejim');
        }

        $this->post('/demo/student')->assertRedirect(route('cabinet.home'));
        $this->get('/cabinet')->assertOk()->assertSee('Demo rejim')->assertSee('Balans');

        $this->assertSame(6, DemoVisit::count());
    }

    public function test_unknown_role_is_404_and_disabled_demo_redirects(): void
    {
        $this->resetDemo();
        $this->post('/demo/sadmin')->assertNotFound();

        PlatformSetting::put('demo_enabled', '0');
        $this->post('/demo/admin')->assertRedirect(route('landing'));
        $this->assertGuest();
    }

    public function test_landing_shows_role_buttons_only_when_ready(): void
    {
        $this->get('/')->assertOk()->assertDontSee('sifatida kirish')->assertSee("Demo so'rang", false);

        $this->resetDemo();
        $this->get('/')->assertOk()->assertSee('Direktor sifatida kirish')->assertSee('O&#039;quvchi sifatida kirish', false)->assertDontSee('Demo hisobi');
    }

    public function test_dangerous_actions_are_blocked_for_demo_users_only(): void
    {
        $this->resetDemo();
        $this->post('/demo/owner');

        $before = auth()->user()->password;
        $this->put('/profile/password', ['current_password' => 'x', 'password' => 'yangi-parol-1', 'password_confirmation' => 'yangi-parol-1'])
            ->assertSessionHas('error');
        $this->assertSame($before, auth()->user()->fresh()->password);
        $this->post('/sms/bulk', [])->assertSessionHas('error');
        $this->post('/ai', ['message' => 'salom'])->assertSessionHas('error');

        // Oddiy markaz foydalanuvchisiga ta'sir qilmaydi
        $this->post('/demo/chiqish');
        $admin = $this->user(Role::Admin, $this->branch('X'));
        $this->actingAs($admin)->put('/profile/password', ['current_password' => 'parol12345', 'password' => 'yangi-parol-1', 'password_confirmation' => 'yangi-parol-1'])
            ->assertSessionMissing('error');
    }

    public function test_demo_session_expires_after_30_minutes(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->resetDemo();
        $this->post('/demo/admin');
        $this->get('/dashboard')->assertOk();

        Carbon::setTestNow('2026-11-10 10:31:00');
        $this->get('/dashboard')->assertRedirect(route('landing'));
        $this->assertGuest();
    }

    public function test_leave_logs_out_and_application_remembers_seen_roles(): void
    {
        $this->resetDemo();
        $this->post('/demo/manager');
        $this->post('/demo/teacher');
        $this->post('/demo/chiqish')->assertRedirect(route('landing').'#ariza');
        $this->assertGuest();

        $this->post('/ariza', ['name' => 'Ali', 'center_name' => 'Markaz', 'phone' => '+998 90 123 4567']);
        $app = SalesApplication::firstOrFail();
        $this->assertSame('manager,teacher', $app->demo_roles);
        $this->assertSame(['Menejer', "O'qituvchi"], $app->demoRoleLabels());
    }

    public function test_demo_sms_is_never_sent_for_real(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $this->resetDemo();
        $branch = \App\Models\Branch::where('code', 'demo-toshkent')->firstOrFail();
        $student = User::where('center_id', $branch->center_id)->where('role', Role::Student)->firstOrFail();

        $sms = app(\App\Services\SmsService::class)->queue($branch, $student->phone, 'Test xabar');
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertSame(SmsMessage::SENT, $sms->fresh()->status);
        $this->assertStringContainsString('demo', $sms->fresh()->provider_response);
    }

    public function test_sadmin_sees_demo_stats_and_can_reset(): void
    {
        $this->resetDemo();
        $this->post('/demo/admin');
        $this->post('/demo/chiqish');

        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->get('/sales/demo')->assertOk()->assertSee('Demo kirishlar')->assertSee('Admin');
        $this->post('/sales/demo/reset')->assertSessionHas('success');
        $this->assertAuthenticatedAs($sadmin);   // tiklash sAdmin sessiyasini buzmaydi
        $this->assertTrue(app(DemoService::class)->available());

        $this->actingAs($this->user(Role::Admin, $this->branch('Y')))->get('/sales/demo')->assertForbidden();
    }

    public function test_scheduler_has_nightly_reset(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains($e->command ?? '', 'demo:reset'));
        $this->assertCount(1, $events);
        $this->assertSame('10 3 * * *', $events->first()->expression);
    }
}
