<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LandingController;
use App\Models\PlatformSetting;
use App\Rules\UzPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sotuv sahifasi aloqa ma'lumotlari va tarif matni: faqat sAdmin. */
class SettingsController extends Controller
{
    public function edit()
    {
        return view('sales.settings', ['c' => PlatformSetting::many(LandingController::CONTACT_KEYS)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_instagram' => ['nullable', 'url', 'max:200', 'starts_with:https://,http://'],
            'contact_telegram' => ['nullable', 'url', 'max:200', 'starts_with:https://,http://'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_phone1' => ['nullable', 'string', new UzPhone],
            'contact_phone2' => ['nullable', 'string', new UzPhone],
            'contact_address' => ['nullable', 'string', 'max:200'],
            'tariff_text' => ['nullable', 'string', 'max:1000'],
            'demo_text' => ['nullable', 'string', 'max:1000'],
            'demo_login' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._@+-]+$/'],
            'demo_password' => ['nullable', 'string', 'max:60'],
        ], [], [
            'contact_instagram' => 'Instagram havolasi', 'contact_telegram' => 'Telegram kanal havolasi', 'contact_email' => 'Email',
            'contact_phone1' => '1-telefon', 'contact_phone2' => '2-telefon', 'contact_address' => 'Manzil', 'tariff_text' => 'Tarif matni', 'demo_text' => 'Demo matni', 'demo_login' => 'Demo login', 'demo_password' => 'Demo parol',
        ]);

        foreach (LandingController::CONTACT_KEYS as $key) {
            PlatformSetting::put($key, $data[$key] ?? null);
        }

        return back()->with('success', 'Sotuv sahifasi sozlamalari saqlandi.');
    }
}
