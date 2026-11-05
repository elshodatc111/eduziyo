<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Course;
use App\Models\Group;
use App\Models\NotificationRecipient;
use App\Models\TestAttempt;
use App\Rules\UniquePhonePerRole;
use App\Rules\UzPhone;
use App\Services\AttendanceService;
use App\Services\TestService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * O'quvchi veb-kabineti (E6). Faqat `role:student`; hamma so'rov kirgan o'quvchining O'ZI doirasida:
 * boshqa o'quvchi, sinfdosh yoki o'qituvchi telefoni ko'rsatilmaydi, javob kaliti test topshirilguncha yuborilmaydi.
 */
class CabinetController extends Controller
{
    public function __construct(private TestService $tests, private AttendanceService $attendance) {}

    public function home(Request $request)
    {
        $me = $request->user();

        $groupIds = $me->memberships()->where('is_active', true)->pluck('group_id');
        $groups = Group::whereIn('id', $groupIds)->whereDate('ends_on', '>=', today())
            ->with(['course:id,name', 'teacher:id,name', 'lessonTime', 'room:id,name'])->orderBy('name')->get();

        $todayIds = Group::whereIn('id', $groups->pluck('id'))->havingLessonOn(today()->toDateString())->pluck('id')->all();

        $rates = $this->rates($me->id);

        return view('cabinet.home', [
            'me' => $me,
            'groups' => $groups,
            'todayIds' => $todayIds,
            'overall' => $rates['overall'],
            'recent' => $me->balanceTransactions()->with('group:id,name')->latest('id')->limit(5)->get(),
            'unread' => $me->notificationRecipients()->whereNull('read_at')->count(),
            'botLink' => app(\App\Services\Telegram\TelegramClient::class)->deepLink('ulanish') ? 'https://t.me/'.ltrim((string) app(\App\Services\Telegram\TelegramClient::class)->username(), '@') : null,
        ]);
    }

    public function groups(Request $request)
    {
        $me = $request->user();
        $groups = Group::whereIn('id', $me->memberships()->select('group_id'))
            ->with(['course:id,name', 'teacher:id,name', 'lessonTime', 'room:id,name'])->orderByDesc('starts_on')->get();

        return view('cabinet.groups', ['groups' => $groups, 'rates' => $this->rates($me->id)['byGroup']]);
    }

    public function group(Request $request, Group $group)
    {
        $me = $request->user();
        abort_unless($me->memberships()->where('group_id', $group->id)->exists(), 404);

        $group->load(['course:id,name', 'teacher:id,name', 'lessonTime', 'room:id,name']);

        return view('cabinet.group', [
            'group' => $group,
            'summary' => $this->attendance->studentSummary($group, $me->id),
            'records' => Attendance::where('group_id', $group->id)->where('student_id', $me->id)->orderByDesc('date')->limit(60)->get(),
        ]);
    }

    public function payments(Request $request)
    {
        $me = $request->user();

        return view('cabinet.payments', [
            'me' => $me,
            'transactions' => $me->balanceTransactions()->with('group:id,name')->latest('id')->paginate(25),
        ]);
    }

    public function courses(Request $request)
    {
        $me = $request->user();
        $courses = Course::whereIn('id', $this->tests->accessibleCourseIds($me))
            ->withCount(['videos', 'audios', 'questions'])->orderBy('name')->get();

        $best = TestAttempt::where('student_id', $me->id)->whereNotNull('finished_at')
            ->selectRaw('course_id, max(score) as best, count(*) as attempts')->groupBy('course_id')->get()->keyBy('course_id');

        return view('cabinet.courses', ['courses' => $courses, 'best' => $best, 'books' => Book::active()->orderBy('name')->get()]);
    }

    public function course(Request $request, Course $course)
    {
        $me = $request->user();
        $this->tests->assertAccess($me, $course);

        return view('cabinet.course', [
            'course' => $course,
            'videos' => $course->videos()->get(),
            'audios' => $course->audios()->get(),
            'attempts' => TestAttempt::where('student_id', $me->id)->where('course_id', $course->id)->whereNotNull('finished_at')->latest('id')->limit(10)->get(),
            'hasQuestions' => $course->questions()->exists(),
        ]);
    }

    public function testStart(Request $request, Course $course): RedirectResponse
    {
        $attempt = $this->tests->start($request->user(), $course);

        return redirect()->route('cabinet.test', $attempt->id);
    }

    public function test(Request $request, int $attempt)
    {
        $model = TestAttempt::where('student_id', $request->user()->id)->findOrFail($attempt);

        if ($model->finished_at !== null) {
            return redirect()->route('cabinet.courses.show', $model->course_id);
        }

        // Faqat savol va variantlar (answer_key ko'rsatilmaydi)
        return view('cabinet.test', ['attempt' => $model, 'course' => Course::findOrFail($model->course_id)]);
    }

    public function testSubmit(Request $request, int $attempt)
    {
        $model = TestAttempt::where('student_id', $request->user()->id)->findOrFail($attempt);

        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'integer', 'min:0', 'max:3'],
        ], [], ['answers' => 'Javoblar']);

        $result = $this->tests->submit($model, $data['answers'] ?? []);

        return view('cabinet.test-result', [
            'course' => Course::findOrFail($model->course_id),
            'result' => $result,
            'questions' => $model->questions,
        ]);
    }

    public function notifications(Request $request)
    {
        $me = $request->user();

        $items = NotificationRecipient::where('user_id', $me->id)->with('notification')->latest('id')->paginate(20);

        // Ochilganda hammasi o'qilgan deb belgilanadi (bu sahifada ko'rsatib bo'lingach)
        $unreadIds = $items->getCollection()->whereNull('read_at')->pluck('id');
        $view = view('cabinet.notifications', ['items' => $items, 'unreadIds' => $unreadIds->all()]);
        if ($unreadIds->isNotEmpty()) {
            NotificationRecipient::whereIn('id', $unreadIds)->update(['read_at' => now()]);
        }

        return $view;
    }

    public function profile(Request $request)
    {
        return view('cabinet.profile', ['me' => $request->user()->load('branch:id,name')]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $me = $request->user();

        $data = $request->validate([
            'phone' => ['required', 'string', new UzPhone, new UniquePhonePerRole($me->branch_id, $me->role, $me->id)],
        ], [], ['phone' => 'Telefon']);

        $me->update(['phone' => Format::canonicalPhone($data['phone'])]);
        AuditLog::record('profile.updated', $me, "O'quvchi telefonini yangiladi (kabinet)");

        return back()->with('success', 'Telefon raqamingiz saqlandi.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], [], ['current_password' => 'Joriy parol', 'password' => 'Yangi parol']);

        $me = $request->user();
        $me->update(['password' => $data['password']]);
        $request->session()->regenerate();
        AuditLog::record('auth.password_changed', $me, "Parolni o'zgartirdi (kabinet)");

        return back()->with('success', 'Parol yangilandi.');
    }

    /** @return array{byGroup: array<int,array{present:int,total:int,rate:?int}>, overall: ?int} */
    private function rates(int $studentId): array
    {
        $rows = Attendance::where('student_id', $studentId)
            ->selectRaw('group_id, COUNT(*) as total, SUM(CASE WHEN is_present THEN 1 ELSE 0 END) as present')
            ->groupBy('group_id')->get();

        $by = [];
        $total = 0;
        $present = 0;
        foreach ($rows as $r) {
            $by[$r->group_id] = ['present' => (int) $r->present, 'total' => (int) $r->total, 'rate' => $r->total ? (int) round($r->present * 100 / $r->total) : null];
            $total += (int) $r->total;
            $present += (int) $r->present;
        }

        return ['byGroup' => $by, 'overall' => $total ? (int) round($present * 100 / $total) : null];
    }
}
