<?php

namespace App\Http\Controllers;

use App\Models\VideoGuide;
use Illuminate\Http\Request;

/** Ilova ichidagi video qo'llanmalar: har bir xodim faqat o'z roliga tegishlisini ko'radi. O'quvchi ko'rmaydi. */
class GuideController extends Controller
{
    private function guides(Request $request)
    {
        $user = $request->user();
        abort_if($user->role === \App\Enums\Role::Student, 403);

        return VideoGuide::forRole($user->role->value);
    }

    public function index(Request $request)
    {
        return view('guides.index', ['guides' => $this->guides($request)->get()]);
    }

    public function show(Request $request, int $guide)
    {
        $g = $this->guides($request)->whereKey($guide)->firstOrFail();   // boshqa rolniki — 404

        abort_unless($g->embedUrl(), 404);

        return view('guides.show', ['guide' => $g, 'others' => $this->guides($request)->where('id', '!=', $g->id)->get()]);
    }
}
