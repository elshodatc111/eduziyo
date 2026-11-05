<?php

namespace App\Services;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Payout;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    /** Tugaganiga shuncha kun bo'lgan guruhlar ham ish haqi hisobida ko'rsatiladi. */
    public const RECENT_DAYS = 31;

    public function __construct(private WalletService $wallets, private UslugaService $usluga) {}

    /**
     * O'qituvchining guruhlari bo'yicha hisoblangan ish haqi.
     *
     *  hisoblangan = faol o'quvchilar x stavka + bonusli o'quvchilar x bonus stavkasi
     *  bonusli o'quvchi = shu guruhdan keyin boshlangan boshqa guruhda ham faol o'quvchi
     *  davomad bo'yicha = o'tkazilgan darslar / jami darslar x hisoblangan
     *
     * @return array<int, array<string,mixed>>
     */
    public function teacherAccruals(User $teacher): array
    {
        return $this->teacherAccrualsMany(collect([$teacher]))[$teacher->id] ?? [];
    }

    /**
     * Bir nechta o'qituvchi uchun hisob-kitob (ro'yxat sahifasi). Guruhlar, o'tilgan darslar, to'lovlar va faol o'quvchilar
     * BITTA so'rovda yuklanadi (N+1 yo'q); hisob formulasi yagona — {@see teacherAccruals()} ham shu orqali ishlaydi.
     *
     * @param  \Illuminate\Support\Collection<int,User>  $teachers
     * @return array<int, list<array>> o'qituvchi id => guruh qatorlari
     */
    public function teacherAccrualsMany(\Illuminate\Support\Collection $teachers): array
    {
        $teacherIds = $teachers->pluck('id')->all();
        if ($teacherIds === []) {
            return [];
        }

        $allGroups = Group::whereIn('teacher_id', $teacherIds)
            ->whereDate('ends_on', '>=', today()->subDays(self::RECENT_DAYS))
            ->orderByDesc('starts_on')
            ->get();
        $byId = $teachers->keyBy('id');
        $allGroups->each(fn (Group $g) => $g->setRelation('teacher', $byId[$g->teacher_id] ?? null));

        $ids = $allGroups->pluck('id');
        $held = AttendanceSession::whereIn('group_id', $ids)->selectRaw('group_id, count(*) as c')->groupBy('group_id')->pluck('c', 'group_id');
        $paid = Payout::whereIn('group_id', $ids)->whereIn('recipient_id', $teacherIds)->selectRaw('recipient_id, group_id, sum(amount) as s')->groupBy('recipient_id', 'group_id')->get()
            ->mapWithKeys(fn ($r) => [$r->recipient_id.':'.$r->group_id => (int) $r->s]);
        $active = GroupStudent::whereIn('group_id', $ids)->where('is_active', true)->get(['group_id', 'student_id'])->groupBy('group_id')->map(fn ($c) => $c->pluck('student_id'));

        // Bonus: guruh o'quvchisi (faol) shu guruhdan KEYIN yoki bir vaqtda boshlangan boshqa guruhda ham faol bo'lsa — hammasi bir yo'la hisoblanadi
        $allStudents = $active->flatten()->unique()->values()->all();
        $enrolled = $allStudents === [] ? collect() : GroupStudent::whereIn('student_id', $allStudents)->where('is_active', true)->get(['group_id', 'student_id'])->groupBy('student_id');
        $starts = $enrolled->isEmpty() ? collect() : Group::whereIn('id', $enrolled->flatten(1)->pluck('group_id')->unique())->get(['id', 'starts_on'])->keyBy('id');

        $out = array_fill_keys($teacherIds, []);

        foreach ($allGroups as $group) {
            $studentIds = $active[$group->id] ?? collect();
            $students = $studentIds->count();
            $bonus = $studentIds->filter(fn ($sid) => collect($enrolled[$sid] ?? [])->contains(
                fn ($e) => $e->group_id !== $group->id && isset($starts[$e->group_id]) && $starts[$e->group_id]->starts_on->gte($group->starts_on)
            ))->count();

            $accrued = $students * $group->teacher_rate + $bonus * $group->teacher_bonus_rate;

            // v13: usluga (staj ustamasi) - alohida qo'shiladi, mavjud hisob (`accrued`) o'zgarmaydi
            $u = $this->usluga->resolve($group);
            $uStudents = $students * $u['student'];
            $uBonus = $bonus * $u['bonus'];
            $total = $accrued + $uStudents + $uBonus;

            $byAttendance = $group->lesson_count > 0 ? (int) round(($held[$group->id] ?? 0) * $total / $group->lesson_count) : 0;
            $paidSum = (int) ($paid[$group->teacher_id.':'.$group->id] ?? 0);

            $out[$group->teacher_id][] = [
                'group' => $group,
                'students' => $students,
                'bonus' => $bonus,
                'held' => (int) ($held[$group->id] ?? 0),
                'accrued' => $accrued,
                'usluga' => $u,
                'usluga_students' => $uStudents,
                'usluga_bonus' => $uBonus,
                'accrued_total' => $total,
                'accrued_by_attendance' => $byAttendance,
                'paid' => $paidSum,
                'remaining' => $byAttendance - $paidSum,
            ];
        }

        return $out;
    }

    public function payTeacher(User $teacher, ?Group $group, PayMethod $method, int $amount, ?string $description, User $actor): Payout
    {
        if ($teacher->role !== Role::Teacher) {
            throw ValidationException::withMessages(['recipient' => "Bu foydalanuvchi o'qituvchi emas."]);
        }

        return $this->pay($teacher, $group, $method, $amount, $description, 'salary_teacher', $actor);
    }

    public function payStaff(User $staff, PayMethod $method, int $amount, ?string $description, User $actor): Payout
    {
        if (! in_array($staff->role, [Role::Admin, Role::Manager, Role::Operator], true)) {
            throw ValidationException::withMessages(['recipient' => "Bu foydalanuvchi hodim emas."]);
        }

        return $this->pay($staff, null, $method, $amount, $description, 'salary_staff', $actor);
    }

    private function pay(User $recipient, ?Group $group, PayMethod $method, int $amount, ?string $description, string $type, User $actor): Payout
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Summani kiriting.']);
        }
        if ($group && $group->teacher_id !== $recipient->id) {
            throw ValidationException::withMessages(['group_id' => "Bu guruh shu o'qituvchiga tegishli emas."]);
        }

        $payout = DB::transaction(function () use ($recipient, $group, $method, $amount, $description, $type, $actor) {
            $payout = Payout::create([
                'branch_id' => $recipient->branch_id, 'recipient_id' => $recipient->id, 'group_id' => $group?->id,
                'method' => $method, 'amount' => $amount, 'description' => $description, 'created_by' => $actor->id,
            ]);

            $this->wallets->post($recipient->branch_id, $method->treasury(), -$amount, $type, trim("{$recipient->name}. {$description}"), $payout, $actor);
            AuditLog::record('payroll.paid', $recipient, "Ish haqi to'landi: ".Format::money($amount)." ({$method->label()})");

            return $payout;
        });

        app(SmsNotifier::class)->notify('staff_paid', $recipient, ['amount' => Format::money($amount)]);

        return $payout;
    }

    /** Guruh o'quvchilaridan nechtasi keyinroq boshlangan boshqa guruhda ham faol. */
}
