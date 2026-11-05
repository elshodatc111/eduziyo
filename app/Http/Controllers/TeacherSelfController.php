<?php

namespace App\Http\Controllers;

use App\Models\GroupDay;
use App\Models\Payout;
use App\Services\PayrollService;
use Illuminate\Http\Request;

/** O'qituvchi o'z ish haqi va dars jadvalini ko'radi (faqat o'zini; boshqa o'qituvchi ma'lumoti ochilmaydi). */
class TeacherSelfController extends Controller
{
    public function payroll(Request $request, PayrollService $payroll)
    {
        $me = $request->user();

        return view('teacher.payroll', [
            'accruals' => $payroll->teacherAccruals($me),
            'payouts' => Payout::with('group')->where('recipient_id', $me->id)->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function schedule(Request $request)
    {
        $me = $request->user();

        $days = GroupDay::with(['group:id,name', 'room:id,name', 'lessonTime:id,starts_at,ends_at'])
            ->whereHas('group', fn ($q) => $q->where('teacher_id', $me->id))
            ->whereDate('date', '>=', today())->whereDate('date', '<=', today()->addDays(13))
            ->orderBy('date')->get()
            ->sortBy(fn ($d) => $d->date->format('Y-m-d').($d->lessonTime?->starts_at ?? ''))
            ->groupBy(fn ($d) => $d->date->format('Y-m-d'));

        return view('teacher.schedule', compact('days'));
    }
}
