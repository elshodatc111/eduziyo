@extends('layouts.guest')
@section('title', 'Yangi parol')

@section('content')
    <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Yangi parol</h1>
    <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400">Botdan kelgan 6 xonali kodni va yangi parolni kiriting.</p>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">@csrf
        <x-input name="login" label="Login" :value="$login" required />
        <x-input name="code" label="Kod (6 xona)" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required />
        <x-input name="password" type="password" label="Yangi parol" hint="Kamida 8 belgi, harf va raqam bilan" autocomplete="new-password" required />
        <x-input name="password_confirmation" type="password" label="Parolni takrorlang" autocomplete="new-password" required />
        <button class="btn-primary w-full py-3">Parolni o'zgartirish</button>
    </form>
    <a href="{{ route('password.request') }}" class="mt-4 inline-block text-sm font-semibold text-brand-600">Kodni qayta yuborish</a>
@endsection
