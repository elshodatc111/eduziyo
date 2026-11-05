<?php

namespace App\Services;

use App\Models\Center;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Telegram\TelegramNotifier;
use App\Support\WorkHours;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Qo'llab-quvvatlash: faqat direktor tiket ochadi, markazda bir vaqtda bitta ochiq tiket. sAdmin javob beradi.
 * SLA (ish soatida) o'tsa sAdmin'ga qayta eslatma; «mijoz javobi kutilmoqda»da 7 kun javob bo'lmasa avtomatik yopiladi.
 */
class SupportService
{
    public const AUTO_CLOSE_DAYS = 7;
    public const REMIND_EVERY_HOURS = 4;

    public function __construct(private TelegramNotifier $tg, private CenterAlerts $alerts) {}

    public function openTicket(Center $center): ?SupportTicket
    {
        return SupportTicket::withoutGlobalScopes()->where('center_id', $center->id)->where('status', '!=', SupportTicket::CLOSED)->first();
    }

    /** @param \Illuminate\Http\UploadedFile[] $files */
    public function open(Center $center, User $user, string $subject, string $body, array $files = []): SupportTicket
    {
        return DB::transaction(function () use ($center, $user, $subject, $body, $files) {
            Center::whereKey($center->id)->lockForUpdate()->first();   // bir vaqtda ikkita ochilmasin

            if ($this->openTicket($center)) {
                throw new DomainException("Sizda allaqachon ochiq murojaat bor. Avval uni yoping yoki javobini kuting.");
            }

            $t = SupportTicket::withoutGlobalScopes()->create([
                'center_id' => $center->id, 'opened_by' => $user->id, 'subject' => $subject, 'status' => SupportTicket::OPEN,
                'sla_due_at' => WorkHours::addHours(now(), WorkHours::config()['sla_hours']), 'last_customer_at' => now(),
            ]);
            $this->message($t, $user, $body, false, notify: false, files: $files);

            $this->tg->toSadmins("🎫 <b>Yangi murojaat</b>\nMarkaz: ".e($center->name)."\nMavzu: ".e($subject)."\n\n".e(mb_substr($body, 0, 300)).(count($files) ? "\n📎 Rasm: ".count($files).' ta' : '')."\n\n".route('platform.support.show', $t));

            return $t->refresh();
        });
    }

    /** Xabar qo'shadi. Mijoz yozsa — «javob kutilmoqda»ga qaytadi; sAdmin yozsa — «mijoz javobi kutilmoqda». */
    /** @param \Illuminate\Http\UploadedFile[] $files */
    public function reply(SupportTicket $ticket, User $user, string $body, array $files = []): SupportMessage
    {
        if ($ticket->isClosed()) {
            throw new DomainException('Murojaat yopilgan.');
        }

        return $this->message($ticket, $user, $body, $user->isSuperAdmin(), files: $files);
    }

    private function message(SupportTicket $ticket, User $user, string $body, bool $staff, bool $notify = true, array $files = []): SupportMessage
    {
        $m = SupportMessage::withoutGlobalScopes()->create(['ticket_id' => $ticket->id, 'center_id' => $ticket->center_id, 'user_id' => $user->id, 'is_staff' => $staff, 'body' => $body]);
        $this->attach($m, $files);

        if ($staff) {
            $ticket->update(['status' => SupportTicket::PENDING_CUSTOMER, 'last_staff_at' => now(), 'first_response_at' => $ticket->first_response_at ?? now()]);
            if ($notify && ($center = Center::find($ticket->center_id))) {
                $this->alerts->send($center, "Murojaatingizga javob keldi: «{$ticket->subject}». «Markaz → Murojaat» bo'limida ko'ring.");
            }
        } elseif ($notify) {
            $ticket->update(['status' => SupportTicket::OPEN, 'last_customer_at' => now(),
                'sla_due_at' => WorkHours::addHours(now(), WorkHours::config()['sla_hours']), 'sla_reminded_at' => null]);
            $this->tg->toSadmins("💬 <b>Murojaatga javob</b>\nMarkaz: ".e((string) Center::find($ticket->center_id)?->name)."\n«".e($ticket->subject)."»\n\n".e(mb_substr($body, 0, 300)).(count($files) ? "\n📎 Rasm: ".count($files).' ta' : '')."\n\n".route('platform.support.show', $ticket));
        }

        return $m;
    }

    public const MAX_FILES = 3;

    /** Rasmlarni yopiq diskka saqlaydi (asl fayl nomi faqat ko'rsatish uchun; diskda tasodifiy nom). */
    private function attach(SupportMessage $m, array $files): void
    {
        foreach (array_slice($files, 0, self::MAX_FILES) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store("support/{$m->center_id}/{$m->ticket_id}", 'local');
            \App\Models\SupportAttachment::withoutGlobalScopes()->create([
                'center_id' => $m->center_id, 'message_id' => $m->id, 'path' => $path,
                'original_name' => mb_substr(basename((string) $file->getClientOriginalName()), 0, 190),
                'mime' => $file->getMimeType() ?: 'image/png', 'size' => (int) $file->getSize(),
            ]);
        }
    }

    public function close(SupportTicket $ticket, string $reason = 'manual'): void
    {
        if ($ticket->isClosed()) {
            return;
        }
        $ticket->update(['status' => SupportTicket::CLOSED, 'closed_at' => now(), 'closed_reason' => $reason]);
    }

    public function rate(SupportTicket $ticket, int $rating, ?string $comment): void
    {
        if (! $ticket->isClosed()) {
            throw new DomainException("Baho faqat yopilgan murojaat uchun beriladi.");
        }
        if ($ticket->rating !== null) {
            throw new DomainException('Baho allaqachon berilgan.');
        }
        if ($rating < 1 || $rating > 5) {
            throw new DomainException("Baho 1 dan 5 gacha bo'lishi kerak.");
        }

        $ticket->update(['rating' => $rating, 'rating_comment' => $comment ? mb_substr($comment, 0, 255) : null]);
    }

    /** Soatlik ish: SLA eslatmalari va avtomatik yopish. @return array{reminded:int, closed:int} */
    public function tick(): array
    {
        $reminded = $closed = 0;

        $overdue = SupportTicket::withoutGlobalScopes()->where('status', SupportTicket::OPEN)->whereNull('first_response_at')
            ->where('sla_due_at', '<', now())
            ->where(fn ($q) => $q->whereNull('sla_reminded_at')->orWhere('sla_reminded_at', '<', now()->subHours(self::REMIND_EVERY_HOURS)))->get();

        foreach ($overdue as $t) {
            $this->tg->toSadmins("⏰ SLA o'tdi: ".(Center::find($t->center_id)?->name)." — «{$t->subject}» hali javobsiz.");
            $t->update(['sla_reminded_at' => now()]);
            $reminded++;
        }

        $stale = SupportTicket::withoutGlobalScopes()->where('status', SupportTicket::PENDING_CUSTOMER)->where('last_staff_at', '<', now()->subDays(self::AUTO_CLOSE_DAYS))->get();
        foreach ($stale as $t) {
            $this->close($t, 'auto');
            if ($center = Center::find($t->center_id)) {
                $this->alerts->send($center, "«{$t->subject}» murojaati ".self::AUTO_CLOSE_DAYS." kun javob bo'lmagani uchun yopildi. Baho qoldirishingiz mumkin.");
            }
            $closed++;
        }

        return compact('reminded', 'closed');
    }

    /** sAdmin sidebar nishoni: javob kutayotgan murojaatlar soni. */
    public function waitingCount(): int
    {
        return SupportTicket::withoutGlobalScopes()->where('status', SupportTicket::OPEN)->count();
    }
}
