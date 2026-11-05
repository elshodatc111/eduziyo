<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloklangan foydalanuvchi va yopilgan filial xodimini chiqarib yuboradi; o'quvchini faqat kabinetga cheklaydi.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $message = null;

        if (! $user->isActive()) {
            $message = 'Akkauntingiz bloklangan. Administrator bilan bog\'laning.';
        } elseif ($problem = $user->accessProblem()) {
            $message = $problem;
        }

        // O'quvchi faqat o'z kabineti (/cabinet) va chiqish sahifasini ko'radi; xodim sahifalariga kira olmaydi.
        if ($message === null && $user->role->usesCabinet() && ! $request->is('api/*') && ! $request->expectsJson()
            && ! $request->routeIs('cabinet.*', 'logout', 'demo.leave', 'legal.*')) {
            return redirect()->route('cabinet.home');
        }

        if ($message === null) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['success' => false, 'message' => $message], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}
