<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\TelegramGuardian;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\PaymentService;
use App\Services\Telegram\TelegramBot;
use App\Support\IntegrationSettings as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Ota-ona va o'quvchi Telegram boti: ulanish, jadval, guruh, qarz, davomat va avtomatik xabarlar. */
class TelegramFamilyTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;
    private $student;
    private $group;
    private array $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');   // dushanba
        Cache::flush();
        S::set('telegram_token', '123456789:AAFakeTokenValue_1234567890abcdef');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

        $this->branch = $this->branch('Toshkent');
        $this->admin = $this->user(Role::Admin, $this->branch, array_keys(array_merge(...array_values(array_map(fn ($g) => $g['items'], config('permissions.groups'))))));
        $this->actingAs($this->admin);
        $this->cat = $this->catalog($this->branch);
        $this->cat['teacher']->update(['name' => 'Kim ustoz']);
        $this->group = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'TOPIK-4', 'starts_on' => '2026-09-21', 'lesson_count' => 12]), $this->admin);
        $this->student = $this->student($this->branch, ['name' => 'Ali Valiyev', 'phone' => '+998 90 111 2233', 'phone2' => '+998 91 555 6677']);
        app(EnrollmentService::class)->enroll($this->group, $this->student, null, $this->admin);
    }

    private function say(int $chat, string $text): void
    {
        app(TelegramBot::class)->handle(['message' => ['chat' => ['id' => $chat, 'type' => 'private'], 'from' => ['id' => $chat, 'username' => 'ota'], 'text' => $text]]);
    }

    private function share(int $chat, string $phone, ?int $owner = null): void
    {
        app(TelegramBot::class)->handle(['message' => ['chat' => ['id' => $chat, 'type' => 'private'], 'from' => ['id' => $chat, 'username' => 'ota'],
            'contact' => ['phone_number' => ltrim($phone, '+'), 'user_id' => $owner ?? $chat]]]);
    }

    private function sentTo(int $chat): string
    {
        return Http::recorded()->filter(fn ($p) => str_contains($p[0]->url(), 'sendMessage') && (int) $p[0]['chat_id'] === $chat)->map(fn ($p) => (string) $p[0]['text'])->implode("\n---\n");
    }

    public function test_parent_links_by_phone2_and_sees_group_schedule_balance_attendance(): void
    {
        $this->say(5001, '/ulanish');
        $this->share(5001, '+998 91 555 6677');
        $g = TelegramGuardian::withoutGlobalScopes()->where('chat_id', 5001)->first();
        $this->assertSame('parent', $g->kind);
        $this->assertSame($this->student->id, $g->student_id);
        $this->assertStringContainsString('Ali Valiyev', $this->sentTo(5001));

        $this->say(5001, '👥 Guruhlar');
        $t = $this->sentTo(5001);
        $this->assertStringContainsString('TOPIK-4', $t);
        $this->assertStringContainsString('Kim ustoz', $t);
        $this->assertStringContainsString('0/12', $t);

        $this->say(5001, '/jadval');
        $this->assertStringContainsString('21.09', $this->sentTo(5001));

        $this->student->update(['balance' => -150000]);
        $this->say(5001, '/qarz');
        $this->assertStringContainsString('150 000', $this->sentTo(5001));

        $this->say(5001, '/davomat');
        $this->assertStringContainsString("yozuvi yo'q", $this->sentTo(5001));
    }

    public function test_student_links_by_own_phone_and_stranger_cannot(): void
    {
        $this->say(5002, '/ulanish');
        $this->share(5002, '+998 90 111 2233');
        $this->assertSame('student', TelegramGuardian::withoutGlobalScopes()->where('chat_id', 5002)->value('kind'));

        $this->say(5003, '/ulanish');
        $this->share(5003, '+998 99 000 0000');
        $this->assertSame(0, TelegramGuardian::withoutGlobalScopes()->where('chat_id', 5003)->count());
        $this->assertStringContainsString('topilmadi', $this->sentTo(5003));

        // boshqaning kontaktini yuborish rad etiladi
        $this->say(5004, '/ulanish');
        $this->share(5004, '+998 90 111 2233', owner: 999);
        $this->assertSame(0, TelegramGuardian::withoutGlobalScopes()->where('chat_id', 5004)->count());
    }

    public function test_never_shows_classmates_or_other_students(): void
    {
        $mate = $this->student($this->branch, ['name' => 'Guruhdosh Gulnora', 'phone' => '+998 90 777 8899', 'phone2' => '+998 93 000 1122']);
        app(EnrollmentService::class)->enroll($this->group, $mate, null, $this->admin);

        $this->say(5001, '/ulanish');
        $this->share(5001, '+998 91 555 6677');
        foreach (['/guruhlar', '/jadval', '/qarz', '/davomat'] as $c) {
            $this->say(5001, $c);
        }
        $this->assertStringNotContainsString('Gulnora', $this->sentTo(5001));
    }

    public function test_siblings_are_shown_together_and_unlink_works(): void
    {
        $sibling = $this->student($this->branch, ['name' => 'Vali Valiyev', 'phone' => '+998 90 222 3344', 'phone2' => '+998 91 555 6677']);
        $this->say(5001, '/ulanish');
        $this->share(5001, '+998 91 555 6677');
        $this->assertSame(2, TelegramGuardian::withoutGlobalScopes()->where('chat_id', 5001)->count());

        $this->say(5001, '/qarz');
        $t = $this->sentTo(5001);
        $this->assertStringContainsString('Ali Valiyev', $t);
        $this->assertStringContainsString('Vali Valiyev', $t);

        $this->say(5001, '/chiqish');
        $this->assertSame(0, TelegramGuardian::withoutGlobalScopes()->where('chat_id', 5001)->count());
    }

    public function test_absence_payment_and_debt_notifications_respect_settings_and_throttle(): void
    {
        $this->say(5001, '/ulanish');
        $this->share(5001, '+998 91 555 6677');
        $this->say(5002, '/ulanish');
        $this->share(5002, '+998 90 111 2233');

        // davomat: kelmadi -> ota-ona va o'quvchining o'ziga xabar (SMS sozlamasidan mustaqil)
        app(AttendanceService::class)->takeToday($this->group, [], $this->admin);
        $this->assertStringContainsString('kelmadi', $this->sentTo(5001));
        $this->assertStringContainsString('kelmadi', $this->sentTo(5002));

        $before = substr_count($this->sentTo(5001), 'kelmadi');
        app(AttendanceService::class)->takeToday($this->group, [], $this->admin);   // qayta saqlash: kuniga bir marta
        $this->assertSame($before, substr_count($this->sentTo(5001), 'kelmadi'));

        // to'lov
        $this->student->update(['balance' => 0]);
        app(PaymentService::class)->receive($this->student, ['cash' => 200000], $this->group, null, $this->admin);
        $this->assertStringContainsString('200 000', $this->sentTo(5001));

        // o'chirib qo'yish
        $this->say(5001, '/bildirishnoma tolov off');
        app(PaymentService::class)->receive($this->student->fresh(), ['cash' => 100000], $this->group, null, $this->admin);
        $this->assertStringNotContainsString('100 000', $this->sentTo(5001));

        // qarz: 3 kunda bir marta
        $this->student->update(['balance' => -90000]);
        $this->artisan('telegram:debts')->assertSuccessful();
        $this->assertStringContainsString('90 000', $this->sentTo(5001));
        $n = substr_count($this->sentTo(5001), '90 000');
        $this->artisan('telegram:debts')->assertSuccessful();
        $this->assertSame($n, substr_count($this->sentTo(5001), '90 000'));
    }

    public function test_other_center_students_are_not_linked_by_same_phone_without_match_and_ids_stay_in_own_center(): void
    {
        $b = $this->newCenter('B markaz');
        $branchB = $this->branch('B filial', $b);
        $other = $this->student($branchB, ['name' => 'Boshqa markaz talabasi', 'phone2' => '+998 94 123 4567']);

        $this->say(5009, '/ulanish');
        $this->share(5009, '+998 94 123 4567');
        $g = TelegramGuardian::withoutGlobalScopes()->where('chat_id', 5009)->first();
        $this->assertSame($b->id, $g->center_id);          // yozuv o'z markaziga yoziladi
        $this->say(5009, '/guruhlar');
        $this->assertStringNotContainsString('TOPIK-4', $this->sentTo(5009));
    }
}
