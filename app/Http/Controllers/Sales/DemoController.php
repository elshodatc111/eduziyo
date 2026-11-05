<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\DemoVisit;
use App\Models\PlatformSetting;
use App\Services\DemoService;
use Illuminate\Http\RedirectResponse;

/** sAdmin: demo'ga kirishlar statistikasi va demo markazni qo'lda tiklash. */
class DemoController extends Controller
{
    public function index(DemoService $demo)
    {
        $since = now()->subDays(30);

        $byRole = DemoVisit::where('created_at', '>=', $since)->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        $byDay = DemoVisit::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('date(created_at) as d, count(*) as total')->groupBy('d')->orderBy('d')->pluck('total', 'd');

        return view('sales.demo', [
            'roles' => DemoService::ROLES,
            'byRole' => $byRole,
            'byDay' => $byDay,
            'total30' => (int) $byRole->sum(),
            'available' => $demo->available(),
            'lastReset' => PlatformSetting::value('demo_last_reset_at'),
        ]);
    }

    public function reset(DemoService $demo): RedirectResponse
    {
        $demo->reset();
        PlatformSetting::put('demo_last_reset_at', now()->toDateTimeString());

        return back()->with('success', "Demo markaz boshlang'ich holatga qaytarildi.");
    }
}
