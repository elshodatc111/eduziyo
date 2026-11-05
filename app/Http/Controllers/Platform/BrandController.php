<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Support\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** sAdmin: platforma nomi, logotipi va favicon'i (hamma joyda: panel, kirish, sotuv sahifasi, kabinet, PWA). */
class BrandController extends Controller
{
    public function edit()
    {
        return view('platform.brand', ['name' => Brand::name(), 'logo' => Brand::logoUrl(), 'favicon' => PlatformSetting::value('brand_favicon') ? Brand::faviconUrl() : null]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[^<>]+$/u'],
            // SVG ataylab yo'q: u skript tashishi mumkin
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:256'],
        ], [], ['brand_name' => 'Loyiha nomi', 'logo' => 'Logotip', 'favicon' => 'Favicon']);

        PlatformSetting::put('brand_name', trim($data['brand_name']));

        foreach (['logo' => 'brand_logo', 'favicon' => 'brand_favicon'] as $field => $key) {
            if ($request->hasFile($field)) {
                if ($old = PlatformSetting::value($key)) {
                    Storage::disk('uploads')->delete($old);
                }
                PlatformSetting::put($key, $request->file($field)->store('brand', 'uploads'));
            }
        }

        Brand::flush();
        AuditLog::record('brand.updated', null, 'Platforma nomi/logotipi yangilandi: '.trim($data['brand_name']));

        return back()->with('success', 'Brending saqlandi.');
    }

    public function remove(Request $request, string $what): RedirectResponse
    {
        abort_unless(in_array($what, ['logo', 'favicon'], true), 404);
        $key = 'brand_'.$what;

        if ($old = PlatformSetting::value($key)) {
            Storage::disk('uploads')->delete($old);
        }
        PlatformSetting::put($key, null);
        Brand::flush();

        return back()->with('success', $what === 'logo' ? "Logotip olib tashlandi." : "Favicon olib tashlandi.");
    }

    /** PWA manifest: nom va (bo'lsa) logotip dinamik. Hammaga ochiq. */
    public function manifest(): JsonResponse
    {
        $name = Brand::name();
        $logo = Brand::logoUrl();
        $icons = $logo
            ? [['src' => $logo, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']]
            : [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ];

        return response()->json([
            'name' => $name, 'short_name' => mb_substr($name, 0, 12), 'description' => "O'quv markazlari boshqaruv tizimi", 'lang' => 'uz',
            'start_url' => '/dashboard', 'scope' => '/', 'display' => 'standalone', 'orientation' => 'portrait-primary',
            'background_color' => '#ffffff', 'theme_color' => '#e11d48', 'icons' => $icons,
        ])->header('Content-Type', 'application/manifest+json');
    }
}
