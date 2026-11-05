<?php

namespace Tests\Feature;

use App\Enums\CenterStatus;
use App\Enums\Role;
use App\Models\SupportTicket;
use App\Models\VideoGuide;
use App\Services\SupportService;
use App\Support\WorkHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportAndGuidesTest extends TestCase
{
    use RefreshDatabase;

    private $owner;

    private $sadmin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');   // payshanba
        $this->owner = $this->user(Role::Owner, null, [], ['center_id' => $this->center()->id]);
        $this->sadmin = $this->user(Role::SAdmin);
    }

    public function test_work_hours_sla_skips_nights_and_sundays(): void
    {
        $c = ['start' => '09:00', 'end' => '18:00', 'days' => [1, 2, 3, 4, 5, 6], 'sla_hours' => 4];
        $this->assertSame('2026-11-05 14:00', WorkHours::addHours(Carbon::parse('2026-11-05 10:00'), 4, $c)->format('Y-m-d H:i'));
        // payshanba 16:00 + 4 soat → 2 soat shu kun, 2 soat ertasi 11:00
        $this->assertSame('2026-11-06 11:00', WorkHours::addHours(Carbon::parse('2026-11-05 16:00'), 4, $c)->format('Y-m-d H:i'));
        // shanba 17:00 + 4 soat → yakshanba dam → dushanba 12:00
        $this->assertSame('2026-11-09 12:00', WorkHours::addHours(Carbon::parse('2026-11-07 17:00'), 4, $c)->format('Y-m-d H:i'));
        // ish vaqtidan oldin
        $this->assertSame('2026-11-05 13:00', WorkHours::addHours(Carbon::parse('2026-11-05 06:00'), 4, $c)->format('Y-m-d H:i'));
    }

    public function test_owner_opens_one_ticket_and_sadmin_replies(): void
    {
        $this->actingAs($this->owner)->post(route('center.support.store'), ['subject' => 'Hisobot ochilmayapti', 'body' => 'Tahlil sahifasi xato beryapti'])->assertSessionHasNoErrors();
        $t = SupportTicket::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('open', $t->status);
        $this->assertSame('2026-11-05 14:00', $t->sla_due_at->format('Y-m-d H:i'));

        // ikkinchi ochiq tiket mumkin emas
        $this->actingAs($this->owner)->post(route('center.support.store'), ['subject' => 'Yana', 'body' => 'Ikkinchi murojaat'])->assertSessionHasErrors('subject');
        $this->assertSame(1, SupportTicket::withoutGlobalScopes()->count());

        $this->actingAs($this->sadmin)->get(route('platform.support'))->assertOk()->assertSee('Hisobot ochilmayapti');
        $this->actingAs($this->sadmin)->post(route('platform.support.reply', $t->id), ['body' => 'Ko\'rib chiqamiz'])->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('pending_customer', $t->status);
        $this->assertNotNull($t->first_response_at);
        $this->actingAs($this->owner)->get(route('center.support'))->assertOk()->assertSee('Ko&#039;rib chiqamiz', false);

        // mijoz javobi — yana «javob kutilmoqda»
        $this->actingAs($this->owner)->post(route('center.support.reply', $t->id), ['body' => 'Rahmat, kutaman'])->assertSessionHasNoErrors();
        $this->assertSame('open', $t->fresh()->status);
    }

    public function test_close_then_rate_once_and_new_ticket_allowed(): void
    {
        $svc = app(SupportService::class);
        $t = $svc->open($this->center(), $this->owner, 'Savol', 'Matn matn matn');
        $this->actingAs($this->owner)->post(route('center.support.rate', $t->id), ['rating' => 5])->assertSessionHas('error');   // ochiq tiketga baho yo'q

        $this->actingAs($this->owner)->post(route('center.support.close', $t->id))->assertRedirect();
        $this->assertSame('closed', $t->fresh()->status);
        $this->actingAs($this->owner)->post(route('center.support.rate', $t->id), ['rating' => 4, 'comment' => 'yaxshi'])->assertSessionHas('success');
        $this->actingAs($this->owner)->post(route('center.support.rate', $t->id), ['rating' => 1])->assertSessionHas('error');
        $this->assertSame(4, $t->fresh()->rating);
        $this->actingAs($this->owner)->post(route('center.support.rate', $t->id), ['rating' => 9])->assertSessionHasErrors('rating');

        $this->assertNotNull($svc->open($this->center(), $this->owner, 'Yangi', 'Yangi savol matni'));
    }

    public function test_sla_reminder_and_auto_close_after_seven_days(): void
    {
        $svc = app(SupportService::class);
        $t = $svc->open($this->center(), $this->owner, 'Kechikkan', 'Javob kutyapman');

        $this->assertSame(0, $svc->tick()['reminded']);
        Carbon::setTestNow('2026-11-05 15:00:00');
        $this->assertSame(1, $svc->tick()['reminded']);
        $this->assertSame(0, $svc->tick()['reminded'], 'bir marta');
        Carbon::setTestNow('2026-11-05 19:30:00');
        $this->assertSame(1, $svc->tick()['reminded'], '4 soatdan keyin qayta');

        $svc->reply($t->fresh(), $this->sadmin, 'Javob');
        Carbon::setTestNow(now()->addDays(6));
        $this->assertSame(0, $svc->tick()['closed']);
        Carbon::setTestNow(now()->addDays(2));
        $this->assertSame(1, $svc->tick()['closed']);
        $t->refresh();
        $this->assertSame('closed', $t->status);
        $this->assertSame('auto', $t->closed_reason);
    }

    public function test_ticket_works_in_read_only_mode_and_is_isolated_between_centers(): void
    {
        $this->center()->update(['status' => CenterStatus::ReadOnly]);
        $this->actingAs($this->owner)->post(route('center.support.store'), ['subject' => 'Tarif tugadi', 'body' => 'Uzaytirishda yordam kerak'])->assertSessionHasNoErrors();
        $t = SupportTicket::withoutGlobalScopes()->firstOrFail();

        $other = $this->newCenter('Boshqa');
        $ownerB = $this->user(Role::Owner, null, [], ['center_id' => $other->id]);
        $this->actingAs($ownerB)->get(route('center.support.show', $t->id))->assertNotFound();
        $this->actingAs($ownerB)->post(route('center.support.reply', $t->id), ['body' => 'hack hack'])->assertNotFound();
        $this->actingAs($ownerB)->get(route('center.support'))->assertOk()->assertDontSee('Tarif tugadi');
    }

    public function test_only_owner_opens_tickets_and_only_sadmin_sees_platform_list(): void
    {
        $admin = $this->user(Role::Admin, $this->branch('F'), ['students.view']);
        $this->actingAs($admin)->post(route('center.support.store'), ['subject' => 'Savol', 'body' => 'Matn matn'])->assertForbidden();
        $this->actingAs($this->owner)->get(route('platform.support'))->assertForbidden();
    }

    public function test_ticket_text_is_deleted_with_center(): void
    {
        $c = $this->newCenter('Ochiriladigan');
        $o = $this->user(Role::Owner, null, [], ['center_id' => $c->id]);
        app(SupportService::class)->open($c, $o, 'Savol', 'Matn matn');
        $this->assertSame(1, SupportTicket::withoutGlobalScopes()->where('center_id', $c->id)->count());

        app(\App\Services\CenterWiper::class)->wipe($c);
        $this->assertSame(0, SupportTicket::withoutGlobalScopes()->where('center_id', $c->id)->count());
        $this->assertSame(0, \App\Models\SupportMessage::withoutGlobalScopes()->where('center_id', $c->id)->count());
    }

    public function test_video_guides_are_role_scoped_and_hidden_from_students(): void
    {
        $this->actingAs($this->sadmin)->post(route('platform.guides.store'), ['title' => 'Direktor uchun', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'roles' => ['owner']])->assertSessionHasNoErrors();
        $this->actingAs($this->sadmin)->post(route('platform.guides.store'), ['title' => "O'qituvchi uchun", 'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'roles' => ['teacher']])->assertSessionHasNoErrors();
        $this->actingAs($this->sadmin)->post(route('platform.guides.store'), ['title' => 'Yomon', 'url' => 'https://vimeo.com/123', 'roles' => ['owner']])->assertSessionHasErrors('url');
        $this->actingAs($this->sadmin)->post(route('platform.guides.store'), ['title' => 'Talaba', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'roles' => ['student']])->assertSessionHasErrors('roles.0');

        $ownerGuide = VideoGuide::where('title', 'Direktor uchun')->firstOrFail();
        $teacherGuide = VideoGuide::where('title', "O'qituvchi uchun")->firstOrFail();

        $this->actingAs($this->owner)->get(route('guides.index'))->assertOk()->assertSee('Direktor uchun')->assertDontSee('uchun</div>')->assertDontSee("O&#039;qituvchi uchun", false);
        $r = $this->actingAs($this->owner)->get(route('guides.show', $ownerGuide->id))->assertOk();
        $r->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('rel=0', false);
        $this->actingAs($this->owner)->get(route('guides.show', $teacherGuide->id))->assertNotFound();

        $student = $this->student($this->branch("F"));
        $this->actingAs($student)->get(route('guides.index'))->assertRedirect();   // o'quvchi panelga kira olmaydi

        $this->actingAs($this->sadmin)->post(route('platform.guides.toggle', $ownerGuide->id));
        $this->actingAs($this->owner)->get(route('guides.show', $ownerGuide->id))->assertNotFound();

        $this->actingAs($this->owner)->get(route('platform.guides'))->assertForbidden();
    }

    public function test_screenshots_attach_to_tickets_with_access_control(): void
    {
        Storage::fake('local');
        $png = fn ($n = 'ekran.png') => UploadedFile::fake()->image($n, 200, 120);

        $this->actingAs($this->owner)->post(route('center.support.store'), ['subject' => 'Xato chiqdi', 'body' => 'Skrinshot ilova qilindi', 'files' => [$png()]])->assertSessionHasNoErrors();
        $t = SupportTicket::withoutGlobalScopes()->firstOrFail();
        $a = \App\Models\SupportAttachment::withoutGlobalScopes()->firstOrFail();
        Storage::disk('local')->assertExists($a->path);
        $this->assertSame($t->center_id, $a->center_id);

        // egasi va sAdmin ko'radi, rasm ko'rinadi
        $this->actingAs($this->owner)->get(route('center.support'))->assertOk()->assertSee(route('center.support.file', $a->id), false);
        $this->get(route('center.support.file', $a->id))->assertOk();
        $this->actingAs($this->sadmin)->get(route('platform.support.file', $a->id))->assertOk();

        // sAdmin ham rasm bilan javob beradi
        $this->post(route('platform.support.reply', $t->id), ['body' => 'Rahmat, ko\'rdik', 'files' => [$png('javob.png')]])->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Models\SupportAttachment::withoutGlobalScopes()->count());

        // boshqa markaz direktori rasmni ocha olmaydi; sAdmin yo'li direktorga yopiq
        $other = $this->user(Role::Owner, null, [], ['center_id' => $this->newCenter('Boshqa markaz')->id]);
        $this->actingAs($other)->get(route('center.support.file', $a->id))->assertNotFound();
        $this->actingAs($this->owner)->get(route('platform.support.file', $a->id))->assertForbidden();
    }

    public function test_attachments_reject_non_images_and_too_many(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner)->post(route('center.support.store'), ['subject' => 'Fayl', 'body' => 'PDF yuboryapman', 'files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->post(route('center.support.store'), ['subject' => 'Ko\'p', 'body' => 'Ko\'p rasm', 'files' => [UploadedFile::fake()->image('1.png'), UploadedFile::fake()->image('2.png'), UploadedFile::fake()->image('3.png'), UploadedFile::fake()->image('4.png')]])->assertSessionHasErrors('files');
        $this->post(route('center.support.store'), ['subject' => 'Katta', 'body' => 'Juda katta rasm', 'files' => [UploadedFile::fake()->image('x.png')->size(6000)]])->assertSessionHasErrors('files.0');
        $this->assertSame(0, SupportTicket::withoutGlobalScopes()->count());
    }

    public function test_guide_cover_uses_youtube_thumbnail_or_uploaded_image(): void
    {
        Storage::fake('uploads');
        $g = VideoGuide::create(['title' => 'Kirish', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'roles' => ['admin'], 'is_active' => true, 'sort_order' => 1]);
        $admin = $this->user(Role::Admin);

        $this->actingAs($admin)->get(route('guides.index'))->assertOk()->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false);

        $this->actingAs($this->sadmin)->post(route('platform.guides.cover', $g->id), ['cover' => UploadedFile::fake()->image('c.png', 640, 360)])->assertSessionHasNoErrors();
        $g->refresh();
        $this->assertNotNull($g->cover_path);
        Storage::disk('uploads')->assertExists($g->cover_path);
        $this->actingAs($admin)->get(route('guides.index'))->assertSee('uploads/'.$g->cover_path, false)->assertDontSee('i.ytimg.com', false);

        $this->actingAs($this->sadmin)->delete(route('platform.guides.cover.remove', $g->id));
        $this->assertNull($g->fresh()->cover_path);
        $this->actingAs($this->sadmin)->post(route('platform.guides.cover', $g->id), ['cover' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])->assertSessionHasErrors('cover');
    }
}
