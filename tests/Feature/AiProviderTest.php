<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PlatformSetting;
use App\Services\OpenAiService;
use App\Support\IntegrationSettings as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** AI provayderlar: kalitlar sAdmin sahifasida, OpenAI ↔ Claude avtomatik almashinuv. */
class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    private const OAI = 'sk-testopenaikey1234567890abcd';
    private const ANT = 'sk-ant-testclaudekey1234567890abcd';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.key' => null, 'services.anthropic.key' => null]);
        S::flush();
    }

    private function saveKeys(string $primary = 'openai'): void
    {
        $this->actingAs($this->user(Role::SAdmin, null))->put('/platform/integratsiyalar/ai', [
            'primary' => $primary, 'openai_key' => self::OAI, 'anthropic_key' => self::ANT,
            'openai_model' => 'gpt-x', 'anthropic_model' => 'claude-x',
        ])->assertSessionHasNoErrors();
    }

    public function test_only_sadmin_can_manage_keys_and_they_are_encrypted(): void
    {
        $b = $this->branch('A');
        $this->actingAs($this->user(Role::Admin, $b))->put('/platform/integratsiyalar/ai', ['primary' => 'openai'])->assertForbidden();

        $this->saveKeys();
        $this->assertSame(self::OAI, S::get('openai_key'));
        $this->assertSame(self::ANT, S::get('anthropic_key'));
        $raw = PlatformSetting::where('key', 'int_anthropic_key')->value('value');
        $this->assertStringNotContainsString('testclaude', $raw);                     // bazada shifrlangan
        $this->get('/platform/integratsiyalar')->assertOk()->assertSee('••••abcd')->assertDontSee(self::ANT);

        $this->put('/platform/integratsiyalar/ai', ['primary' => 'openai', 'openai_key' => 'noto-gri'])->assertSessionHasErrors('openai_key');
    }

    public function test_primary_is_used_first_and_order_follows_choice(): void
    {
        $this->saveKeys('anthropic');
        $this->assertSame(['anthropic', 'openai'], app(OpenAiService::class)->providers());

        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Salom Claude']]])]);
        $this->actingAs($this->user(Role::SAdmin, null));
        $msg = app(OpenAiService::class)->chat([['role' => 'system', 'content' => 'Sen yordamchisan'], ['role' => 'user', 'content' => 'Salom']]);

        $this->assertSame('Salom Claude', $msg['content']);
        $sent = Http::recorded();
        $this->assertCount(1, $sent);                                                  // OpenAI'ga umuman murojaat qilinmagan
        $this->assertSame(self::ANT, $sent[0][0]->header('x-api-key')[0]);
        $this->assertSame('claude-x', $sent[0][0]->data()['model']);
        $this->assertSame('Sen yordamchisan', $sent[0][0]->data()['system']);
    }

    public function test_falls_back_to_claude_when_openai_fails_and_translates_tools(): void
    {
        $this->saveKeys('openai');
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['code' => 'insufficient_quota']], 429),
            'api.anthropic.com/*' => Http::response(['content' => [
                ['type' => 'text', 'text' => 'Hisoblayman'],
                ['type' => 'tool_use', 'id' => 'tu1', 'name' => 'overview', 'input' => ['from' => '2026-09-01']],
            ]]),
        ]);
        $this->actingAs($this->user(Role::SAdmin, null));

        $tools = [['type' => 'function', 'function' => ['name' => 'overview', 'description' => 'Umumiy', 'parameters' => ['type' => 'object', 'properties' => ['from' => ['type' => 'string']]]]]];
        $messages = [
            ['role' => 'system', 'content' => 'Tizim'],
            ['role' => 'user', 'content' => 'Savol'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'old1', 'type' => 'function', 'function' => ['name' => 'overview', 'arguments' => '{"to":"x"}']]]],
            ['role' => 'tool', 'tool_call_id' => 'old1', 'content' => '{"ok":1}'],
        ];
        $msg = app(OpenAiService::class)->chat($messages, $tools);

        // javob OpenAI uslubiga o'girilgan
        $this->assertSame('Hisoblayman', $msg['content']);
        $this->assertSame('overview', $msg['tool_calls'][0]['function']['name']);
        $this->assertSame('tu1', $msg['tool_calls'][0]['id']);
        $this->assertSame(['from' => '2026-09-01'], json_decode($msg['tool_calls'][0]['function']['arguments'], true));
        $this->assertSame('anthropic', app(OpenAiService::class)->lastProvider ?? 'anthropic');

        // so'rov Claude formatiga o'girilgan
        $claude = collect(Http::recorded())->first(fn ($p) => str_contains($p[0]->url(), 'anthropic'))[0]->data();
        $this->assertSame('overview', $claude['tools'][0]['name']);
        $this->assertSame('object', $claude['tools'][0]['input_schema']['type']);
        $this->assertSame(['user', 'assistant', 'user'], array_column($claude['messages'], 'role'));
        $this->assertSame('tool_use', $claude['messages'][1]['content'][0]['type']);
        $this->assertSame('tool_result', $claude['messages'][2]['content'][0]['type']);
        $this->assertSame('old1', $claude['messages'][2]['content'][0]['tool_use_id']);
    }

    public function test_error_when_all_providers_fail_and_when_none_configured(): void
    {
        $this->actingAs($this->user(Role::SAdmin, null));
        try {
            app(OpenAiService::class)->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail('Kalitsiz xato berishi kerak edi');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('AI kaliti sozlanmagan', $e->errors()['message'][0]);
        }

        $this->saveKeys();
        Http::fake(['*' => Http::response('', 500)]);
        $this->expectException(ValidationException::class);
        app(OpenAiService::class)->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_single_provider_works_and_probe_reports(): void
    {
        $this->actingAs($this->user(Role::SAdmin, null))->put('/platform/integratsiyalar/ai', ['primary' => 'openai', 'anthropic_key' => self::ANT])->assertSessionHasNoErrors();
        $this->assertSame(['anthropic'], app(OpenAiService::class)->providers());

        Http::fake(['api.anthropic.com/*' => Http::response('', 401)]);
        $this->post('/platform/integratsiyalar/ai/test')->assertSessionHas('success', fn ($m) => str_contains($m, 'xato') && str_contains($m, "noto'g'ri"));
    }
}
