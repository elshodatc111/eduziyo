<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;

class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {url? : To\'liq webhook manzili (standart: APP_URL/api/telegram/webhook)}';

    protected $description = "Telegram botning webhook manzilini maxfiy sarlavha bilan o'rnatadi";

    public function handle(TelegramClient $tg): int
    {
        $secret = (string) \App\Support\IntegrationSettings::get('telegram_webhook_secret');

        if (! $tg->configured() || $secret === '') {
            $this->error('Bot tokeni va webhook kaliti kerak (sAdmin → «Integratsiyalar» sahifasida yoki .env da).');

            return self::FAILURE;
        }

        $url = $this->argument('url') ?: rtrim((string) config('app.url'), '/').'/api/telegram/webhook';
        $ok = $tg->setWebhook($url, $secret);
        $ok ? $this->info("Webhook o'rnatildi: {$url}") : $this->error("Webhook o'rnatilmadi (token yoki manzilni tekshiring).");

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
