<?php

namespace App\Http\Middleware;

use App\Services\DemoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo markaz foydalanuvchisi uchun himoya: sessiya qisqa (30 daqiqa) va xavfli amallar yopiq
 * (parol/profil, xodim yaratish/tahrirlash, import, ommaviy SMS, bildirishnoma, AI). Oddiy foydalanuvchilarga ta'sir qilmaydi.
 */
class DemoMode
{
    /** Demo rejimda taqiqlangan marshrut nomlari (qiymat: `*` bilan prefiks). */
    private const BLOCKED = [
        'profile.update', 'profile.password', 'cabinet.profile.update', 'cabinet.profile.password',
        'staff.store', 'staff.update', 'staff.permissions.update', 'telegram.*', 'cabinet.telegram.*',
        'imports.*', 'sms.bulk', 'sms.settings', 'notifications.store',
        'ai.*', 'help.send', 'courses.questions.ai', 'courses.questions.ai-store',
        'branches.*', 'contract-settings.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! app(DemoService::class)->isDemoUser($user)) {
            return $next($request);
        }

        $started = (int) $request->session()->get('demo_started_at', 0);

        if ($started === 0) {
            $request->session()->put('demo_started_at', now()->timestamp);
        } elseif (now()->timestamp - $started > DemoService::SESSION_MINUTES * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('landing')->with('success', "Demo sessiyasi tugadi (30 daqiqa). Qaytadan kirishingiz mumkin.");
        }

        if (! $request->isMethodSafe() && $request->route()?->named(...self::BLOCKED)) {
            $message = "Bu amal demo rejimda o'chirilgan.";

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 403)
                : back()->with('error', $message);
        }

        return $next($request);
    }
}
