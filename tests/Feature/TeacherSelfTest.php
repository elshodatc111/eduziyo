<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TeacherSelfTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_sees_own_schedule_and_payroll_only(): void
    {
        Carbon::setTestNow('2026-09-14 09:00:00');
        $branch = $this->branch();
        $c = $this->catalog($branch);
        $admin = $this->user(Role::Admin, $branch, ['groups.view', 'groups.create']);
        $this->actingAs($admin);
        $mine = app(GroupService::class)->create($this->groupPayload($c, ['name' => 'mening guruhim', 'starts_on' => '2026-09-14']), $admin);

        $other = $this->user(Role::Teacher, $branch);
        $room2 = \App\Models\Room::create(['branch_id' => $branch->id, 'name' => '2-xona']);
        $theirs = app(GroupService::class)->create($this->groupPayload($c, ['name' => 'begona guruh', 'teacher_id' => $other->id, 'room_id' => $room2->id, 'starts_on' => '2026-09-15']), $admin);

        $this->actingAs($c['teacher'])->get(route('teacher.schedule'))->assertOk()->assertSee('MENING GURUHIM', false)->assertDontSee('BEGONA GURUH', false);
        $this->get(route('teacher.payroll'))->assertOk()->assertSee('Mening ish haqim', false)->assertDontSee('BEGONA GURUH', false);
        $this->get('/dashboard')->assertOk()->assertSee('Mening jadvalim');

        // boshqa rollar kira olmaydi
        $this->actingAs($admin)->get(route('teacher.payroll'))->assertForbidden();
        $this->get(route('teacher.schedule'))->assertForbidden();
    }

    public function test_system_status_shows_telegram_backup_result(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->get('/system-status')->assertOk()->assertSee('Zaxira (Telegram)')->assertSee('Hali yuborilmagan');

        Cache::put('system.telegram_backup', ['ok' => false, 'at' => now()->timestamp, 'message' => 'Bot sozlanmagan'], now()->addDay());
        $this->get('/system-status')->assertSee('Bot sozlanmagan');

        Cache::put('system.telegram_backup', ['ok' => true, 'at' => now()->timestamp, 'message' => 'ok'], now()->addDay());
        $this->get('/system-status')->assertSee('Oxirgi muvaffaqiyatli yuborilgan');
    }
}
