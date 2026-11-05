<?php

namespace App\Services\Telegram;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Parolni FAQAT Telegram bot orqali tiklash: login → bot bir martalik kod (10 daqiqa, 5 urinish) → yangi parol.
 * Yangi parol o'rnatilgach barcha sessiya va API tokenlar yopiladi.
 */
class TelegramPasswordReset
{
    public const TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const MAX_REQUESTS_PER_HOUR = 5;

    public function __construct(private TelegramLinkService $links, private TelegramClient $client) {}

    /** Kod yuboriladimi (faqat ichki; foydalanuvchiga baribir bir xil javob ko'rsatiladi). */
    public function request(string $login): bool
    {
        $user = User::where('username', trim($login))->where('status', 'active')->first();

        if (! $user || ! ($chat = $this->links->chatOf($user))) {
            return false;
        }

        $key = 'tg-reset:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS_PER_HOUR)) {
            return false;
        }
        RateLimiter::hit($key, 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('telegram_reset_codes')->where('user_id', $user->id)->delete();
        DB::table('telegram_reset_codes')->insert([
            'user_id' => $user->id, 'code_hash' => $this->hash($code), 'expires_at' => now()->addMinutes(self::TTL_MINUTES), 'attempts' => 0, 'created_at' => now(),
        ]);

        $this->client->sendMessage($chat, "🔐 Parolni tiklash kodi: <b>{$code}</b>\nKod ".self::TTL_MINUTES." daqiqa amal qiladi. Buni siz so'ramagan bo'lsangiz, e'tibor bermang va hech kimga aytmang.");

        return true;
    }

    public function reset(string $login, string $code, string $newPassword): User
    {
        $fail = fn () => ValidationException::withMessages(['code' => "Kod noto'g'ri yoki muddati o'tgan."]);

        $user = User::where('username', trim($login))->where('status', 'active')->first();
        $row = $user ? DB::table('telegram_reset_codes')->where('user_id', $user->id)->orderByDesc('id')->first() : null;

        if (! $row || now()->greaterThan($row->expires_at) || $row->attempts >= self::MAX_ATTEMPTS) {
            throw $fail();
        }

        if (! hash_equals($row->code_hash, $this->hash(trim($code)))) {
            DB::table('telegram_reset_codes')->where('id', $row->id)->increment('attempts');
            throw $fail();
        }

        DB::transaction(function () use ($user, $newPassword) {
            $user->update(['password' => $newPassword]);
            $user->tokens()->delete();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            DB::table('telegram_reset_codes')->where('user_id', $user->id)->delete();
        });

        AuditLog::record('auth.password_reset_telegram', $user, "Parol Telegram kodi orqali tiklandi: {$user->name}", [], [], $user->branch_id);

        if ($chat = $this->links->chatOf($user)) {
            $this->client->sendMessage($chat, "✅ Parolingiz o'zgartirildi. Barcha qurilmalardan chiqarildingiz. Bu siz bo'lmasangiz, darhol administratorga xabar bering.");
        }

        return $user;
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
