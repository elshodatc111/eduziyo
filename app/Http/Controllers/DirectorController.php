<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\User;
use App\Rules\UzPhone;
use App\Services\DirectorService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Direktorlar bo'limi. Direktor — o'z markazi uchun (markaz/direktorlar), sAdmin — istalgan markaz uchun
 * (platform/markazlar/{center}/direktorlar). Boshqa rollarga yopiq (route middleware: owner yoki sadmin).
 */
class DirectorController extends Controller
{
    public function __construct(private DirectorService $directors) {}

    private function center(Request $request, ?int $centerId): Center
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->role === \App\Enums\Role::Owner, 403);

        // Direktor faqat o'z markazini ko'radi (boshqasi — 404)
        $id = $user->isSuperAdmin() ? $centerId : $user->center_id;
        abort_unless($id, 404);

        return Center::findOrFail($id);
    }

    public function index(Request $request, ?int $center = null)
    {
        $c = $this->center($request, $center);

        return view('directors.index', ['center' => $c, 'directors' => $this->directors->directors($c), 'isPlatform' => $request->user()->isSuperAdmin()]);
    }

    public function store(Request $request, ?int $center = null): RedirectResponse
    {
        $c = $this->center($request, $center);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', new UzPhone]], [], ['name' => 'Ism', 'phone' => 'Telefon']);

        try {
            $r = $this->directors->add($c, $data['name'], $data['phone'], $request->user());
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['phone' => $e->getMessage()]);
        }

        $res = back()->with('success', "Direktor qo'shildi: {$r['user']->name}.");

        return $r['link'] ? $res->with('owner_link', $r['link']) : $res->with('credentials', ['login' => $r['user']->username, 'password' => $r['password']]);
    }

    public function toggle(Request $request, int $director, ?int $center = null): RedirectResponse
    {
        $c = $this->center($request, $center);
        $dir = User::where('center_id', $c->id)->findOrFail($director);

        try {
            $this->directors->setBlocked($c, $dir, $dir->status === \App\Enums\UserStatus::Active, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Holat yangilandi.');
    }
}
