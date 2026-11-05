<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Services\LegalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Ommaviy hujjat sahifalari va majburiy rozilik oynasi. */
class LegalController extends Controller
{
    /** /oferta, /maxfiylik, /shartlar — har auditoriya bo'yicha amaldagi versiya. */
    public function show(string $type)
    {
        abort_unless(in_array($type, array_keys(LegalDocument::TYPES), true), 404);

        $docs = LegalDocument::inEffect()->where('type', $type)->orderBy('version')->get()->groupBy('audience')->map->last()->sortKeys()->values();

        return view('legal.show', ['c' => \App\Models\PlatformSetting::many(LandingController::CONTACT_KEYS), 'type' => $type, 'docs' => $docs, 'label' => LegalDocument::TYPES[$type]]);
    }

    public function consent(Request $request, LegalService $legal)
    {
        $pending = $legal->pendingFor($request->user());

        if ($pending->isEmpty()) {
            return redirect()->route('dashboard');
        }

        return view('legal.consent', ['docs' => $pending]);
    }

    public function accept(Request $request, LegalService $legal)
    {
        $pending = $legal->pendingFor($request->user());

        if ($request->boolean('refuse')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/');
        }

        $request->validate(['agree' => ['accepted']], ['agree.accepted' => 'Davom etish uchun hujjatlarga rozilik bering.']);

        $legal->accept($request->user(), $pending, $request);

        return redirect()->intended(route('dashboard'));
    }
}
