<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PlatformSetting;
use App\Models\SmsModem;
use App\Services\Sms\Modem\ModemWorker;
use App\Services\Sms\Modem\PortFactory;
use App\Services\SmsService;
use App\Support\IntegrationSettings as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeModemPorts;
use Tests\TestCase;

/** sAdmin «Integratsiyalar» sahifasi: Telegram bot, webhook, modem parametrlari (.env o'rniga). */
class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456789:AAFakeTokenValue_1234567890abcdef';

    private function sadmin()
    {
        return $this->user(Role::SAdmin, null);
    }

    public function test_only_sadmin_sees_page(): void
    {
        $b = $this->branch('A');
        $this->actingAs($this->user(Role::Admin, $b))->get('/platform/integratsiyalar')->assertForbidden();
        $this->actingAs($this->sadmin())->get('/platform/integratsiyalar')->assertOk()->assertSee('Integratsiyalar');
        $this->actingAs($this->user(Role::Admin, $b))->put('/platform/integratsiyalar/modem', [])->assertForbidden();
    }

    public function test_token_is_verified_encrypted_masked_and_overrides_env(): void
    {
        config(['services.telegram.token' => '999999:ENVTOKENENVTOKENENVTOKEN']);
        Http::fake(['api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'eduziyo_bot', 'first_name' => 'Eduziyo']])]);
        $this->actingAs($this->sadmin());

        $this->put('/platform/integratsiyalar/telegram', ['token' => self::TOKEN, 'webhook_url' => 'https://crm.example.uz/api/telegram/webhook'])->assertSessionHasNoErrors();

        $this->assertSame(self::TOKEN, S::get('telegram_token'));                      // .env dan ustun
        $this->assertSame('eduziyo_bot', S::get('telegram_username'));                  // getMe dan avtomatik
        $raw = PlatformSetting::where('key', 'int_telegram_token')->value('value');
        $this->assertStringNotContainsString('AAFake', $raw);                           // bazada shifrlangan
        $this->assertNotEmpty(S::get('telegram_webhook_secret'));                       // kalit avtomatik yaratildi

        $html = $this->get('/platform/integratsiyalar')->assertOk()->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $html);
        $this->assertStringContainsString('••••cdef', $html);
    }

    public function test_invalid_token_is_rejected_and_old_one_kept(): void
    {
        S::set('telegram_token', self::TOKEN);
        Http::fake(['api.telegram.org/*/getMe' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);
        $this->actingAs($this->sadmin());

        $this->put('/platform/integratsiyalar/telegram', ['token' => '111111:BADTOKENBADTOKENBADTOKEN'])->assertSessionHasErrors('token');
        $this->assertSame(self::TOKEN, S::get('telegram_token'));

        $this->put('/platform/integratsiyalar/telegram', ['token' => 'abc'])->assertSessionHasErrors('token');          // format
        $this->put('/platform/integratsiyalar/telegram', ['webhook_url' => 'http://x.uz/h'])->assertSessionHasErrors('webhook_url');   // faqat https
    }

    public function test_blank_token_keeps_saved_one(): void
    {
        S::set('telegram_token', self::TOKEN);
        $this->actingAs($this->sadmin())->put('/platform/integratsiyalar/telegram', ['token' => '', 'username' => '@my_bot'])->assertSessionHasNoErrors();
        $this->assertSame(self::TOKEN, S::get('telegram_token'));
        $this->assertSame('my_bot', S::get('telegram_username'));
    }

    public function test_set_check_and_remove_webhook(): void
    {
        S::set('telegram_token', self::TOKEN);
        S::set('telegram_webhook_url', 'https://crm.example.uz/api/telegram/webhook');
        Http::fake([
            'api.telegram.org/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'b', 'first_name' => 'B']]),
            'api.telegram.org/*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => 'https://crm.example.uz/api/telegram/webhook', 'pending_update_count' => 2]]),
            'api.telegram.org/*/deleteWebhook' => Http::response(['ok' => true, 'result' => true]),
        ]);
        $this->actingAs($this->sadmin());

        $this->post('/platform/integratsiyalar/webhook')->assertRedirect()->assertSessionHas('success');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'setWebhook') && $r['url'] === 'https://crm.example.uz/api/telegram/webhook' && $r['secret_token'] === S::get('telegram_webhook_secret'));

        $this->get('/platform/integratsiyalar?tekshirish=1')->assertOk()->assertSee('Token to\'g\'ri', false)->assertSee('kutilayotgan: 2');

        $this->delete('/platform/integratsiyalar/webhook')->assertSessionHas('success');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'deleteWebhook'));
    }

    public function test_webhook_requires_https_and_token(): void
    {
        Http::fake();
        $this->actingAs($this->sadmin());
        $this->post('/platform/integratsiyalar/webhook')->assertSessionHas('error');         // token yo'q
        S::set('telegram_token', self::TOKEN);
        config(['app.url' => 'http://localhost']);
        $this->post('/platform/integratsiyalar/webhook')->assertSessionHas('error');         // http
        Http::assertNothingSent();
    }

    public function test_webhook_controller_uses_secret_from_settings(): void
    {
        S::set('telegram_token', self::TOKEN);
        S::set('telegram_webhook_secret', 'sirkalit123');
        Http::fake();
        $payload = ['update_id' => 1, 'message' => ['chat' => ['id' => 5], 'text' => 'salom', 'from' => ['id' => 5]]];
        $this->postJson('/api/telegram/webhook', $payload)->assertForbidden();
        $this->postJson('/api/telegram/webhook', $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'sirkalit123'])->assertOk();
    }

    public function test_clear_removes_saved_token_and_falls_back_to_env(): void
    {
        config(['services.telegram.token' => '999999:ENVTOKENENVTOKENENVTOKEN']);
        S::set('telegram_token', self::TOKEN);
        $this->actingAs($this->sadmin())->delete('/platform/integratsiyalar/telegram')->assertSessionHas('success');
        $this->assertSame('999999:ENVTOKENENVTOKENENVTOKEN', S::get('telegram_token'));
    }

    public function test_modem_settings_validation_and_ports(): void
    {
        $this->actingAs($this->sadmin());
        $ok = ['ports' => '/dev/ttyUSB*, /dev/ttyACM*', 'baud' => 9600, 'pause_min' => 2, 'pause_max' => 5, 'daily_limit' => 40];
        $this->put('/platform/integratsiyalar/modem', $ok)->assertSessionHasNoErrors();
        $this->assertSame(9600, S::int('modem_baud'));
        $this->assertSame(40, S::int('modem_daily_limit'));

        $this->put('/platform/integratsiyalar/modem', ['ports' => '/etc/passwd'] + $ok)->assertSessionHasErrors('ports');
        $this->put('/platform/integratsiyalar/modem', ['ports' => '/dev/../etc/x'] + $ok)->assertSessionHasErrors('ports');
        $this->put('/platform/integratsiyalar/modem', ['baud' => 1234] + $ok)->assertSessionHasErrors('baud');
        $this->put('/platform/integratsiyalar/modem', ['pause_min' => 9, 'pause_max' => 3] + $ok)->assertSessionHasErrors('pause_max');
        $this->assertSame(9600, S::int('modem_baud'));   // xato so'rovlar o'zgartirmadi

        $this->assertSame([], array_filter(S::modemPorts(), fn ($p) => ! str_starts_with($p, '/dev/')));
    }

    public function test_daily_limit_stops_modem_and_pause_is_used(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        Cache::flush();
        config(['services.eskiz.email' => '', 'services.eskiz.password' => '']);
        Http::fake();
        FakeModemPorts::$sent = [];
        $ports = new FakeModemPorts;
        $ports->devices = ['/dev/ttyUSB1' => FakeModemPorts::modem('860000000000001')];
        $this->app->instance(PortFactory::class, $ports);
        $branch = $this->branch('T');
        $branch->update(['sms_enabled' => true]);

        S::set('modem_daily_limit', '2');
        S::set('modem_pause_min', '4');
        S::set('modem_pause_max', '4');
        $pauses = [];
        ModemWorker::$pauser = function (int $s) use (&$pauses) { $pauses[] = $s; };

        $worker = app(ModemWorker::class);
        $worker->tick(true);
        foreach (['1', '2', '3'] as $t) {
            app(SmsService::class)->queue($branch, '+998 90 123 4567', 'Xabar '.$t);
        }

        $r = $worker->tick();
        $this->assertSame(2, $r['sent']);                              // 3-xabar chegaradan oshdi
        $this->assertSame([4], $pauses);                                // faqat xabarlar orasida (oxirgisidan keyin emas)
        $m = SmsModem::first();
        $this->assertSame(2, $m->daily_sent);
        $this->assertSame(0, $m->remainingToday());

        // ertasi kuni hisob yangilanadi
        Carbon::setTestNow('2026-11-06 09:00:00');
        $this->assertGreaterThan(0, $m->fresh()->remainingToday() ?? 1);
    }
}
