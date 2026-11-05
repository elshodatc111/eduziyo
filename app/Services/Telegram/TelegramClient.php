<?php

namespace App\Services\Telegram;

use App\Support\IntegrationSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Telegram Bot API bilan ishlovchi yupqa klient. Token sozlanmagan bo'lsa hech narsa yubormaydi. */
class TelegramClient
{
    public function configured(): bool
    {
        return filled(IntegrationSettings::get('telegram_token'));
    }

    public function username(): ?string
    {
        return IntegrationSettings::get('telegram_username') ?: null;
    }

    /** t.me chuqur havola (start parametri bilan). */
    public function deepLink(string $payload): ?string
    {
        return $this->username() ? 'https://t.me/'.ltrim($this->username(), '@').'?start='.$payload : null;
    }

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): bool
    {
        $body = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if ($replyMarkup) {
            $body['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->call('sendMessage', $body);
    }

    public function sendDocument(int|string $chatId, string $path, string $filename, ?string $caption = null): bool
    {
        if (! $this->configured()) {
            return false;
        }

        try {
            $res = Http::timeout(300)->attach('document', fopen($path, 'r'), $filename)
                ->post($this->url('sendDocument'), array_filter(['chat_id' => $chatId, 'caption' => $caption]));

            if (! $res->successful()) {
                Log::warning('Telegram sendDocument xatosi: '.$res->status());
            }

            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram sendDocument istisno: '.$e->getMessage());

            return false;
        }
    }

    public function setWebhook(string $url, string $secret): bool
    {
        return $this->call('setWebhook', ['url' => $url, 'secret_token' => $secret, 'allowed_updates' => json_encode(['message'])]);
    }

    /**
     * Bot ma'lumotini oladi (token to'g'riligini tekshiradi). @return array{ok:bool, username?:string, name?:string, error?:string}
     */
    public function me(): array
    {
        return $this->query('getMe', fn ($r) => ['username' => $r['username'] ?? null, 'name' => $r['first_name'] ?? null]);
    }

    /** Webhook holati. @return array{ok:bool, url?:string, pending?:int, last_error?:?string, error?:string} */
    public function webhookInfo(): array
    {
        return $this->query('getWebhookInfo', fn ($r) => ['url' => $r['url'] ?? '', 'pending' => (int) ($r['pending_update_count'] ?? 0), 'last_error' => $r['last_error_message'] ?? null]);
    }

    public function deleteWebhook(): bool
    {
        return $this->call('deleteWebhook', []);
    }

    private function query(string $method, callable $map): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'error' => 'Bot tokeni kiritilmagan.'];
        }

        try {
            $res = Http::timeout(15)->get($this->url($method));
            $json = $res->json();

            if (! $res->successful() || empty($json['ok'])) {
                return ['ok' => false, 'error' => (string) ($json['description'] ?? 'Telegram javob bermadi ('.$res->status().')')];
            }

            return ['ok' => true] + $map($json['result'] ?? []);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => "Telegram'ga ulanib bo'lmadi: ".$e->getMessage()];
        }
    }

    private function call(string $method, array $body): bool
    {
        if (! $this->configured()) {
            return false;
        }

        try {
            $res = Http::timeout(15)->asForm()->post($this->url($method), $body);

            if (! $res->successful()) {
                Log::warning("Telegram {$method} xatosi: ".$res->status());
            }

            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning("Telegram {$method} istisno: ".$e->getMessage());

            return false;
        }
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.IntegrationSettings::get('telegram_token').'/'.$method;
    }
}
