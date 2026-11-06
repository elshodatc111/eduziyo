<?php

namespace Tests\Feature;

use App\Models\SmsMessage;
use App\Models\SmsPlatformTemplate;
use App\Services\CenterAlerts;
use App\Services\SmsService;
use App\Services\UsageService;
use App\Support\IntegrationSettings as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** SMS Gateway (asosiy kanal) va Eskiz'ga avtomatik o'tish. */
class SmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_123';

    private $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approveSmsTemplates();
        Carbon::setTestNow('2026-11-05 10:00:00');
        Cache::flush();
        CenterAlerts::$sent = [];
        config(['services.eskiz.email' => 'g@eskiz.uz', 'services.eskiz.password' => 's', 'services.eskiz.from' => '4546']);
        S::set('gateway_url', 'https://sms.test');
        S::set('gateway_key', 'sk_sms_'.str_repeat('k', 20));
        S::set('gateway_secret', self::SECRET);
        $this->branch = $this->branch('Toshkent');
        $this->branch->update(['sms_enabled' => true]);
    }

    private function fake(array $gateway): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($gateway + [
            '*/auth/login' => Http::response(['data' => ['token' => 'T']]),
            '*/message/sms/send' => Http::response(['status' => 'waiting', 'id' => 'x']),
            '*/user/templates' => Http::response(['data' => []]),
        ]);
    }

    private function send(?string $text = null): SmsMessage
    {
        return app(SmsService::class)->queue($this->branch, '+998 90 123 4567', $text ?? $this->approvedSmsText(), 'payment_received')->fresh();
    }

    private function hook(array $data, ?string $secret = self::SECRET, ?int $ts = null)
    {
        $body = json_encode(['event' => 'sms.'.$data['status'], 'data' => $data]);
        $ts ??= now()->timestamp;
        $sig = 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $secret ?? 'x');

        return $this->call('POST', '/api/sms-gateway/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SMS_TIMESTAMP' => (string) $ts, 'HTTP_X_SMS_SIGNATURE' => $sig, 'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    private function accepted(): SmsMessage
    {
        $this->fake(['*/api/v1/sms/send' => Http::response(['id' => 'g-1', 'status' => 'queued'], 202)]);

        return $this->send();
    }

    public function test_accepted_by_gateway_stays_queued_and_counts_usage_once(): void
    {
        $sms = $this->accepted();

        $this->assertSame('queued', $sms->status);
        $this->assertSame('gateway', $sms->channel);
        $this->assertSame('g-1', $sms->gateway_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v1/sms/send') && $r['external_id'] === 'eduziyo-'.$sms->id && $r['to'] === '+998901234567');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/message/sms/send'));
    }

    public function test_gateway_errors_fall_back_to_eskiz(): void
    {
        foreach ([Http::response(['error' => ['code' => 'no_modem', 'message' => 'x']], 503), Http::response([], 500), Http::response(['error' => ['code' => 'unauthorized']], 401)] as $resp) {
            $this->fake(['*/api/v1/sms/send' => $resp]);
            $sms = $this->send();
            $this->assertSame('sent', $sms->status);
            $this->assertSame('eskiz', $sms->channel);
            $this->assertStringContainsString('Gateway', $sms->provider_response);
        }
    }

    public function test_connection_failure_falls_back_to_eskiz(): void
    {
        $this->fake(['*/api/v1/sms/send' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);
        $this->assertSame('eskiz', $this->send()->channel);
    }

    public function test_validation_errors_do_not_fall_back(): void
    {
        $this->fake(['*/api/v1/sms/send' => Http::response(['error' => ['code' => 'invalid_phone', 'message' => 'bad']], 422)]);
        $sms = $this->send();
        $this->assertSame('failed', $sms->status);
        $this->assertSame('gateway', $sms->channel);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/message/sms/send'));
    }

    public function test_no_gateway_key_uses_eskiz(): void
    {
        S::set('gateway_key', null);
        $this->fake([]);
        $this->assertSame('eskiz', $this->send()->channel);
    }

    public function test_webhook_sent_marks_sent_and_clears_secret_text(): void
    {
        $sms = $this->accepted();
        $this->hook(['id' => 'g-1', 'external_id' => 'eduziyo-'.$sms->id, 'status' => 'sent'])->assertOk();

        $sms->refresh();
        $this->assertSame('sent', $sms->status);
        $this->assertNotNull($sms->sent_at);
        $this->assertSame('gateway', $sms->channel);
    }

    public function test_webhook_failed_falls_back_to_eskiz_once_without_double_usage(): void
    {
        $sms = $this->accepted();
        $before = app(UsageService::class)->used($this->center(), 'sms');
        $payload = ['id' => 'g-1', 'external_id' => 'eduziyo-'.$sms->id, 'status' => 'failed', 'error' => 'x'];

        $this->fake([]);
        $this->hook($payload)->assertOk();
        $this->hook($payload)->assertOk();   // takroriy webhook

        $sms->refresh();
        $this->assertSame('sent', $sms->status);
        $this->assertSame('eskiz', $sms->channel);
        $this->assertSame($before, app(UsageService::class)->used($this->center(), 'sms'));
        Http::assertSentCount(count(Http::recorded()));
        $this->assertCount(1, Http::recorded(fn ($r) => str_contains($r->url(), '/message/sms/send')));
    }

    public function test_webhook_failed_with_unapproved_text_does_not_go_to_eskiz(): void
    {
        $sms = $this->accepted();
        SmsPlatformTemplate::where('key', 'payment_received')->update(['is_approved' => false]);
        $this->fake([]);
        $this->hook(['id' => 'g-1', 'external_id' => 'eduziyo-'.$sms->id, 'status' => 'expired'])->assertOk();

        $this->assertSame('failed', $sms->fresh()->status);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/message/sms/send'));
    }

    public function test_webhook_cancelled_fails_without_fallback(): void
    {
        $sms = $this->accepted();
        $this->fake([]);
        $this->hook(['id' => 'g-1', 'external_id' => 'eduziyo-'.$sms->id, 'status' => 'cancelled'])->assertOk();
        $this->assertSame('failed', $sms->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_webhook_signature_and_timestamp_are_enforced(): void
    {
        $sms = $this->accepted();
        $p = ['id' => 'g-1', 'external_id' => 'eduziyo-'.$sms->id, 'status' => 'sent'];

        $this->hook($p, 'wrong')->assertForbidden();
        $this->hook($p, self::SECRET, now()->subHour()->timestamp)->assertForbidden();
        $this->assertSame('queued', $sms->fresh()->status);

        S::set('gateway_secret', null);
        $this->hook($p)->assertForbidden();   // sir sozlanmagan
    }

    public function test_unknown_message_webhook_returns_ok(): void
    {
        $this->hook(['id' => 'zz', 'external_id' => 'eduziyo-99999', 'status' => 'sent'])->assertOk();
        $this->hook(['id' => 'zz', 'external_id' => 'other-1', 'status' => 'sent'])->assertOk();
    }

    public function test_sync_command_applies_final_status_and_falls_back_for_missing_or_stuck(): void
    {
        $a = $this->accepted();
        $b = $this->accepted();
        $b->update(['gateway_id' => 'g-2']);
        Carbon::setTestNow(now()->addMinutes(5));

        $this->fake([
            '*/api/v1/sms/g-1' => Http::response(['id' => 'g-1', 'status' => 'sent']),
            '*/api/v1/sms/g-2' => Http::response([], 404),
        ]);
        Artisan::call('sms:gateway-sync');

        $this->assertSame('sent', $a->fresh()->status);
        $this->assertSame('gateway', $a->fresh()->channel);
        $this->assertSame('eskiz', $b->fresh()->channel);   // gateway topmadi → Eskiz
        $this->assertSame('sent', $b->fresh()->status);

        // hali navbatda turgan yangi xabarga tegilmaydi
        $c = $this->accepted();
        Artisan::call('sms:gateway-sync');
        $this->assertSame('queued', $c->fresh()->status);
    }
}
