<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CourseQuestion;
use App\Models\CourseVideo;
use App\Models\Group;
use App\Models\TestAttempt;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** E6: o'quvchi veb-kabineti. */
class CabinetTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;
    private array $cat;
    private Group $group;
    private $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');

        $this->branch = $this->branch();
        $this->admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members']);
        $this->actingAs($this->admin);
        $this->cat = $this->catalog($this->branch);
        $this->group = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'KABINET-GURUHI']), $this->admin);
        $this->student = $this->student($this->branch, ['name' => 'Nodira Karimova', 'username' => 'nodira', 'password' => 'parol12345']);
        app(EnrollmentService::class)->enroll($this->group, $this->student, null, $this->admin);

        $c = $this->cat['course']->id;
        CourseVideo::create(['branch_id' => $this->branch->id, 'course_id' => $c, 'number' => 1, 'title' => 'Birinchi video', 'url' => 'https://youtu.be/abc']);
        for ($i = 1; $i <= 20; $i++) {
            CourseQuestion::create(['branch_id' => $this->branch->id, 'course_id' => $c, 'question' => "Savol {$i}", 'correct' => "to'g'ri {$i}", 'wrong' => ["x{$i}", "y{$i}", "z{$i}"]]);
        }

        auth()->logout();
        $this->app['auth']->forgetGuards();
    }

    public function test_student_can_login_on_web_and_lands_in_cabinet(): void
    {
        $this->post('/login', ['login' => 'nodira', 'password' => 'parol12345'])->assertRedirect(route('cabinet.home'));
        $this->get('/cabinet')->assertOk()->assertSee('Nodira Karimova')->assertSee('KABINET-GURUHI')->assertSee('Balans');
    }

    public function test_student_is_kept_inside_cabinet(): void
    {
        $this->actingAs($this->student);

        $this->get('/dashboard')->assertRedirect(route('cabinet.home'));
        $this->get('/students')->assertRedirect(route('cabinet.home'));
        $this->get('/groups')->assertRedirect(route('cabinet.home'));
        $this->get('/profile')->assertRedirect(route('cabinet.home'));
    }

    public function test_staff_cannot_open_cabinet(): void
    {
        $this->actingAs($this->admin);
        $this->get('/cabinet')->assertForbidden();
        $this->actingAs($this->user(Role::Teacher, $this->branch));
        $this->get('/cabinet/guruhlar')->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/cabinet')->assertRedirect(route('login'));
    }

    public function test_groups_group_page_and_payments(): void
    {
        $this->actingAs($this->student);

        $this->get('/cabinet/guruhlar')->assertOk()->assertSee('KABINET-GURUHI');
        $this->get("/cabinet/guruhlar/{$this->group->id}")->assertOk()->assertSee('Davomatim')->assertSee('1-xona');
        $this->get('/cabinet/tolovlar')->assertOk()->assertSee('Joriy balans')->assertSee('Guruh narxi');
    }

    public function test_student_cannot_open_foreign_group_or_course(): void
    {
        $otherBranch = $this->branch('Boshqa');
        $otherCat = $this->catalog($otherBranch);
        $otherAdmin = $this->user(Role::Admin, $otherBranch, ['groups.create']);
        $this->actingAs($otherAdmin);
        $foreign = app(GroupService::class)->create($this->groupPayload($otherCat, ['name' => 'BEGONA']), $otherAdmin);

        $this->actingAs($this->student);
        $this->get("/cabinet/guruhlar/{$foreign->id}")->assertNotFound();
        $this->get("/cabinet/kurslar/{$otherCat['course']->id}")->assertNotFound();

        // Boshqa markaz o'quvchisi ham ko'rmaydi
        $c2 = $this->newCenter('Boshqa markaz');
        $b2 = $this->branch('B2', $c2);
        $other = $this->student($b2);
        $this->actingAs($other);
        $this->get("/cabinet/guruhlar/{$this->group->id}")->assertNotFound();
        $this->get('/cabinet/guruhlar')->assertOk()->assertDontSee('KABINET-GURUHI');
    }

    public function test_course_page_and_full_test_flow_hides_answer_key(): void
    {
        $this->actingAs($this->student);
        $course = $this->cat['course'];

        $this->get('/cabinet/kurslar')->assertOk()->assertSee('Koreys tili');
        $this->get("/cabinet/kurslar/{$course->id}")->assertOk()->assertSee('Birinchi video')->assertSee('Testni boshlash');

        $resp = $this->post("/cabinet/kurslar/{$course->id}/test");
        $attempt = TestAttempt::where('student_id', $this->student->id)->firstOrFail();
        $resp->assertRedirect(route('cabinet.test', $attempt->id));

        $page = $this->get("/cabinet/test/{$attempt->id}")->assertOk();
        $page->assertSee('Yakunlash');
        $this->assertStringNotContainsString('answer_key', $page->getContent());

        // Hammasini to'g'ri belgilash (kalit serverda)
        $answers = [];
        foreach ($attempt->questions as $i => $q) {
            $answers[$q['id']] = $attempt->answer_key[$i];
        }
        $this->post("/cabinet/test/{$attempt->id}", ['answers' => $answers])->assertOk()->assertSee('100%');

        // Qayta topshirib bo'lmaydi, boshqa o'quvchi ochib ham bo'lmaydi
        $this->post("/cabinet/test/{$attempt->id}", ['answers' => $answers])->assertSessionHasErrors('attempt');
        $this->actingAs($this->student($this->branch));
        $this->get("/cabinet/test/{$attempt->id}")->assertNotFound();
    }

    public function test_notifications_are_marked_read(): void
    {
        $this->actingAs($this->admin);
        [, $count] = app(\App\Services\NotificationService::class)->send('Salom', 'Ertaga dars bor', $this->branch->id, $this->admin);
        $this->assertGreaterThan(0, $count);

        $this->actingAs($this->student);
        $this->get('/cabinet/xabarlar')->assertOk()->assertSee('Ertaga dars bor');
        $this->assertSame(0, $this->student->notificationRecipients()->whereNull('read_at')->count());
    }

    public function test_profile_phone_and_password_change(): void
    {
        $this->actingAs($this->student);

        $this->put('/cabinet/profil', ['phone' => '+998 90 111 2233'])->assertSessionHasNoErrors();
        $this->assertSame('+998 90 111 2233', $this->student->fresh()->phone);

        $this->put('/cabinet/profil/parol', ['current_password' => 'xato', 'password' => 'yangi-parol-1', 'password_confirmation' => 'yangi-parol-1'])->assertSessionHasErrors('current_password');
        $this->put('/cabinet/profil/parol', ['current_password' => 'parol12345', 'password' => 'yangi-parol-1', 'password_confirmation' => 'yangi-parol-1'])->assertSessionHasNoErrors();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('yangi-parol-1', $this->student->fresh()->password));
    }

    public function test_pwa_files_are_served(): void
    {
        $manifest = $this->getJson('/manifest.webmanifest')->assertOk()->json();
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->get('/login')->assertOk()->assertSee('manifest.webmanifest', false);
    }
}
