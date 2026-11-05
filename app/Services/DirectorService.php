<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Center;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramLinkService;
use App\Support\Format;
use DomainException;
use Illuminate\Support\Str;

/** Markazga direktor (hammuassis) qo'shish va bloklash. Faqat sAdmin va shu markaz direktori chaqiradi (controller'da tekshiriladi). */
class DirectorService
{
    public function directors(Center $center)
    {
        return User::where('center_id', $center->id)->where('role', Role::Owner)->orderBy('id')->get();
    }

    /** @return array{user: User, link: ?string, password: ?string} Telegram bo'lsa havola, bo'lmasa bir martalik parol. */
    public function add(Center $center, string $name, string $phone, User $actor): array
    {
        $phone = Format::canonicalPhone($phone) ?? $phone;

        if (User::where('role', Role::Owner)->where('phone', $phone)->exists()) {
            throw new DomainException('Bu telefon bilan direktor allaqachon mavjud.');
        }

        $password = Str::lower(Str::random(10));
        $base = Str::slug($center->code, '').'.direktor';
        $username = $base;
        for ($i = 2; User::where('username', $username)->exists(); $i++) {
            $username = $base.$i;
        }

        $user = User::create([
            'center_id' => $center->id, 'role' => Role::Owner, 'name' => $name, 'username' => $username,
            'phone' => $phone, 'password' => $password, 'created_by' => $actor->id,
        ]);

        AuditLog::record('director.created', $user, "{$center->name}: yangi direktor qo'shildi ({$name})");

        $link = app(TelegramClient::class)->deepLink('own_'.app(TelegramLinkService::class)->makeToken($user, 'own', 60 * 24 * 7));

        return ['user' => $user, 'link' => $link, 'password' => $link ? null : $password];
    }

    public function setBlocked(Center $center, User $director, bool $blocked, User $actor): void
    {
        if ($director->center_id !== $center->id || $director->role !== Role::Owner) {
            throw new DomainException('Direktor topilmadi.');
        }
        if ($blocked && $director->id === $actor->id) {
            throw new DomainException("O'zingizni bloklab bo'lmaydi.");
        }
        if ($blocked && $this->directors($center)->where('status', UserStatus::Active)->where('id', '!=', $director->id)->isEmpty()) {
            throw new DomainException("Markazda kamida bitta faol direktor qolishi kerak.");
        }

        $director->update(['status' => $blocked ? UserStatus::Blocked : UserStatus::Active]);
        AuditLog::record($blocked ? 'director.blocked' : 'director.unblocked', $director, $director->name);
    }
}
