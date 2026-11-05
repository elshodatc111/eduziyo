<?php

namespace App\Http\Controllers;

use App\Models\DemoVisit;
use App\Services\DemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Sotuv sahifasidan demo markazga "bir bosishda" rol tanlab kirish. */
class DemoController extends Controller
{
    public function enter(Request $request, string $role, DemoService $demo): RedirectResponse
    {
        abort_unless(array_key_exists($role, DemoService::ROLES), 404);

        if (! $demo->available() || ! ($user = $demo->userFor($role)) || ! $user->isActive()) {
            return redirect()->route('landing')->with('error', "Demo hozircha mavjud emas. Ariza qoldiring, biz sizga ko'rsatamiz.");
        }

        // Boshqa hisobdan kirgan bo'lsa, avval chiqariladi (sessiya aralashmasligi uchun)
        if (Auth::check()) {
            Auth::guard('web')->logout();
        }

        $seen = (array) $request->session()->get('demo_roles_seen', []);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('demo_started_at', now()->timestamp);
        $request->session()->put('demo_roles_seen', array_values(array_unique([...$seen, $role])));

        DemoVisit::create([
            'role' => $role,
            'ip_hash' => hash('sha256', ($request->ip() ?? '').config('app.key')),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 160),
        ]);

        return redirect($demo->landingRoute($role));
    }

    /** Demo'dan chiqish: sessiya tugaydi, "ariza qoldirish" bo'limiga o'tadi (ko'rilgan rollar eslab qolinadi). */
    public function leave(Request $request): RedirectResponse
    {
        $seen = (array) $request->session()->get('demo_roles_seen', []);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('demo_roles_seen', $seen);

        return redirect()->to(route('landing').'#ariza');
    }
}
