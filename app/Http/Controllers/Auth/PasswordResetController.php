<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramPasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Parolni tiklash (faqat Telegram bot kodi orqali). Foydalanuvchi mavjudligi oshkor qilinmaydi. */
class PasswordResetController extends Controller
{
    public function forgot(TelegramClient $tg)
    {
        return view('auth.forgot', ['botLink' => $tg->username() ? 'https://t.me/'.ltrim($tg->username(), '@') : null]);
    }

    public function sendCode(Request $request, TelegramPasswordReset $reset): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:100']]);
        $reset->request($data['login']);

        return redirect()->route('password.reset')->with('login', $data['login'])
            ->with('success', "Agar hisob Telegram'ga ulangan bo'lsa, kod botga yuborildi (10 daqiqa amal qiladi).");
    }

    public function form(Request $request)
    {
        return view('auth.reset', ['login' => session('login', $request->query('login'))]);
    }

    public function reset(Request $request, TelegramPasswordReset $reset): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['login' => 'Login', 'code' => 'Kod', 'password' => 'Yangi parol']);

        $reset->reset($data['login'], $data['code'], $data['password']);

        return redirect()->route('login')->with('success', "Parol yangilandi. Yangi parol bilan kiring.");
    }
}
