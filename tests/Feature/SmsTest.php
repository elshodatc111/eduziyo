<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SmsService;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approveSmsTemplates();
        Carbon::setTestNow('2026-09-21 10:00:00');

        config(['services.eskiz.email' => 'global@eskiz.uz', 'services.eskiz.password' => 'secret', 'services.eskiz.from' => '4546']);
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'TOKEN-1']]),
            '*/message/sms/send' => Http::response(['status' => 'waiting', 'id' => 'x']),
        ]);

        $this->branch = $this->branch('Toshkent');
        $this->admin = $this->user(Role::Admin, $this->branch, ['students.create', 'students.view', 'payments.create', 'payments.refund', 'sms.view', 'sms.send', 'sms.manage', 'groups.view']);
        $this->actingAs($this->admin);
    }

    private function enable(): void
    {
        $this->branch->update(['sms_enabled' => true]);
    }

    public function test_nothing_is_sent_while_branch_sms_is_disabled(): void
    {
        [$student] = app(StudentService::class)->create(['name' => 'Ali', 'phone' => '+998 90 123 4567'], $this->admin);
        app(PaymentService::class)->receive($student, ['cash' => 100000], null, null, $this->admin);

        $this->assertSame(0, SmsMessage::count());
        Http::assertNothingSent();
    }

    public function test_welcome_and_payment_sms_are_sent_with_rendered_text(): void
    {
        $this->enable();

        [$student, $password] = app(StudentService::class)->create(['name' => 'ali valiyev', 'phone' => '+998 90 123 4567'], $this->admin);
        $welcome = SmsMessage::where('template_key', 'student_welcome')->firstOrFail();
        $this->assertSame('sent', $welcome->status);
        $this->assertSame('998901234567', $welcome->phone);
        $this->assertStringContainsString('ALI VALIYEV', $welcome->message);
        $this->assertStringContainsString('Toshkent', $welcome->message);
        $this->assertStringContainsString('@eduziyocrmbot', $welcome->message);
        // SMS orqali hech qanday parol yuborilmaydi
        $this->assertStringNotContainsString($password, $welcome->message);
        $this->assertNull($welcome->secret);

        app(PaymentService::class)->receive($student, ['cash' => 250000], null, null, $this->admin);
        $payment = SmsMessage::where('template_key', 'payment_received')->firstOrFail();
        $this->assertStringContainsString("250 000 so'm", $payment->message);

        // Eskizga yuborilgan haqiqiy so'rovda ham parol yo'q
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/message/sms/send') && $r['mobile_phone'] === '998901234567' && $r['from'] === '4546' && $r->hasHeader('Authorization', 'Bearer TOKEN-1') && ! str_contains($r['message'], $password));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/message/sms/send') && str_contains((string) $r['message'], $password));
    }

    public function test_disabled_templates_are_not_sent_and_can_be_enabled(): void
    {
        $this->enable();
        [$student] = app(StudentService::class)->create(['name' => 'Ali', 'phone' => '+998 90 123 4567'], $this->admin);

        app(\App\Services\SmsNotifier::class)->notify('birthday', $student);
        $this->assertSame(0, SmsMessage::where('template_key', 'birthday')->count());   // standart holatda o'chiq

        // filial faqat yoqadi/o'chiradi; yuborilgan "body" e'tiborga olinmaydi (matn umumiy, faqat sAdmin tahrirlaydi)
        $this->put('/sms/settings', [
            'sms_enabled' => 1,
            'templates' => ['birthday' => ['enabled' => 1, 'body' => 'Mening matnim'], 'payment_received' => ['enabled' => 0]],
        ])->assertRedirect();

        app(\App\Services\SmsNotifier::class)->notify('birthday', $student);
        $this->assertSame(1, SmsMessage::where('template_key', 'birthday')->count());
        $msg = SmsMessage::where('template_key', 'birthday')->value('message');
        $this->assertStringNotContainsString('Mening matnim', $msg);
        $this->assertStringStartsWith('Hurmatli ', $msg);

        // "payment_received" o'chirildi -> yuborilmaydi
        app(PaymentService::class)->receive($student, ['cash' => 1000], null, null, $this->admin);
        $this->assertSame(0, SmsMessage::where('template_key', 'payment_received')->count());
    }

    public function test_branch_never_sees_template_text_and_password_is_never_sent(): void
    {
        $this->enable();

        $page = $this->get('/sms')->assertOk();
        $page->assertSee("Yangi o'quvchiga xush kelibsiz");   // xabar turi nomi ko'rinadi
        $page->assertDontSee('@eduziyocrmbot');               // matnning o'zi ko'rinmaydi

        $this->assertArrayNotHasKey('{password}', \App\Support\SmsTemplates::PLACEHOLDERS);
        $this->assertArrayNotHasKey('password_reset', \App\Support\SmsTemplates::all());

        // parol o'zgaruvchisi berilsa ham matnga tushmaydi
        [$student] = app(StudentService::class)->create(['name' => 'Ali', 'phone' => '+998 90 123 4567'], $this->admin);
        app(\App\Services\SmsNotifier::class)->notify('student_welcome', $student, ['login' => 'x', 'password' => 'abc12345']);
        $this->assertSame(0, SmsMessage::where('message', 'like', '%abc12345%')->count());
    }

    public function test_invalid_phone_and_provider_errors_are_recorded(): void
    {
        $this->enable();

        $bad = app(SmsService::class)->queue($this->branch, '12345', 'Salom', null, null, $this->admin);
        $this->assertSame('failed', $bad->status);

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*/auth/login' => Http::response(['data' => ['token' => 'T']]), '*/message/sms/send' => Http::response(['status' => 'error', 'message' => 'Shablon tasdiqlanmagan'], 400)]);
        $sms = app(SmsService::class)->queue($this->branch, '901234567', $this->approvedSmsText(), 'payment_received', null, $this->admin);
        $this->assertSame('failed', $sms->fresh()->status);
        $this->assertStringContainsString('Shablon', $sms->fresh()->provider_response);

        // sAdmin tasdiqlamagan (yoki shablonga mos kelmaydigan) matn Eskiz'ga umuman yuborilmaydi
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake();
        $free = app(SmsService::class)->queue($this->branch, '901234567', 'Erkin matn', null, null, $this->admin);
        $this->assertSame('failed', $free->fresh()->status);
        $this->assertStringContainsString('faqat modem', $free->fresh()->provider_response);
        $custom = app(SmsService::class)->queue($this->branch, '901234567', "Boshqa matn bu", 'payment_received', null, $this->admin);
        $this->assertSame('failed', $custom->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_platform_eskiz_account_is_used_and_expired_token_is_refreshed(): void
    {
        $this->enable();
        config(['services.eskiz.email' => 'filial@eskiz.uz', 'services.eskiz.password' => 'own-pass', 'services.eskiz.from' => 'FILIAL']);

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'NEW']]),
            '*/message/sms/send' => Http::sequence()->push(['message' => 'Expired'], 401)->push(['status' => 'waiting']),
        ]);

        $sms = app(SmsService::class)->queue($this->branch, '901234567', $this->approvedSmsText(), 'payment_received', null, $this->admin);

        $this->assertSame('sent', $sms->fresh()->status);
        $this->assertSame('eskiz', $sms->fresh()->channel);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/auth/login') && $r['email'] === 'filial@eskiz.uz');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/message/sms/send') && $r['from'] === 'FILIAL' && $r->hasHeader('Authorization', 'Bearer NEW'));
        Http::assertSentCount(4);   // login, 401, qayta login, yuborish
    }

    public function test_missing_credentials_mark_message_failed(): void
    {
        $this->enable();
        config(['services.eskiz.email' => null, 'services.eskiz.password' => null]);

        $sms = app(SmsService::class)->queue($this->branch, '901234567', $this->approvedSmsText(), 'payment_received', null, $this->admin);

        $this->assertSame('failed', $sms->fresh()->status);
        $this->assertStringContainsString('sozlanmagan', $sms->fresh()->provider_response);
    }

    public function test_bulk_sms_audiences_and_deduplication(): void
    {
        // v8 B9: /sms/bulk endi to'g'ridan-to'g'ri yubormaydi - avval "Tekshiring" sahifasiga
        // yo'naltiradi, faqat shu yerda tasdiqlangach navbatga qo'yiladi.
        $this->enable();
        $debtor = $this->student($this->branch, ['name' => 'Qarzdor', 'phone' => '+998 90 111 1111', 'balance' => -120000]);
        $rich = $this->student($this->branch, ['name' => 'Boy', 'phone' => '+998 90 222 2222', 'balance' => 50000]);
        $noPhone = $this->student($this->branch, ['name' => 'Raqamsiz', 'phone' => null, 'balance' => -1]);
        $foreign = $this->student($this->branch('Boshqa'), ['phone' => '+998 90 333 3333', 'balance' => -999]);

        $resp = $this->post('/sms/bulk', ['audience' => 'debtors', 'message' => 'Hurmatli {name}, qarz {debt}']);
        $resp->assertRedirect();
        $url = $resp->headers->get('Location');
        $this->assertStringContainsString('/confirm/', $url);
        $this->post($url)->assertRedirect()->assertSessionHas('success');

        $sent = SmsMessage::orderBy('id')->get();
        $this->assertCount(1, $sent);                                 // raqamsiz va boshqa filial o'quvchisi yo'q
        $this->assertStringContainsString("120 000 so'm", $sent[0]->message);

        SmsMessage::query()->delete();
        $resp = $this->post('/sms/bulk', ['audience' => 'all', 'message' => 'Salom']);
        $this->post($resp->headers->get('Location'))->assertRedirect();
        $this->assertSame(2, SmsMessage::count());                    // 901111111 va 902222222

        $this->post('/sms/bulk', ['audience' => 'group', 'message' => 'Salom'])->assertSessionHasErrors('group_id');
    }

    public function test_bulk_requires_enabled_sms_and_permission(): void
    {
        $this->post('/sms/bulk', ['audience' => 'all', 'message' => 'Salom'])->assertSessionHasErrors('message');

        $manager = $this->user(Role::Manager, $this->branch, ['sms.view']);
        $this->actingAs($manager)->post('/sms/bulk', ['audience' => 'all', 'message' => 'x'])->assertForbidden();
        $this->actingAs($manager)->put('/sms/settings', ['templates' => []])->assertForbidden();
        $this->actingAs($manager)->get('/sms')->assertOk();

        $teacher = $this->user(Role::Teacher, $this->branch, ['attendance.view']);
        $this->actingAs($teacher)->get('/sms')->assertForbidden();
    }

    public function test_birthday_command_sends_once_per_day(): void
    {
        $this->enable();
        $this->put('/sms/settings', ['sms_enabled' => 1, 'templates' => ['birthday' => ['enabled' => 1, 'body' => 'Tabriklaymiz, {name}!']]]);

        $this->student($this->branch, ['name' => 'Tugilgan', 'phone' => '+998 90 123 4567', 'birthday' => '2005-09-21']);
        $this->student($this->branch, ['name' => 'Boshqa kun', 'phone' => '+998 90 222 2222', 'birthday' => '2005-09-22']);

        $this->artisan('sms:birthdays')->assertSuccessful();
        $this->artisan('sms:birthdays')->assertSuccessful();

        $this->assertSame(1, SmsMessage::where('template_key', 'birthday')->count());
        $this->assertStringContainsString('Tugilgan', SmsMessage::where('template_key', 'birthday')->value('message') ?? '') ;
    }

    public function test_sms_page_renders_and_history_is_scoped(): void
    {
        $this->enable();
        app(SmsService::class)->queue($this->branch, '901234567', 'Mening xabarim', null, null, $this->admin);
        $other = $this->branch('Boshqa');
        app(SmsService::class)->queue($other, '902222222', 'Begona xabar', null, null, $this->admin);

        $this->get('/sms')->assertOk()->assertSee('Mening xabarim')->assertDontSee('Begona xabar')->assertSee('Ommaviy yuborish');
    }

    public function test_migration_removes_password_templates_and_old_branch_copies(): void
    {
        \App\Services\Sms\SmsPlatformTemplates::ensureSeeded();
        \App\Models\SmsPlatformTemplate::forceCreate(['key' => 'password_reset', 'label' => 'Eski', 'body' => 'Yangi parol: {password}', 'is_approved' => false]);
        \App\Models\SmsPlatformTemplate::where('key', 'student_welcome')->update(['body' => 'Login: {login}, parol: {password}', 'is_approved' => false]);
        \App\Models\SmsTemplate::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'key' => 'payment_received', 'body' => 'Eski filial matni', 'is_enabled' => true]);

        (require base_path('database/migrations/2026_11_21_000001_eduziyo_sms_global_templates.php'))->up();

        $this->assertSame(0, \App\Models\SmsPlatformTemplate::where('key', 'password_reset')->count());
        $welcome = \App\Models\SmsPlatformTemplate::where('key', 'student_welcome')->firstOrFail();
        $this->assertStringNotContainsString('{password}', $welcome->body);
        $this->assertSame(0, \App\Models\SmsPlatformTemplate::where('is_approved', false)->count());
        $this->assertSame(\App\Models\SmsPlatformTemplate::where('key', 'payment_received')->value('body'), \App\Models\SmsTemplate::withoutGlobalScopes()->where('key', 'payment_received')->value('body'));
        $this->assertSame(0, \App\Models\SmsPlatformTemplate::where('body', 'like', '%{password}%')->count());
    }
}
