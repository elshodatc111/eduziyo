<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PlatformSetting;
use App\Support\IntegrationSettings as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** sAdmin «Integratsiyalar» sahifasi: Telegram bot, webhook, SMS Gateway (.env o'rniga). */
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
        $this->actingAs($this->user(Role::Admin, $b))->put('/platform/integratsiyalar/gateway', [])->assertForbidden();
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

    public function test_gateway_settings_validation_encryption_and_check(): void
    {
        $this->actingAs($this->sadmin());
        $key = 'sk_sms_'.str_repeat('a', 24);
        $this->put('/platform/integratsiyalar/gateway', ['url' => 'http://x.uz', 'key' => $key])->assertSessionHasErrors('url');
        $this->put('/platform/integratsiyalar/gateway', ['url' => 'https://sms.elshod.date', 'key' => 'bad'])->assertSessionHasErrors('key');
        $this->put('/platform/integratsiyalar/gateway', ['url' => 'https://sms.elshod.date/', 'key' => $key, 'secret' => 'whsec_1'])->assertSessionHasNoErrors();

        $this->assertSame('https://sms.elshod.date', S::get('gateway_url'));
        $this->assertSame($key, S::get('gateway_key'));
        $this->assertNotSame($key, PlatformSetting::value('int_gateway_key'));   // shifrlangan
        $this->get('/platform/integratsiyalar')->assertOk()->assertDontSee($key);

        // bo'sh kalit eskisini o'chirmaydi
        $this->put('/platform/integratsiyalar/gateway', ['url' => 'https://sms.elshod.date', 'key' => ''])->assertSessionHasNoErrors();
        $this->assertSame($key, S::get('gateway_key'));

        Http::fake(['*/api/v1/health' => Http::response(['ok' => true]), '*/api/v1/me/usage' => Http::response(['used' => 1])]);
        $this->post('/platform/integratsiyalar/gateway/test')->assertSessionHas('success');
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*/api/v1/health' => Http::response(['ok' => true]), '*/api/v1/me/usage' => Http::response([], 401)]);
        $this->post('/platform/integratsiyalar/gateway/test')->assertSessionHas('error');
    }
}
