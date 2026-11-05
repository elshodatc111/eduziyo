<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Center;
use App\Models\GroupStudent;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\PermissionRegistry;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Ommaviy demo markaz: bitta umumiy markaz, har rol uchun bittadan tayyor foydalanuvchi, "bir bosishda kirish".
 * Demo foydalanuvchilarning paroli har tozalashda tasodifiy; uni hech kim bilmaydi va kerak ham emas.
 */
class DemoService
{
    public const CENTER_CODE = 'demo';

    public const SESSION_MINUTES = 30;

    /** rol => [belgi, nima ko'rasiz, ikonka] - sotuv sahifasidagi kartalar uchun. */
    public const ROLES = [
        'owner' => ['Direktor', "Barcha filiallar, statistika, kassa va markaz sozlamalari", 'crown'],
        'admin' => ['Admin', "O'quvchi, guruh, to'lov va davomat bilan kundalik ish", 'briefcase'],
        'manager' => ['Menejer', "O'quvchilarni ro'yxatga olish va to'lov qabul qilish", 'users'],
        'operator' => ['Operator', "Murojaatlar (varonka) va qo'ng'iroq natijalari", 'headphones'],
        'teacher' => ["O'qituvchi", "Guruhlarim, davomat olish va ish haqim", 'graduation'],
        'student' => ["O'quvchi", "Jadval, davomat, balans va test topshirish", 'book-open'],
    ];

    public const USERNAMES = [
        'owner' => 'demo.direktor', 'admin' => 'demo.admin', 'manager' => 'demo.menejer',
        'operator' => 'demo.operator', 'teacher' => 'demo.ustoz', 'student' => 'demo.oquvchi',
    ];

    public function center(): ?Center
    {
        return Center::where('code', self::CENTER_CODE)->where('is_demo', true)->first();
    }

    /** Sotuv sahifasida tugmalar ko'rsatilishi mumkinmi: yoqilgan va demo foydalanuvchilar tayyor. */
    public function available(): bool
    {
        if (PlatformSetting::value('demo_enabled', '1') !== '1') {
            return false;
        }

        $center = $this->center();

        return $center !== null && $center->isActive()
            && User::where('center_id', $center->id)->whereIn('username', array_values(self::USERNAMES))->count() === count(self::USERNAMES);
    }

    public function userFor(string $role): ?User
    {
        $center = $this->center();
        $username = self::USERNAMES[$role] ?? null;

        if (! $center || ! $username) {
            return null;
        }

        return User::where('center_id', $center->id)->where('username', $username)->first();
    }

    /** Demo foydalanuvchi uchun kirgandan keyingi sahifa. */
    public function landingRoute(string $role): string
    {
        return $role === 'student' ? route('cabinet.home') : route('dashboard');
    }

    public function isDemoUser(?User $user): bool
    {
        return $user !== null && $user->center_id !== null && (bool) $user->center?->is_demo;
    }

    /**
     * Demo markazni noldan tiklaydi: eski ma'lumot o'chadi (faqat demo markaz!), yangisi yaratiladi.
     *
     * @return array<string,int> o'chirilgan qatorlar (dry-run da - o'chiriladiganlar)
     */
    public function reset(bool $dryRun = false): array
    {
        $center = Center::firstOrCreate(['code' => self::CENTER_CODE], ['name' => 'Demo markaz', 'status' => 'active', 'is_demo' => true]);

        // Xavfsizlik: "demo" kodli markaz demo belgisiga ega bo'lishi shart, aks holda hech narsa o'chirilmaydi.
        if (! $center->is_demo) {
            throw new \LogicException("«demo» kodli markaz is_demo=false: tozalash bekor qilindi.");
        }

        $report = app(CenterWiper::class)->wipe($center, $dryRun);

        if ($dryRun) {
            return $report;
        }

        $center->update(['status' => 'active', 'is_demo' => true, 'name' => 'Demo markaz']);

        // Seeder Auth::login() qiladi: tozalashni veb orqali (sAdmin tugmasi) ishga tushirsa, sAdmin chiqib ketmasligi uchun tiklaymiz.
        $previous = Auth::user();

        try {
            (new DemoSeeder)->configure([['Toshkent filiali', 'demo-toshkent', '90'], ['Samarqand filiali', 'demo-samarqand', '91']], Str::random(40))->run();
            $this->ensureRoleUsers($center);
            $this->seedCourseContent($center);
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            } else {
                Auth::logout();
            }
        }

        return $report;
    }

    /** Har rol uchun barqaror login: mavjud seeder foydalanuvchilarini qayta nomlaydi, operatorni yaratadi. */
    private function ensureRoleUsers(Center $center): void
    {
        $branch = Branch::where('center_id', $center->id)->where('code', 'demo-toshkent')->firstOrFail();
        $password = Str::random(40);

        $rename = function (User $user, string $username, string $name) use ($password) {
            $user->forceFill(['username' => $username, 'name' => $name, 'password' => $password])->save();

            return $user;
        };

        $rename(User::where('center_id', $center->id)->where('username', 'demo.direktor')->firstOrFail(), self::USERNAMES['owner'], 'Demo Direktor');
        $rename(User::where('center_id', $center->id)->where('username', 'demo-toshkent.admin')->firstOrFail(), self::USERNAMES['admin'], 'Demo Admin');
        $rename(User::where('center_id', $center->id)->where('username', 'demo-toshkent.menejer1')->firstOrFail(), self::USERNAMES['manager'], 'Demo Menejer');
        $rename(User::where('center_id', $center->id)->where('username', 'demo-toshkent.ustoz1')->firstOrFail(), self::USERNAMES['teacher'], 'Demo Ustoz');

        $operator = User::create([
            'center_id' => $center->id, 'branch_id' => $branch->id, 'role' => Role::Operator, 'name' => 'Demo Operator',
            'username' => self::USERNAMES['operator'], 'phone' => '+998 90 000 00 02', 'password' => $password,
        ]);
        $operator->syncPermissions(PermissionRegistry::defaultsFor(Role::Operator));

        // O'quvchi: hozir davom etayotgan guruhda o'qiydigan, qarzi yo'q o'quvchi (jadval, davomat, balans va test ko'rinadi)
        $activeGroupIds = \App\Models\Group::where('branch_id', $branch->id)->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->pluck('id');
        $studentId = GroupStudent::whereIn('group_id', $activeGroupIds)->where('is_active', true)
            ->whereIn('student_id', User::where('center_id', $center->id)->where('role', Role::Student)->where('balance', '>=', 0)->select('id'))
            ->orderBy('id')->value('student_id');
        $student = User::where('center_id', $center->id)->where('role', Role::Student)
            ->when($studentId, fn ($q) => $q->where('id', $studentId))->orderBy('id')->firstOrFail();
        $rename($student, self::USERNAMES['student'], "Demo O'quvchi");
    }

    /** Kurslarga namunaviy video va test savollari (o'quvchi kabinetida test topshirib ko'rish uchun). */
    private function seedCourseContent(Center $center): void
    {
        $questions = [
            ['안녕하세요 nima degani?', 'Assalomu alaykum', ['Xayr', 'Rahmat', 'Kechirasiz']],
            ['감사합니다 nima degani?', 'Rahmat', ['Salom', 'Xayr', 'Marhamat']],
            ['사과 nima?', 'Olma', ["Nok", "Uzum", "Anor"]],
            ['물 nima?', 'Suv', ['Sut', 'Choy', 'Non']],
            ['학교 nima?', 'Maktab', ['Uy', 'Kitob', "Do'kon"]],
            ['선생님 kim?', "O'qituvchi", ['Talaba', 'Shifokor', 'Haydovchi']],
            ['학생 kim?', "O'quvchi", ["O'qituvchi", 'Oshpaz', 'Sotuvchi']],
            ['책 nima?', 'Kitob', ['Daftar', 'Ruchka', 'Stol']],
            ['네 nima degani?', 'Ha', ["Yo'q", 'Balki', 'Yaxshi']],
            ['아니요 nima degani?', "Yo'q", ['Ha', 'Rahmat', 'Salom']],
            ['하나 qaysi son?', '1', ['2', '3', '4']],
            ['둘 qaysi son?', '2', ['1', '3', '5']],
            ['셋 qaysi son?', '3', ['2', '4', '6']],
            ['집 nima?', 'Uy', ['Mashina', 'Maktab', "Bog'"]],
            ['친구 kim?', "Do'st", ['Aka', 'Opa', 'Ota']],
            ['커피 nima?', 'Qahva', ['Choy', 'Suv', 'Sharbat']],
        ];

        foreach (\App\Models\Course::where('center_id', $center->id)->get() as $course) {
            foreach ([['Birinchi dars: alifbo', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], ['Ikkinchi dars: salomlashish', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']] as $i => [$title, $url]) {
                \App\Models\CourseVideo::create(['branch_id' => $course->branch_id, 'course_id' => $course->id, 'number' => $i + 1, 'title' => $title, 'url' => $url]);
            }
            foreach ($questions as [$q, $correct, $wrong]) {
                \App\Models\CourseQuestion::create(['branch_id' => $course->branch_id, 'course_id' => $course->id, 'question' => $q, 'correct' => $correct, 'wrong' => $wrong]);
            }
        }
    }
}
