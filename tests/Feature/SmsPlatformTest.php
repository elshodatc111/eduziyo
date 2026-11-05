<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Center;
use App\Models\CenterBalanceTransaction;
use App\Models\PlatformSetting;
use App\Models\SmsInvoice;
use App\Models\SmsMessage;
use App\Models\SmsOutbox;
use App\Models\Tariff;
use App\Services\CenterBalanceService;
use App\Services\CenterLifecycle;
use App\Services\Sms\ModemDriver;
use App\Services\Sms\SmsDispatcher;
use App\Services\SmsInvoiceService;
use App\Services\SmsService;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** E5: USB modem gateway, Eskiz zaxirasi, SMS jurnali, SMS faktura va qo'shimcha SMS havzasi. */
class SmsPlatformTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');
        Cache::flush();
        config(['services.eskiz.email' => 'g@eskiz.uz', 'services.eskiz.password' => 's', 'services.eskiz.from' => '4546']);
        Http::fake(['*/auth/login' => Http::response(['data' => ['token' => 'T']]), '*/message/sms/send' => Http::response(['status' => 'waiting', 'id' => 'x'])]);

        $this->branch = $this->branch('Toshkent');
        $this->branch->update(['sms_enabled' => true]);
        $this->owner = $this->user(Role::Owner, null, [], ['center_id' => $this->center()->id]);
    }


    private function send(string $phone = '+998 90 123 4567', string $text = 'Salom'): SmsMessage
    {
        return app(SmsService::class)->queue($this->branch, $phone, $text);
    }



    public function test_without_live_modem_sms_goes_straight_to_eskiz(): void
    {
        $sms = $this->send()->fresh();

        $this->assertSame('sent', $sms->status);
        $this->assertSame('eskiz', $sms->channel);
        $this->assertSame(0, SmsOutbox::count());
    }





    private function subscribe(Center $c, int $sms = 2): void
    {
        $t = Tariff::forceCreate(['slug' => 't-'.uniqid(), 'name' => 'Mini', 'price_monthly' => 100_000, 'price_yearly' => 1_000_000, 'max_branches' => 2, 'max_students' => 50, 'sms_monthly' => $sms, 'ai_monthly' => 1, 'is_active' => true, 'sort_order' => 1]);
        app(SubscriptionService::class)->grant($c, $t, 30);
    }

    public function test_invoice_lifecycle_accept_debits_balance_and_adds_sms(): void
    {
        $c = $this->center();
        $this->subscribe($c);
        $inv = app(SmsInvoiceService::class);

        $i = $inv->create($c, 500, 40_000, 'Aksiya', $this->owner->id);
        $this->assertSame('pending', $i->status);

        try {
            $inv->create($c, 100, 1000, null, $this->owner->id);
            $this->fail('ikkinchi faol faktura bo\'lmasligi kerak');
        } catch (DomainException) {
        }

        // balans yetmasa — hech narsa o'zgarmaydi
        try {
            $inv->accept($i, $this->owner->id);
            $this->fail('balans yetmaydi');
        } catch (DomainException) {
        }
        $this->assertSame('pending', $i->fresh()->status);
        $this->assertSame(0, app(UsageService::class)->extra($c));

        app(CenterBalanceService::class)->credit($c, 50_000, CenterBalanceTransaction::TOPUP);
        $inv->accept($i, $this->owner->id);

        $this->assertSame('accepted', $i->fresh()->status);
        $this->assertSame(10_000, app(CenterBalanceService::class)->balance($c));
        $this->assertSame(500, app(UsageService::class)->extra($c));
        $this->assertSame(1, CenterBalanceTransaction::withoutGlobalScopes()->where('type', 'sms_pack')->count());

        $this->expectException(DomainException::class);
        $inv->accept($i, $this->owner->id);   // qayta qabul qilib bo'lmaydi
    }

    public function test_invoice_expires_after_three_days_and_can_be_cancelled(): void
    {
        $c = $this->center();
        $this->subscribe($c);
        $inv = app(SmsInvoiceService::class);
        $i = $inv->create($c, 10, 1000, null, $this->owner->id);

        Carbon::setTestNow(now()->addDays(3)->addMinute());
        $this->assertNull($inv->active($c));
        app(CenterLifecycle::class)->run();
        $this->assertSame('expired', $i->fresh()->status);

        $j = $inv->create($c, 10, 1000, null, $this->owner->id);
        $inv->cancel($j, $this->owner->id);
        $this->assertSame('cancelled', $j->fresh()->status);
        $this->assertNotNull($inv->create($c, 5, 500, null, $this->owner->id));
    }

    public function test_extra_pool_is_used_after_monthly_limit(): void
    {
        $c = $this->center();
        $this->subscribe($c, 2);
        $u = app(UsageService::class);
        $this->assertTrue($u->consume($c, 'sms'));
        $this->assertTrue($u->consume($c, 'sms'));
        $this->assertFalse($u->consume($c, 'sms'), 'limit tugadi');

        \App\Models\CenterSubscription::withoutGlobalScopes()->where('center_id', $c->id)->update(['extra_sms' => 2]);
        $this->assertTrue($u->consume($c, 'sms'));
        $this->assertSame(1, $u->extra($c));
        $this->assertTrue($u->consume($c, 'sms'));
        $this->assertFalse($u->consume($c, 'sms'));
    }

    public function test_owner_accepts_invoice_through_ui_and_cannot_touch_foreign_invoice(): void
    {
        $c = $this->center();
        $this->subscribe($c);
        app(CenterBalanceService::class)->credit($c, 100_000, 'topup');
        $i = app(SmsInvoiceService::class)->create($c, 300, 30_000, null, $this->owner->id);

        $other = $this->newCenter('Boshqa');
        $foreign = app(SmsInvoiceService::class)->create($other, 10, 100, null, $this->owner->id);

        $this->actingAs($this->owner)->get(route('center.sms'))->assertOk()->assertSee('300');
        $this->actingAs($this->owner)->post(route('center.sms.accept', $foreign->id))->assertNotFound();
        $this->actingAs($this->owner)->post(route('center.sms.accept', $i->id))->assertRedirect();
        $this->assertSame(300, app(UsageService::class)->extra($c));
    }

    public function test_sadmin_log_invoice_and_retention_pages(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->send();
        $this->subscribe($this->center());

        $this->actingAs($sadmin)->get(route('platform.sms'))->assertOk()->assertSee('Salom');
        $this->actingAs($sadmin)->post(route('platform.sms.invoices.store', $this->center()), ['sms_count' => 100, 'amount' => 20000])->assertRedirect();
        $inv = SmsInvoice::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($sadmin)->post(route('platform.sms.invoices.cancel', $inv->id))->assertRedirect();
        $this->assertSame('cancelled', $inv->fresh()->status);

        $this->actingAs($sadmin)->put(route('platform.sms.settings'), ['sms_text_retention_days' => 7])->assertRedirect();
        $this->assertSame('7', PlatformSetting::value('sms_text_retention_days'));

        $this->actingAs($this->owner)->get(route('platform.sms'))->assertForbidden();
    }

    public function test_prune_text_clears_old_messages_only(): void
    {
        $old = $this->send('+998 90 123 4567', 'Eski matn');
        SmsMessage::withoutGlobalScopes()->whereKey($old->id)->update(['created_at' => now()->subDays(40)]);
        $new = $this->send('+998 91 123 4567', 'Yangi matn');

        PlatformSetting::put('sms_text_retention_days', '0');
        $this->artisan('sms:prune-text')->assertSuccessful();
        $this->assertSame('Eski matn', $old->fresh()->message);

        PlatformSetting::put('sms_text_retention_days', '30');
        $this->artisan('sms:prune-text')->assertSuccessful();
        $this->assertSame("[o'chirildi]", SmsMessage::withoutGlobalScopes()->find($old->id)->message);
        $this->assertSame('Yangi matn', SmsMessage::withoutGlobalScopes()->find($new->id)->message);
    }
}
