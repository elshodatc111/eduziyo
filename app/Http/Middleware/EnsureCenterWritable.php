<?php

namespace App\Http\Middleware;

use App\Models\Center;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Markaz "faqat ko'rish" (tarif tugagan) yoki tarif kutilayotgan holatda bo'lsa, yozish amallarini (POST/PUT/DELETE) to'xtatadi.
 * GET so'rovlari, chiqish, balans/tarif sahifalari va parol almashtirish har doim ishlaydi. sAdmin va demo markaz cheklanmaydi.
 */
class EnsureCenterWritable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin() || ! $user->center_id || $request->isMethodSafe()
            || $request->routeIs('logout', 'demo.leave', 'center.*', 'profile.password', 'cabinet.profile.password')) {
            return $next($request);
        }

        $center = Center::find($user->center_id);

        if (! $center || $center->is_demo) {
            return $next($request);
        }

        if ($center->status->isWritable()) {
            return $this->checkBranchLimit($request, $next, $center);
        }

        $message = $center->status === \App\Enums\CenterStatus::Pending
            ? "Tarif tanlanmaguncha tizim faqat ko'rish rejimida. Direktor «Markaz» bo'limidan tarif sotib olsin."
            : "Tarif muddati tugagan: tizim faqat ko'rish rejimida. Direktor «Markaz» bo'limidan tarifni uzaytirsin.";

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        return back()->with('error', $message);
    }

    /** Tarif filial limitidan oshgan filialda yozish amallari yopiladi (faqat ko'rish). Filialni yopish/o'chirish ruxsat etiladi. */
    private function checkBranchLimit(Request $request, Closure $next, Center $center): Response
    {
        if ($request->routeIs('branches.*', 'profile.*', 'cabinet.*', 'telegram.*', 'tasks.*')) {
            return $next($request);
        }

        $limits = app(\App\Services\BranchLimitService::class);
        $locked = $limits->lockedIds($center);
        if ($locked === []) {
            return $next($request);
        }

        $ids = array_filter([(int) $request->user()->branch_id, (int) \App\Support\BranchContext::id()]);
        foreach ($request->route()?->parameters() ?? [] as $param) {
            if ($param instanceof \Illuminate\Database\Eloquent\Model && isset($param->branch_id)) {
                $ids[] = (int) $param->branch_id;
            }
        }

        if (array_intersect($ids, $locked) === []) {
            return $next($request);
        }

        $message = "Bu filial tarif limitidan oshgani uchun faqat ko'rish rejimida. Direktor tarifni almashtirsin yoki ortiqcha filialni yopsin.";

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
