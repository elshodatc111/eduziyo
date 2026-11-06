@extends('layouts.guest')
@section('title', 'Parolni tiklash')

@section('content')
    <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Parolni tiklash</h1>
    <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400">Loginingizni kiriting — bir martalik kod Telegram botga yuboriladi.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">@csrf
        <x-input name="login" label="Login" autocomplete="username" required autofocus />
        <button class="btn-primary w-full py-3">Kod yuborish</button>
    </form>
    <div class="mt-5 rounded-xl border border-ink-200 p-3 text-xs text-ink-500 dark:border-ink-800">
        Telegram ulanmaganmi? @if ($botLink)<a class="font-semibold text-brand-600" href="{{ $botLink }}" target="_blank" rel="noopener">Botni oching</a>@else Botni oching @endif, <b>/parol</b> buyrug'ini yuboring va telefon raqamingizni tasdiqlang.
    </div>
    <a href="{{ route('login') }}" class="mt-4 inline-block text-sm font-semibold text-brand-600">← Kirish sahifasi</a>
@endsection
