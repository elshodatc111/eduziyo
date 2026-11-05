<?php

namespace App\Http\Controllers;

use App\Models\Center;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Direktor: o'z markazining logotipi (yon panelda markaz nomi yonida ko'rinadi). */
class CenterBrandController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000']], [], ['logo' => 'Logotip']);
        $center = Center::findOrFail($request->user()->center_id);

        if ($center->logo_path) {
            Storage::disk('uploads')->delete($center->logo_path);
        }
        $center->update(['logo_path' => $request->file('logo')->store('centers/'.$center->id, 'uploads')]);

        return back()->with('success', 'Markaz logotipi saqlandi.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $center = Center::findOrFail($request->user()->center_id);
        if ($center->logo_path) {
            Storage::disk('uploads')->delete($center->logo_path);
            $center->update(['logo_path' => null]);
        }

        return back()->with('success', 'Logotip olib tashlandi.');
    }
}
