<?php

namespace App\Services\Telegram;

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Payment;
use App\Models\TelegramGuardian;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Ota-ona va o'quvchi uchun Telegram bot: telefon bo'yicha ulash, jadval/guruh/qarz/davomat ma'lumoti va avtomatik xabarlar.
 * Ota-ona raqami — o'quvchining `phone2`, o'quvchining o'zi — `phone`. Boshqa o'quvchilar (guruhdoshlar) ma'lumoti HECH QACHON berilmaydi.
 */
class TelegramFamilyService
{
    public function __construct(private TelegramClient $tg) {}

    /** Chat ulangan o'quvchilar. @return Collection<int,TelegramGuardian> */
    public function links(int $chat): Collection
    {
        return TelegramGuardian::withoutGlobalScopes()->where('chat_id', $chat)->with(['student' => fn ($q) => $q->select('id', 'name', 'balance', 'status', 'archived_at', 'branch_id', 'center_id')])->get()
            ->filter(fn ($l) => $l->student && $l->student->archived_at === null);
    }

    /**
     * Telefon bo'yicha ulaydi (telefon Telegram'dan tasdiqlangan o'z kontakti bo'lishi shart — chaqiruvchi tekshiradi).
     *
     * @return Collection<int,TelegramGuardian> yangi va mavjud ulanishlar
     */
    public function linkByPhone(int $chat, string $phone, ?string $username): Collection
    {
        $students = User::where('role', Role::Student->value)->where('status', 'active')->whereNull('archived_at')
            ->where(fn ($q) => $q->where('phone', $phone)->orWhere('phone2', $phone))->get();

        foreach ($students as $s) {
            TelegramGuardian::withoutGlobalScopes()->updateOrCreate(
                ['student_id' => $s->id, 'chat_id' => $chat],
                ['center_id' => $s->center_id, 'branch_id' => $s->branch_id, 'kind' => $s->phone === $phone ? TelegramGuardian::STUDENT : TelegramGuardian::PARENT,
                    'phone' => $phone, 'tg_username' => $username, 'linked_at' => now()],
            );
        }

        return $this->links($chat);
    }

    public function unlink(int $chat): int
    {
        return TelegramGuardian::withoutGlobalScopes()->where('chat_id', $chat)->delete();
    }

    public function toggle(int $chat, string $field, bool $on): void
    {
        if (in_array($field, ['notify_absence', 'notify_debt', 'notify_payment'], true)) {
            TelegramGuardian::withoutGlobalScopes()->where('chat_id', $chat)->update([$field => $on]);
        }
    }

    // ---------------- Ma'lumot matnlari ----------------

    /** Faol va kelgusi guruhlar: kurs, ustoz, kunlar/vaqt, xona, muddat, o'tilgan darslar. */
    public function groupsText(User $student): string
    {
        $groups = $this->activeGroups($student);
        if ($groups->isEmpty()) {
            return "Hozir faol guruh yo'q.";
        }

        $lines = [];
        foreach ($groups as $g) {
            $held = $g->sessions()->count();
            $lines[] = "👥 <b>".e($g->name)."</b> — ".e((string) $g->course?->name)
                ."\n👨‍🏫 Ustoz: ".e((string) ($g->teacher?->name ?? '—'))
                ."\n🗓 ".$g->schedule->label()." · ⏰ ".$this->time($g)
                ."\n🚪 Xona: ".e((string) ($g->room?->name ?? '—'))
                ."\n📅 ".$g->starts_on->format('d.m.Y').' — '.$g->ends_on->format('d.m.Y').' ('.$g->status_label.')'
                ."\n📚 Darslar: {$held}/{$g->lesson_count}";
        }

        return implode("\n\n", $lines);
    }

    /** Eng yaqin darslar (14 kun ichida, guruh kunlari bo'yicha). */
    public function scheduleText(User $student): string
    {
        $groups = $this->activeGroups($student);
        $rows = [];
        foreach ($groups as $g) {
            foreach ($g->days()->whereBetween('date', [today()->toDateString(), today()->addDays(13)->toDateString()])->limit(30)->get() as $d) {
                $rows[] = [$d->date->toDateString(), $g, $d];
            }
        }
        usort($rows, fn ($a, $b) => [$a[0], $a[1]->lessonTime?->starts_at] <=> [$b[0], $b[1]->lessonTime?->starts_at]);

        if ($rows === []) {
            return "Keyingi 14 kunda dars yo'q.";
        }

        $wd = [1 => 'Du', 2 => 'Se', 3 => 'Chor', 4 => 'Pay', 5 => 'Ju', 6 => 'Sha', 7 => 'Yak'];
        $out = [];
        foreach (array_slice($rows, 0, 12) as [$date, $g, $d]) {
            $c = \Illuminate\Support\Carbon::parse($date);
            $out[] = $c->format('d.m').' ('.$wd[$c->dayOfWeekIso].') '.$this->time($g).' — '.e($g->name).($g->room ? ', '.e($g->room->name) : '');
        }

        return implode("\n", $out);
    }

    /** Balans (qarz yoki oldindan to'lov) va so'nggi 5 to'lov. */
    public function balanceText(User $student): string
    {
        $b = (int) $student->balance;
        $head = $b < 0 ? "❗ Qarz: <b>".Format::money(-$b, false)." so'm</b>" : ($b > 0 ? "✅ Balans: <b>".Format::money($b, false)." so'm</b>" : "✅ Qarz yo'q");

        $pays = Payment::withoutGlobalScopes()->where('student_id', $student->id)->where('type', 'payment')->whereNull('reversed_at')->orderByDesc('id')->limit(5)->get();
        $list = $pays->map(fn ($p) => '• '.$p->created_at->format('d.m.Y').' — '.Format::money((int) $p->amount, false)." so'm")->implode("\n");

        return $head.($list ? "\n\nSo'nggi to'lovlar:\n{$list}" : '');
    }

    /** So'nggi 14 kun davomati. */
    public function attendanceText(User $student): string
    {
        $rows = \App\Models\Attendance::withoutGlobalScopes()->where('student_id', $student->id)->whereDate('date', '>=', today()->subDays(14))->orderByDesc('date')->limit(14)->get();
        if ($rows->isEmpty()) {
            return "So'nggi 14 kunda davomat yozuvi yo'q.";
        }

        $came = $rows->where('is_present', true)->count();
        $list = $rows->map(fn ($r) => ($r->is_present ? '✅' : '❌').' '.\Illuminate\Support\Carbon::parse($r->date)->format('d.m'))->implode('  ');

        return "{$list}\n\nKeldi: {$came}, kelmadi: ".($rows->count() - $came);
    }

    /** Bitta chat uchun barcha farzandlar bo'yicha javob. */
    public function reply(int $chat, string $what): string
    {
        $links = $this->links($chat);
        if ($links->isEmpty()) {
            return "Siz hali ulanmagansiz. /ulanish ni bosing.";
        }

        $multi = $links->count() > 1;
        $parts = [];
        foreach ($links as $l) {
            $s = $l->student;
            $body = match ($what) {
                'groups' => $this->groupsText($s),
                'schedule' => $this->scheduleText($s),
                'balance' => $this->balanceText($s),
                'attendance' => $this->attendanceText($s),
            };
            $parts[] = ($multi || $l->kind === TelegramGuardian::PARENT ? "🧒 <b>".e($s->name)."</b>\n" : '').$body;
        }

        return implode("\n\n— — —\n\n", $parts);
    }

    // ---------------- Avtomatik xabarlar ----------------

    /** Kelmagan o'quvchi haqida (kuniga bir marta har bir guruh uchun). */
    public function notifyAbsent(User $student, Group $group): int
    {
        $key = "tg:absent:{$student->id}:{$group->id}:".today()->toDateString();
        if (! Cache::add($key, 1, now()->endOfDay())) {
            return 0;
        }

        return $this->send($student, 'notify_absence', "❌ <b>".e($student->name)."</b> bugun «".e($group->name)."» darsiga kelmadi ("
            .now()->format('d.m.Y').").\nSabab bo'lsa, markaz administratoriga xabar bering.");
    }

    /** Qarz eslatmasi (3 kunda bir martadan tez emas). */
    public function notifyDebt(User $student): int
    {
        if ((int) $student->balance >= 0 || ! Cache::add("tg:debt:{$student->id}", 1, now()->addDays(3))) {
            return 0;
        }

        return $this->send($student, 'notify_debt', "❗ <b>".e($student->name)."</b> hisobida qarz: <b>".Format::money(-(int) $student->balance, false)." so'm</b>.\nTo'lovni markaz kassasiga yoki administratorga murojaat qilib amalga oshiring.");
    }

    /** To'lov qabul qilinganda. */
    public function notifyPayment(User $student, int $amount): int
    {
        return $this->send($student, 'notify_payment', "✅ <b>".e($student->name)."</b> uchun to'lov qabul qilindi: <b>".Format::money($amount, false)." so'm</b>. Rahmat!");
    }

    private function send(User $student, string $flag, string $text): int
    {
        $sent = 0;
        $links = TelegramGuardian::withoutGlobalScopes()->where('student_id', $student->id)->where($flag, true)->get();
        foreach ($links as $l) {
            $sent += $this->tg->sendMessage($l->chat_id, $text) ? 1 : 0;
        }

        return $sent;
    }

    // ---------------- Yordamchilar ----------------

    /** @return Collection<int,Group> */
    private function activeGroups(User $student): Collection
    {
        $ids = GroupStudent::withoutGlobalScopes()->where('student_id', $student->id)->where('is_active', true)->pluck('group_id');

        return Group::withoutGlobalScopes()->whereIn('id', $ids)->whereNull('deleted_at')->whereDate('ends_on', '>=', today())
            ->with(['course:id,name', 'teacher:id,name', 'room:id,name', 'lessonTime'])->orderBy('starts_on')->get();
    }

    private function time(Group $g): string
    {
        $t = $g->lessonTime;

        return $t ? substr((string) $t->starts_at, 0, 5).'–'.substr((string) $t->ends_at, 0, 5) : '—';
    }
}
