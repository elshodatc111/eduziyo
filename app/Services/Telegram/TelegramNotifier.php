<?php

namespace App\Services\Telegram;

use App\Enums\Role;
use App\Models\Center;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Foydalanuvchi, markaz direktori yoki sAdminlarga bot orqali xabar. Ulanmagan bo'lsa jim o'tadi (panel xabari baribir bor). */
class TelegramNotifier
{
    public function __construct(private TelegramClient $client) {}

    public function toUser(User|int $user, string $text): bool
    {
        $id = $user instanceof User ? $user->id : $user;
        $chat = DB::table('telegram_links')->where('user_id', $id)->value('chat_id');

        return $chat ? $this->client->sendMessage($chat, $text) : false;
    }

    public function toCenterOwner(Center $center, string $text): bool
    {
        $owner = $center->owner_user_id ?: User::where('center_id', $center->id)->where('role', Role::Owner)->value('id');

        return $owner ? $this->toUser((int) $owner, $text) : false;
    }

    /** Barcha ulangan sAdminlarga; yuborilganlar soni qaytadi. */
    public function toSadmins(string $text): int
    {
        $sent = 0;
        foreach ($this->sadminChats() as $chat) {
            $sent += $this->client->sendMessage($chat, $text) ? 1 : 0;
        }

        return $sent;
    }

    /** Barcha sAdminlarga fayl (masalan, to'lov cheki). */
    public function documentToSadmins(string $path, string $filename, ?string $caption = null): int
    {
        $sent = 0;
        foreach ($this->sadminChats() as $chat) {
            $sent += $this->client->sendDocument($chat, $path, $filename, $caption) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * Ulangan sAdminlar + «Integratsiyalar» sahifasida kiritilgan qo'shimcha ogohlantirish chat ID'lari (guruh yoki shaxsiy).
     *
     * @return list<int>
     */
    public function sadminChats(): array
    {
        $linked = DB::table('telegram_links')->join('users', 'users.id', '=', 'telegram_links.user_id')
            ->where('users.role', Role::SAdmin->value)->where('users.status', 'active')->pluck('telegram_links.chat_id')->map(fn ($c) => (int) $c)->all();

        $extra = array_map('intval', array_filter(array_map('trim', explode(',', (string) \App\Support\IntegrationSettings::get('telegram_alert_chats'))), fn ($v) => preg_match('/^-?\d{5,}$/', $v)));

        return array_values(array_unique(array_merge($linked, $extra)));
    }
}
