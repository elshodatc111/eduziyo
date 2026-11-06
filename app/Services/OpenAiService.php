<?php

namespace App\Services;

use App\Models\AiMessage;
use App\Models\User;
use App\Support\IntegrationSettings as S;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * AI provayderlar: OpenAI (Chat Completions) va Claude (Anthropic Messages). Kalitlar/modellar sAdmin «Integratsiyalar»
 * sahifasida saqlanadi. Asosiy provayder sAdmin tanlaydi; u xato bersa (kalit noto'g'ri, mablag' tugagan, 429, 5xx, aloqa yo'q)
 * so'rov avtomatik ikkinchisiga o'tadi. Tashqariga har doim OpenAI uslubidagi xabar qaytadi (AiAnalystService tool_calls bilan ishlaydi).
 */
class OpenAiService
{
    public const PROVIDERS = ['openai' => 'OpenAI (ChatGPT)', 'anthropic' => 'Claude (Anthropic)'];

    /** Oxirgi muvaffaqiyatli javobni qaysi provayder berdi. */
    public ?string $lastProvider = null;

    public function configured(): bool
    {
        return $this->providers() !== [];
    }

    /** Kaliti bor provayderlar, asosiysi birinchi. @return list<string> */
    public function providers(): array
    {
        $primary = S::get('ai_primary') === 'anthropic' ? 'anthropic' : 'openai';
        $order = $primary === 'openai' ? ['openai', 'anthropic'] : ['anthropic', 'openai'];

        return array_values(array_filter($order, fn ($p) => filled(S::get($p.'_key'))));
    }

    /**
     * Foydalanuvchining shu filialdagi bugungi savollari limitdan oshmaganini tekshiradi.
     *
     * @throws ValidationException
     */
    public function assertQuota(User $user, ?int $branchId): void
    {
        $limit = (int) config('services.openai.daily_limit');

        $used = AiMessage::where('role', 'user')->whereDate('created_at', today())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId), fn ($q) => $q->where('user_id', $user->id))
            ->count();

        if ($limit > 0 && $used >= $limit) {
            throw ValidationException::withMessages(['message' => "AI so'rovlari kunlik limitiga ({$limit}) yetdi. Ertaga urinib ko'ring."]);
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $messages
     * @param  array<int,array<string,mixed>>  $tools
     * @return array<string,mixed> javob xabari (content yoki tool_calls)
     */
    public function chat(array $messages, array $tools = [], bool $json = false): array
    {
        if (app(DemoService::class)->isDemoUser(auth()->user())) {
            throw ValidationException::withMessages(['message' => "Demo rejimda AI o'chirilgan."]);
        }

        $providers = $this->providers();
        if ($providers === []) {
            throw ValidationException::withMessages(['message' => "AI kaliti sozlanmagan. Platforma egasi (sAdmin) «Integratsiyalar» sahifasida OpenAI yoki Claude kalitini kiritishi kerak."]);
        }

        $aiUser = auth()->user();
        $aiCenter = null;
        if ($aiUser && ! $aiUser->isSuperAdmin() && $aiUser->center_id && ($aiCenter = \App\Models\Center::find($aiUser->center_id))
            && ! app(UsageService::class)->consume($aiCenter, 'ai')) {
            throw ValidationException::withMessages(['message' => "AI so'rovlar limiti tugagan yoki tarifingizda AI yo'q. Direktor «Markaz» bo'limidan tarifni ko'rsin."]);
        }

        $lastError = null;
        foreach ($providers as $i => $provider) {
            $result = $provider === 'anthropic' ? $this->callAnthropic($messages, $tools, $json) : $this->callOpenAi($messages, $tools, $json);

            if (is_array($result)) {
                $this->lastProvider = $provider;
                if ($i > 0) {
                    \Illuminate\Support\Facades\Log::warning("AI zaxira provayderga o'tdi: {$provider}");
                }

                return $result;
            }

            $lastError = $result;   // xato matni
            $this->alertFailure($provider, $result);
        }

        // Hech bir provayder javob bermadi: markazning oylik hisobi behuda sarflanmasin
        if ($aiCenter) {
            app(UsageService::class)->refund($aiCenter, 'ai');
        }

        throw ValidationException::withMessages(['message' => count($providers) > 1
            ? "AI xizmatlari (OpenAI va Claude) hozir javob bermadi. Keyinroq urinib ko'ring."
            : (string) $lastError]);
    }

    /** Bitta provayderni sinash (markaz limitini sarflamaydi). true yoki xato matni. */
    public function probe(string $provider): true|string
    {
        $msgs = [['role' => 'user', 'content' => 'Ping. Faqat "ok" deb javob ber.']];
        $r = $provider === 'anthropic' ? $this->callAnthropic($msgs, [], false) : $this->callOpenAi($msgs, [], false);

        return is_array($r) ? true : $r;
    }

    /** @return array<string,mixed>|string  javob xabari yoki xato matni */
    private function callOpenAi(array $messages, array $tools, bool $json): array|string
    {
        $payload = ['model' => S::get('openai_model') ?: 'gpt-4.1-mini', 'messages' => $messages, 'temperature' => 0.3];
        if ($tools) {
            $payload['tools'] = $tools;
        }
        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken((string) S::get('openai_key'))->timeout(90)
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/chat/completions', $payload);
        } catch (ConnectionException) {
            return "OpenAI bilan aloqa o'rnatilmadi. Internetni tekshirib, qayta urinib ko'ring.";
        }

        if (! $response->successful()) {
            report(new \RuntimeException('OpenAI xatosi: '.$response->status().' '.mb_substr($response->body(), 0, 300)));

            return $this->httpError('OpenAI', $response->status());
        }

        return $response->json('choices.0.message') ?? "OpenAI bo'sh javob qaytardi.";
    }

    /** @return array<string,mixed>|string */
    private function callAnthropic(array $messages, array $tools, bool $json): array|string
    {
        [$system, $chat] = $this->toAnthropicMessages($messages);
        if ($json) {
            $system .= "\n\nJavobni FAQAT bitta to'g'ri JSON obyekt sifatida qaytar, boshqa matn yozma.";
        }

        $payload = ['model' => S::get('anthropic_model') ?: 'claude-sonnet-4-5', 'max_tokens' => 4096, 'temperature' => 0.3, 'messages' => $chat];
        if (trim($system) !== '') {
            $payload['system'] = trim($system);
        }
        if ($tools) {
            $payload['tools'] = array_map(fn ($t) => [
                'name' => $t['function']['name'],
                'description' => $t['function']['description'] ?? '',
                'input_schema' => $t['function']['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass],
            ], $tools);
        }

        try {
            $response = Http::withHeaders(['x-api-key' => (string) S::get('anthropic_key'), 'anthropic-version' => '2023-06-01'])->timeout(90)
                ->post(rtrim((string) config('services.anthropic.base_url'), '/').'/messages', $payload);
        } catch (ConnectionException) {
            return "Claude bilan aloqa o'rnatilmadi. Internetni tekshirib, qayta urinib ko'ring.";
        }

        if (! $response->successful()) {
            report(new \RuntimeException('Claude xatosi: '.$response->status().' '.mb_substr($response->body(), 0, 300)));

            return $this->httpError('Claude', $response->status());
        }

        $text = '';
        $calls = [];
        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $calls[] = ['id' => $block['id'], 'type' => 'function',
                    'function' => ['name' => $block['name'], 'arguments' => json_encode($block['input'] ?? new \stdClass, JSON_UNESCAPED_UNICODE)]];
            }
        }
        if ($text === '' && ! $calls) {
            return "Claude bo'sh javob qaytardi.";
        }

        $message = ['role' => 'assistant', 'content' => $text === '' ? null : $text];
        if ($calls) {
            $message['tool_calls'] = $calls;
        }

        return $message;
    }

    /**
     * OpenAI uslubidagi xabarlarni Anthropic formatiga o'giradi (system alohida, tool natijalari user xabari ichida,
     * ketma-ket bir xil rolli xabarlar birlashtiriladi).
     *
     * @return array{0:string,1:list<array<string,mixed>>}
     */
    private function toAnthropicMessages(array $messages): array
    {
        $system = [];
        $out = [];
        $push = function (string $role, array $blocks) use (&$out) {
            if ($out && end($out)['role'] === $role) {
                $out[array_key_last($out)]['content'] = array_merge($out[array_key_last($out)]['content'], $blocks);
            } else {
                $out[] = ['role' => $role, 'content' => $blocks];
            }
        };

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            if ($role === 'system') {
                $system[] = (string) ($m['content'] ?? '');
            } elseif ($role === 'tool') {
                $push('user', [['type' => 'tool_result', 'tool_use_id' => $m['tool_call_id'] ?? '', 'content' => (string) ($m['content'] ?? '')]]);
            } elseif ($role === 'assistant') {
                $blocks = [];
                if (filled($m['content'] ?? null)) {
                    $blocks[] = ['type' => 'text', 'text' => (string) $m['content']];
                }
                foreach ((array) ($m['tool_calls'] ?? []) as $c) {
                    $args = json_decode((string) ($c['function']['arguments'] ?? '{}'), true);
                    $blocks[] = ['type' => 'tool_use', 'id' => $c['id'], 'name' => $c['function']['name'], 'input' => $args ?: new \stdClass];
                }
                if ($blocks) {
                    $push('assistant', $blocks);
                }
            } else {
                $push('user', [['type' => 'text', 'text' => (string) ($m['content'] ?? '')]]);
            }
        }

        // Anthropic suhbat «user» bilan boshlanishini talab qiladi
        if ($out && $out[0]['role'] !== 'user') {
            array_unshift($out, ['role' => 'user', 'content' => [['type' => 'text', 'text' => '.']]]);
        }

        return [implode("\n\n", array_filter($system)), $out];
    }

    private function httpError(string $name, int $status): string
    {
        return match (true) {
            in_array($status, [401, 403], true) => "{$name} kaliti noto'g'ri yoki bekor qilingan.",
            in_array($status, [402, 429], true) => "{$name} hisobida mablag' yoki limit tugagan.",
            default => "{$name} xizmati javob bermadi (kod {$status}). Keyinroq urinib ko'ring.",
        };
    }

    /** Provayder xato berganda sAdminlarga Telegram'da xabar (6 soatda bir marta). */
    private function alertFailure(string $provider, string $error): void
    {
        try {
            if (\Illuminate\Support\Facades\Cache::add("ai:alert:{$provider}", 1, now()->addHours(6))) {
                app(\App\Services\Telegram\TelegramNotifier::class)->toSadmins(
                    "⚠️ <b>AI provayder xatosi</b>\n".e(self::PROVIDERS[$provider] ?? $provider).': '.e($error)
                    ."\nTizim zaxira provayderga o'tadi (kaliti bo'lsa). Tekshirish: ".route('platform.integrations')
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
