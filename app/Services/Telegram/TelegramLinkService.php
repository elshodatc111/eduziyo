<?php

namespace App\Services\Telegram;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Foydalanuvchini Telegram chat'iga ulash (bir martalik token bilan yoki kontakt orqali). */
class TelegramLinkService
{
    public const TTL_MINUTES = 15;

    public function chatOf(User|int $user): ?int
    {
        $id = $user instanceof User ? $user->id : $user;
        $chat = DB::table('telegram_links')->where('user_id', $id)->value('chat_id');

        return $chat ? (int) $chat : null;
    }

    public function userOfChat(int $chatId): ?User
    {
        $id = DB::table('telegram_links')->where('chat_id', $chatId)->value('user_id');

        return $id ? User::find($id) : null;
    }

    /** Bir martalik ulash tokeni (15 daqiqa). $minutes ni uzaytirish mumkin (direktor birinchi kirishi uchun). */
    public function makeToken(User $user, string $purpose = 'link', int $minutes = self::TTL_MINUTES): string
    {
        $token = Str::lower(Str::random(24));
        Cache::put("tg:{$purpose}:{$token}", $user->id, now()->addMinutes($minutes));

        return $token;
    }

    /** Tokenni iste'mol qiladi va foydalanuvchini qaytaradi (bir marta ishlaydi). */
    public function consumeToken(string $token, string $purpose = 'link'): ?User
    {
        $id = Cache::pull("tg:{$purpose}:{$token}");

        return $id ? User::find($id) : null;
    }

    public function link(User $user, int $chatId, ?string $username = null, string $how = 'token'): void
    {
        DB::transaction(function () use ($user, $chatId, $username) {
            // Bitta chat faqat bitta foydalanuvchiga, bitta foydalanuvchi faqat bitta chat'ga ulanadi
            DB::table('telegram_links')->where('chat_id', $chatId)->orWhere('user_id', $user->id)->delete();
            DB::table('telegram_links')->insert([
                'user_id' => $user->id, 'chat_id' => $chatId, 'tg_username' => $username,
                'linked_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        AuditLog::record('telegram.linked', $user, "Telegram ulandi ({$how}): {$user->name}", [], [], $user->branch_id);
    }

    public function unlink(User $user): void
    {
        DB::table('telegram_links')->where('user_id', $user->id)->delete();
        AuditLog::record('telegram.unlinked', $user, "Telegram uzildi: {$user->name}", [], [], $user->branch_id);
    }
}
