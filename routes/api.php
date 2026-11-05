<?php

use Illuminate\Support\Facades\Route;

// Mobil API olib tashlangan (EDUZIYO). Faqat Telegram bot webhook'i qoldi (maxfiy sarlavha bilan; sessiya/CSRF yo'q).
Route::post('telegram/webhook', \App\Http\Controllers\TelegramWebhookController::class)->middleware('throttle:120,1');
