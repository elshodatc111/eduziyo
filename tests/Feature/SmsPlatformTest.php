<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Center;
use App\Models\CenterSmsAccount;
use App\Models\CenterSmsTemplateSync;
use App\Models\PlatformSetting;
use App\Models\SmsMessage;
use App\Models\SmsPlatformTemplate;
use App\Models\Tariff;
use App\Services\CenterAlerts;
use App\Services\Sms\EskizTemplateSync;
use App\Services\SmsService;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** SMS yo'nalishi: gateway → platforma Eskiz'i (limit ichida), limit tugagach markazning o'z Eskiz'i; faqat tasdiqlangan matnlar. */
class SmsPlatformTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approveSmsTemplates();
        Carbon::setTestNow('2026-11-05 10:00:00');
        Cache::flush();
        CenterAlerts::$sent = [];
        config(['services.eskiz.email' => 'g@eskiz.uz', 'services.eskiz.password' => 's', 'services.eskiz.from' => '4546']);
        $this->fakeEskiz();

        $this->branch = $this->branch('Toshkent');
        $this->branch->update(['sms_enabled' => true]);
        $this->owner = $this->user(Role::Owner, null, [], ['center_id' => $this->center()->id, 'phone' => '+998 90 000 1122']);
    }

    private function fakeEskiz(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'T']]),
            '*/message/sms/send' => Http::response(['status' => 'waiting', 'id' => 'x']),
            '*/user/templates' => Http::response(['data' => []]),
            '*/user/template' => Http::response(['data' => ['id' => 77]]),
        ]);
    }

    private function send(string $phone = '+998 90 123 4567', ?string $text = null, ?string $key = 'payment_received'): SmsMessage
    {
        return app(SmsService::class)->queue($this->branch, $phone, $text ?? $this->approvedSmsText(), $key);
    }

    private function subscribe(Center $c, int $sms = 2): void
    {
        $t = Tariff::forceCreate(['slug' => 't-'.uniqid(), 'name' => 'Mini', 'price_monthly' => 100_000, 'price_yearly' => 1_000_000, 'max_branches' => 2, 'max_students' => 50, 'sms_monthly' => $sms, 'ai_monthly' => 1, 'is_active' => true, 'sort_order' => 1]);
        app(SubscriptionService::class)->grant($c, $t, 30);
    }

    private function ownAccount(?Center $c = null): CenterSmsAccount
    {
        return CenterSmsAccount::withoutGlobalScopes()->create([
            'center_id' => ($c ?? $this->center())->id, 'email' => 'own@eskiz.uz', 'password' => 'own-pass', 'sender' => 'MARKAZ', 'status' => 'connected', 'connected_at' => now(),
        ]);
    }

    public function test_without_gateway_tariff_sms_goes_to_platform_eskiz(): void
    {
        $sms = $this->send()->fresh();

        $this->assertSame('sent', $sms->status);
        $this->assertSame('eskiz', $sms->channel);
    }

    public function test_only_approved_template_texts_go_through_eskiz(): void
    {
        SmsPlatformTemplate::where('key', 'payment_received')->update(['is_approved' => false]);
        $this->fakeEskiz();

        $a = $this->send()->fresh();                                   // shablon tasdiqlanmagan
        $this->assertSame('failed', $a->status);
        $this->assertStringContainsString('tasdiqlanmagan', $a->provider_response);

        $this->approveSmsTemplates();
        $b = $this->send('+998 90 123 4567', 'Mutlaqo boshqa matn', 'payment_received')->fresh();   // tasdiqlangan matnga mos emas
        $c = $this->send('+998 90 123 4567', $this->approvedSmsText(), null)->fresh();               // shablonsiz (erkin) matn
        $this->assertSame('failed', $b->status);
        $this->assertSame('failed', $c->status);
        Http::assertNothingSent();

        $this->assertSame('sent', $this->send()->fresh()->status);   // mos matn o'tadi
    }

    public function test_limit_exhausted_without_own_eskiz_stops_sms_and_warns_director_once(): void
    {
        $c = $this->center();
        $this->subscribe($c, 2);

        $this->assertSame('sent', $this->send()->fresh()->status);
        $this->assertSame('sent', $this->send('+998 91 123 4567')->fresh()->status);
        $this->assertFalse(app(UsageService::class)->allowed($c, 'sms'));

        $third = $this->send('+998 93 123 4567')->fresh();
        $this->assertSame('failed', $third->status);
        $this->assertStringContainsString('Eskiz', $third->provider_response);

        // direktorga tizim SMS'i (limitga sanalmaydi) va Telegram/jurnal ogohlantirishi
        $warn = SmsMessage::withoutGlobalScopes()->where('template_key', 'sms_limit_reached')->firstOrFail();
        $this->assertSame('sent', $warn->status);
        $this->assertSame('998900001122', $warn->phone);
        $this->assertStringContainsString($c->name, $warn->message);
        $this->assertSame(2, app(UsageService::class)->used($c, 'sms'));
        $this->assertCount(1, array_filter(CenterAlerts::$sent, fn ($a) => str_contains($a['text'], 'Eskiz')));

        // shu oyda qayta ogohlantirilmaydi
        $this->send('+998 94 123 4567');
        $this->assertSame(1, SmsMessage::withoutGlobalScopes()->where('template_key', 'sms_limit_reached')->count());
    }

    public function test_after_limit_center_own_eskiz_is_used_and_all_branches_share_the_limit(): void
    {
        $c = $this->center();
        $this->subscribe($c, 1);
        $this->ownAccount();
        $second = $this->branch('Samarqand');
        $second->update(['sms_enabled' => true]);

        $this->assertSame('eskiz', $this->send()->fresh()->channel);                     // tarif ichida
        $other = app(SmsService::class)->queue($second, '+998 91 123 4567', $this->approvedSmsText(), 'payment_received')->fresh();   // boshqa filial — limit umumiy
        $this->assertSame('own', $other->channel);
        $this->assertSame('sent', $other->status);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/auth/login') && $r['email'] === 'own@eskiz.uz');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/message/sms/send') && $r['from'] === 'MARKAZ');
        $this->assertSame(1, app(UsageService::class)->used($c, 'sms'));                  // markaz Eskiz'i tarif hisobini oshirmaydi
        $this->assertSame(0, SmsMessage::withoutGlobalScopes()->where('template_key', 'sms_limit_reached')->count());

        // markaz Eskiz'ida ham faqat tasdiqlangan matn
        $free = app(SmsService::class)->queue($second, '+998 91 123 4567', 'Erkin matn', null)->fresh();
        $this->assertSame('failed', $free->status);
    }

    public function test_owner_connects_validates_and_disconnects_eskiz_and_templates_are_submitted(): void
    {
        $this->actingAs($this->owner)->get(route('center.sms'))->assertOk()->assertSee('Ulanmagan');

        // noto'g'ri login — saqlanmaydi
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*/auth/login' => Http::response(['message' => 'bad'], 401)]);
        $this->put(route('center.sms.eskiz.connect'), ['email' => 'x@eskiz.uz', 'password' => 'noto\'g\'ri', 'sender' => '4546'])->assertSessionHasErrors('password');
        $this->assertSame(0, CenterSmsAccount::withoutGlobalScopes()->count());

        $this->fakeEskiz();
        $this->put(route('center.sms.eskiz.connect'), ['email' => 'own@eskiz.uz', 'password' => 'maxfiy-parol', 'sender' => '4546'])->assertSessionHasNoErrors();
        $acc = CenterSmsAccount::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('maxfiy-parol', $acc->password);
        $this->assertStringNotContainsString('maxfiy', $acc->getRawOriginal('password'));      // bazada shifrlangan

        // tasdiqlangan hamma matn markaz Eskiz'iga moderatsiyaga yuborilgan
        $approved = SmsPlatformTemplate::where('is_approved', true)->count();
        $this->assertGreaterThan(5, $approved);
        $this->assertSame($approved, CenterSmsTemplateSync::withoutGlobalScopes()->where('center_id', $this->center()->id)->where('status', 'pending')->count());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/user/template') && str_contains($r['template'], 'Hurmatli'));

        $this->get(route('center.sms'))->assertOk()->assertSee('Moderatsiyada')->assertDontSee('maxfiy-parol');

        // parol bo'sh qoldirilsa eskisi saqlanadi
        $this->put(route('center.sms.eskiz.connect'), ['email' => 'own@eskiz.uz', 'password' => '', 'sender' => 'YANGI'])->assertSessionHasNoErrors();
        $this->assertSame('maxfiy-parol', $acc->fresh()->password);
        $this->assertSame('YANGI', $acc->fresh()->sender);

        $this->delete(route('center.sms.eskiz.disconnect'))->assertRedirect();
        $this->assertSame(0, CenterSmsAccount::withoutGlobalScopes()->count());
        $this->assertSame(0, CenterSmsTemplateSync::withoutGlobalScopes()->count());
    }

    public function test_eskiz_account_is_isolated_between_centers_and_only_owner_manages(): void
    {
        $other = $this->newCenter('Boshqa');
        $this->ownAccount($other);

        $this->actingAs($this->owner)->get(route('center.sms'))->assertOk()->assertSee('Ulanmagan');
        $this->delete(route('center.sms.eskiz.disconnect'))->assertRedirect();
        $this->assertSame(1, CenterSmsAccount::withoutGlobalScopes()->where('center_id', $other->id)->count());   // boshqa markazniki tegilmadi

        $admin = $this->user(Role::Admin, $this->branch, ['sms.manage']);
        $this->actingAs($admin)->put(route('center.sms.eskiz.connect'), ['email' => 'a@b.uz', 'password' => 'x', 'sender' => '4546'])->assertForbidden();
        $this->actingAs($admin)->get(route('center.sms'))->assertForbidden();
    }

    public function test_sadmin_approves_edits_and_revokes_templates(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        SmsPlatformTemplate::query()->update(['is_approved' => false]);
        $this->ownAccount();

        $this->actingAs($sadmin)->get(route('platform.sms.templates'))->assertOk()->assertSee('Tasdiqlanmagan');
        $this->post(route('platform.sms.templates.approve', 'payment_received'))->assertRedirect();

        $t = SmsPlatformTemplate::where('key', 'payment_received')->firstOrFail();
        $this->assertTrue($t->is_approved);
        $this->assertSame('pending', $t->eskiz_status);                       // platforma hisobiga yuborildi
        $this->assertSame('pending', CenterSmsTemplateSync::withoutGlobalScopes()->where('template_key', 'payment_received')->value('status'));   // ulangan markazga ham

        // matn o'zgarsa tasdiq bekor bo'ladi
        $this->put(route('platform.sms.templates.update', 'payment_received'), ['body' => "Hurmatli {name}, {amount} qabul qilindi. Rahmat!"])->assertRedirect();
        $this->assertFalse($t->fresh()->is_approved);
        $this->assertSame(0, CenterSmsTemplateSync::withoutGlobalScopes()->where('template_key', 'payment_received')->count());
        $this->put(route('platform.sms.templates.update', 'payment_received'), ['body' => 'Noma\'lum {zzz} o\'zgaruvchi bor matn'])->assertSessionHasErrors('body');

        // yangi matn filial standarti bo'ladi
        $this->assertSame("Hurmatli {name}, {amount} qabul qilindi. Rahmat!", app(SmsService::class)->template($this->branch, 'payment_received')['body']);

        $this->post(route('platform.sms.templates.approve', 'payment_received'));
        $this->post(route('platform.sms.templates.revoke', 'payment_received'))->assertRedirect();
        $this->assertFalse($t->fresh()->is_approved);

        $this->actingAs($this->owner)->get(route('platform.sms.templates'))->assertForbidden();
        $this->actingAs($this->owner)->post(route('platform.sms.templates.approve', 'payment_received'))->assertForbidden();
    }

    public function test_status_refresh_marks_templates_approved_by_eskiz(): void
    {
        $this->ownAccount();
        SmsPlatformTemplate::query()->update(['is_approved' => false]);
        $tpl = SmsPlatformTemplate::where('key', 'payment_received')->firstOrFail();
        $tpl->update(['is_approved' => true]);
        $sync = app(EskizTemplateSync::class);
        $sync->onApproved($tpl->fresh());

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'T']]),
            '*/user/templates' => Http::response(['data' => [['id' => 77, 'template' => $tpl->body, 'status' => 'service']]]),
        ]);
        $sync->refreshAll();

        $this->assertSame('approved', $tpl->fresh()->eskiz_status);
        $this->assertSame('approved', CenterSmsTemplateSync::withoutGlobalScopes()->where('template_key', 'payment_received')->value('status'));
    }

    public function test_invalid_center_credentials_mark_account_as_error(): void
    {
        $this->subscribe($this->center(), 1);
        $this->send();                                                  // limitni tugatamiz
        $acc = $this->ownAccount();

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*/auth/login' => Http::response(['message' => 'bad'], 401)]);
        $sms = $this->send('+998 91 123 4567')->fresh();

        $this->assertSame('failed', $sms->status);
        $this->assertSame('error', $acc->fresh()->status);
        $this->assertNotNull($acc->fresh()->last_error);
    }

    public function test_sadmin_log_page_retention_and_platform_eskiz_account(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->send();

        $this->actingAs($sadmin)->get(route('platform.sms'))->assertOk()->assertSee('Hurmatli Ali');
        $this->put(route('platform.sms.settings'), ['sms_text_retention_days' => 7])->assertRedirect();
        $this->assertSame('7', PlatformSetting::value('sms_text_retention_days'));
        $this->actingAs($this->owner)->get(route('platform.sms'))->assertForbidden();

        // platforma Eskiz hisobi: noto'g'ri bo'lsa saqlanmaydi
        $this->actingAs($sadmin);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*/auth/login' => Http::response(['message' => 'bad'], 401)]);
        $this->put(route('platform.integrations.eskiz'), ['email' => 'new@eskiz.uz', 'password' => 'yomon', 'from' => '4546'])->assertSessionHasErrors('password');
        $this->assertNotSame('new@eskiz.uz', \App\Support\IntegrationSettings::get('eskiz_email'));

        $this->fakeEskiz();
        $this->put(route('platform.integrations.eskiz'), ['email' => 'new@eskiz.uz', 'password' => 'yaxshi', 'from' => 'EDUCRM'])->assertSessionHasNoErrors();
        $this->assertSame('new@eskiz.uz', \App\Support\IntegrationSettings::get('eskiz_email'));
        $this->assertSame('yaxshi', \App\Support\IntegrationSettings::get('eskiz_password'));
        $this->assertStringNotContainsString('yaxshi', \App\Models\PlatformSetting::where('key', 'int_eskiz_password')->value('value'));
    }

    public function test_prune_text_clears_old_messages_only(): void
    {
        $old = $this->send('+998 90 123 4567');
        SmsMessage::withoutGlobalScopes()->whereKey($old->id)->update(['created_at' => now()->subDays(40)]);
        $new = $this->send('+998 91 123 4567');

        PlatformSetting::put('sms_text_retention_days', '0');
        $this->artisan('sms:prune-text')->assertSuccessful();
        $this->assertStringContainsString('Hurmatli', $old->fresh()->message);

        PlatformSetting::put('sms_text_retention_days', '30');
        $this->artisan('sms:prune-text')->assertSuccessful();
        $this->assertSame("[o'chirildi]", SmsMessage::withoutGlobalScopes()->find($old->id)->message);
        $this->assertStringContainsString('Hurmatli', SmsMessage::withoutGlobalScopes()->find($new->id)->message);
    }
}
