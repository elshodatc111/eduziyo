<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Telegram webhook: faqat maxfiy sarlavha to'g'ri bo'lsa qabul qilinadi. */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBot $bot): JsonResponse
    {
        $secret = (string) \App\Support\IntegrationSettings::get('telegram_webhook_secret');

        abort_unless($secret !== '' && hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        try {
            $bot->handle($request->all());
        } catch (\Throwable $e) {
            Log::error('Telegram bot xatosi: '.$e->getMessage());
        }

        // Telegram qayta-qayta yubormasligi uchun doim 200
        return response()->json(['ok' => true]);
    }
}
