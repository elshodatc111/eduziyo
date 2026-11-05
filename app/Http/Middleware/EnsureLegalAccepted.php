<?php

namespace App\Http\Middleware;

use App\Services\DemoService;
use App\Services\LegalService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Amaldagi hujjatlar (oferta, maxfiylik, shartlar) qabul qilinmaguncha foydalanuvchi faqat rozilik sahifasini ko'radi.
 * Yangi versiya qayta rozilik talab qilsa, keyingi so'rovdayoq yana so'raladi. sAdmin va demo foydalanuvchi bundan mustasno.
 */
class EnsureLegalAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs('legal.*', 'logout', 'demo.leave') || app(DemoService::class)->isDemoUser($user)) {
            return $next($request);
        }

        if (app(LegalService::class)->pendingFor($user)->isEmpty()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Avval hujjatlarga rozilik bering.'], 403);
        }

        return redirect()->route('legal.consent');
    }
}
