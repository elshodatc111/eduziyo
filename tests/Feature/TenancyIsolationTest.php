<?php

namespace Tests\Feature;

use App\Enums\CenterStatus;
use App\Enums\Role;
use App\Models\AiChat;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Center;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Room;
use App\Models\User;
use App\Support\CenterContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** EDUZIYO E0: markazlar o'rtasidagi izolyatsiya (eng muhim xavfsizlik talabi, TZ 5 va 23-bo'lim). */
class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Center $a;

    private Center $b;

    private Branch $ba;

    private Branch $bb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = $this->newCenter('Markaz A');
        $this->b = $this->newCenter('Markaz B');
        $this->ba = $this->branch('Filial A', $this->a);
        $this->bb = $this->branch('Filial B', $this->b);
    }

    private function group(Branch $branch, string $name): Group
    {
        $c = $this->catalog($branch);

        return Group::create([
            'branch_id' => $branch->id, 'course_id' => $c['course']->id, 'room_id' => $c['room']->id, 'lesson_time_id' => $c['time']->id,
            'teacher_id' => $c['teacher']->id, 'name' => $name, 'schedule' => 'odd', 'price' => 500000, 'lesson_count' => 4,
            'starts_on' => '2026-11-02', 'ends_on' => '2026-11-30',
        ]);
    }

    public function test_new_rows_inherit_center_from_branch(): void
    {
        $g = $this->group($this->ba, 'a1');
        $this->assertSame($this->a->id, $g->center_id);

        $lead = Lead::create(['branch_id' => $this->bb->id, 'name' => 'L', 'phone' => '+998 90 111 2233', 'status' => 'new']);
        $this->assertSame($this->b->id, $lead->center_id);

        $student = $this->student($this->ba);
        $this->assertSame($this->a->id, $student->center_id);
    }

    public function test_staff_cannot_open_other_centers_records_by_id(): void
    {
        $admin = $this->user(Role::Admin, $this->ba, ['students.view', 'groups.view', 'leads.view', 'staff.view']);
        $foreignStudent = $this->student($this->bb);
        $foreignGroup = $this->group($this->bb, 'b-guruh');
        $foreignLead = Lead::create(['branch_id' => $this->bb->id, 'name' => 'Begona', 'phone' => '+998 90 222 3344', 'status' => 'new']);

        $this->actingAs($admin);
        $this->get("/students/{$foreignStudent->id}")->assertNotFound();
        $this->get("/groups/{$foreignGroup->id}")->assertNotFound();
        $this->get("/leads/{$foreignLead->id}")->assertNotFound();
    }

    public function test_owner_sees_all_own_branches_but_nothing_from_other_center(): void
    {
        $ba2 = $this->branch('Filial A2', $this->a);
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->a->id]);
        $s1 = $this->student($this->ba, ['name' => 'Ali A1']);
        $s2 = $this->student($ba2, ['name' => 'Vali A2']);
        $foreign = $this->student($this->bb, ['name' => 'Begona B']);

        $this->actingAs($owner);
        $this->assertSame(2, User::visibleToContext()->ofRole(Role::Student)->count());

        $this->get('/students')->assertOk()->assertSee('Ali A1')->assertSee('Vali A2')->assertDontSee('Begona B');
        $this->get("/students/{$s2->id}")->assertOk();
        $this->get("/students/{$foreign->id}")->assertNotFound();
    }

    public function test_owner_branch_switcher_lists_and_accepts_only_own_center_branches(): void
    {
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->a->id]);

        $this->actingAs($owner)->get('/')->assertOk()->assertSee('Filial A')->assertDontSee('Filial B');

        $this->actingAs($owner)->post('/branch/switch', ['branch_id' => $this->bb->id])->assertSessionHasErrors('branch_id');
        $this->assertNull(session('current_branch_id'));

        $this->actingAs($owner)->post('/branch/switch', ['branch_id' => $this->ba->id])->assertSessionHasNoErrors();
        $this->assertSame($this->ba->id, session('current_branch_id'));
    }

    public function test_foreign_branch_id_in_session_is_ignored_for_owner(): void
    {
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->a->id]);
        $this->student($this->ba, ['name' => 'Ali A1']);
        $this->student($this->bb, ['name' => 'Begona B']);

        // Sessiyaga boshqa markaz filiali qo'lda joylashtirilsa ham, u hisobga olinmaydi
        $this->actingAs($owner)->withSession(['current_branch_id' => $this->bb->id])->get('/students')
            ->assertOk()->assertSee('Ali A1')->assertDontSee('Begona B');
    }

    public function test_owner_creates_staff_only_in_own_center_branches(): void
    {
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->a->id]);
        $base = ['name' => 'Yangi Menejer', 'username' => 'menejer.a', 'phone' => '+998 90 333 4455', 'status' => 'active', 'role' => 'manager', 'password' => 'parol12345', 'password_confirmation' => 'parol12345'];

        $this->actingAs($owner)->post('/staff', $base + ['branch_id' => $this->bb->id])->assertSessionHasErrors('branch_id');
        $this->assertSame(0, User::where('username', 'menejer.a')->count());

        $this->actingAs($owner)->post('/staff', $base + ['branch_id' => $this->ba->id])->assertSessionHasNoErrors();
        $created = User::where('username', 'menejer.a')->firstOrFail();
        $this->assertSame($this->a->id, $created->center_id);
        $this->assertSame($this->ba->id, $created->branch_id);
    }

    public function test_owner_cannot_manage_foreign_or_platform_users(): void
    {
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->a->id]);
        $foreignAdmin = $this->user(Role::Admin, $this->bb, ['staff.view']);
        $sadmin = $this->user(Role::SAdmin);
        $ownAdmin = $this->user(Role::Admin, $this->ba, ['staff.view']);

        $this->actingAs($owner);
        $this->get("/staff/{$foreignAdmin->id}/edit")->assertNotFound();
        $this->get("/staff/{$sadmin->id}/edit")->assertNotFound();      // sAdmin markazsiz - markaz foydalanuvchisiga ko'rinmaydi
        $this->get("/staff/{$ownAdmin->id}/edit")->assertOk();
        $this->assertTrue($owner->can('manage', $ownAdmin));
        $this->assertFalse($owner->can('manage', $foreignAdmin));
        $this->assertFalse($owner->can('manage', $sadmin));
    }

    public function test_owner_cannot_use_platform_pages(): void
    {
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $this->a->id]);

        foreach (['/branches', '/superadmins', '/system-status'] as $url) {
            $this->actingAs($owner)->get($url)->assertForbidden();
        }
        $this->actingAs($owner)->post('/center/switch', ['center_id' => $this->b->id])->assertForbidden();
    }

    public function test_sadmin_sees_everything_or_selected_center(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->student($this->ba, ['name' => 'Ali A1']);
        $this->student($this->bb, ['name' => 'Begona B']);

        $this->actingAs($sadmin)->get('/students')->assertOk()->assertSee('Ali A1')->assertSee('Begona B');

        $this->actingAs($sadmin)->post('/center/switch', ['center_id' => $this->a->id])->assertRedirect();
        $this->assertSame($this->a->id, CenterContext::id());
        $this->get('/students')->assertOk()->assertSee('Ali A1')->assertDontSee('Begona B');
    }

    public function test_user_without_center_sees_nothing(): void
    {
        $this->student($this->ba, ['name' => 'Ali A1']);
        $orphan = $this->user(Role::Admin, $this->ba, ['students.view']);
        $orphan->forceFill(['center_id' => null])->save();

        $this->actingAs($orphan);
        $this->assertSame(0, CenterContext::id());
        $this->assertSame(0, User::visibleToContext()->count());
        $this->assertSame(0, Branch::count());
    }

    public function test_rows_with_null_center_are_hidden_from_center_users(): void
    {
        $admin = $this->user(Role::Admin, $this->ba, ['students.view']);
        DB::table('rooms')->insert(['branch_id' => $this->ba->id, 'center_id' => null, 'name' => 'Yetim xona', 'created_at' => now(), 'updated_at' => now()]);
        Room::withoutGlobalScopes()->where('name', 'Yetim xona')->firstOrFail();

        $this->actingAs($admin);
        $this->assertSame(0, Room::where('name', 'Yetim xona')->count());   // xato bo'lsa ma'lumot "yo'qoladi", boshqaga oqib o'tmaydi
    }

    public function test_creating_row_without_any_center_context_fails_loudly(): void
    {
        Auth::logout();

        $this->expectException(\LogicException::class);
        AiChat::create(['user_id' => 1, 'title' => 'x']);
    }

    public function test_branch_names_unique_per_center_and_codes_globally(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        // Boshqa markazda bir xil nomli filial ochish mumkin
        $this->actingAs($sadmin)->post('/branches', ['center_id' => $this->b->id, 'name' => 'Filial A'])->assertSessionHasNoErrors();
        // Xuddi shu markazda - yo'q
        $this->actingAs($sadmin)->post('/branches', ['center_id' => $this->a->id, 'name' => 'Filial A'])->assertSessionHasErrors('name');

        $codes = Branch::withoutGlobalScopes()->where('name', 'Filial A')->pluck('code');
        $this->assertCount(2, $codes);
        $this->assertCount(2, $codes->unique());   // /apply/{kod} havolasi global: kodlar to'qnashmaydi
    }

    public function test_login_blocked_when_center_is_suspended_or_deleted(): void
    {
        $admin = $this->user(Role::Admin, $this->ba, [], ['username' => 'admin.a', 'password' => 'parol12345']);

        $this->post('/login', ['login' => 'admin.a', 'password' => 'parol12345'])->assertSessionHasNoErrors();
        auth()->logout();

        $this->a->update(['status' => CenterStatus::Suspended]);
        $this->post('/login', ['login' => 'admin.a', 'password' => 'parol12345'])->assertSessionHasErrors('login');

        $this->a->update(['status' => CenterStatus::Deleted]);
        $this->post('/login', ['login' => 'admin.a', 'password' => 'parol12345'])->assertSessionHasErrors('login');
    }

    public function test_active_session_is_cut_when_center_gets_suspended(): void
    {
        $admin = $this->user(Role::Admin, $this->ba, ['students.view']);
        $this->actingAs($admin)->get('/students')->assertOk();

        $this->a->update(['status' => CenterStatus::Suspended]);
        $this->actingAs($admin->fresh())->get('/students')->assertRedirect(route('login'));
    }

    public function test_group_lesson_days_get_center_id(): void
    {
        $admin = $this->user(Role::Admin, $this->ba, ['groups.create', 'groups.view']);
        $c = $this->catalog($this->ba);

        $this->actingAs($admin)->post('/groups', $this->groupPayload($c))->assertSessionHasNoErrors();

        $this->assertGreaterThan(0, DB::table('group_days')->count());
        $this->assertSame(0, DB::table('group_days')->whereNull('center_id')->count());
        $this->assertSame([$this->a->id], DB::table('group_days')->pluck('center_id')->unique()->values()->all());
    }

    public function test_audit_log_is_center_scoped_and_platform_actions_are_markazsiz(): void
    {
        $adminA = $this->user(Role::Admin, $this->ba, ['audit.view']);
        $this->actingAs($adminA);
        AuditLog::record('test.a', null, 'A markaz harakati');
        $this->assertSame($this->a->id, AuditLog::where('action', 'test.a')->firstOrFail()->center_id);

        Auth::logout();
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin);
        AuditLog::record('test.platform', null, 'Platforma harakati');
        $row = AuditLog::withoutGlobalScopes()->where('action', 'test.platform')->firstOrFail();
        $this->assertNull($row->center_id);

        $this->actingAs($adminA->fresh());
        $this->assertSame(0, AuditLog::where('action', 'test.platform')->count());
    }

    public function test_tenancy_audit_command_detects_orphan_rows(): void
    {
        $this->artisan('tenancy:audit')->assertSuccessful();

        DB::table('rooms')->insert(['branch_id' => $this->ba->id, 'center_id' => null, 'name' => 'Yetim', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('tenancy:audit')->assertFailed();
    }

    public function test_tenancy_audit_command_detects_center_branch_mismatch(): void
    {
        DB::table('rooms')->insert(['branch_id' => $this->ba->id, 'center_id' => $this->b->id, 'name' => 'Noto\'g\'ri markaz', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('tenancy:audit')->assertFailed();
    }
}
