@extends('layouts.cabinet')
@section('title', 'Profil')
@section('content')
<h1 class="mb-4 text-lg font-extrabold text-ink-900 dark:text-white">Profil</h1>
<div class="card mb-5"><div class="card-body">
    <dl class="space-y-1.5 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-ink-500">F.I.O</dt><dd class="font-medium text-ink-800 dark:text-ink-100">{{ $me->name }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-ink-500">Login</dt><dd class="font-medium text-ink-800 dark:text-ink-100">{{ $me->username }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-ink-500">Filial</dt><dd class="font-medium text-ink-800 dark:text-ink-100">{{ $me->branch?->name }}</dd></div>
    </dl>
    <form method="POST" action="{{ route('cabinet.profile.update') }}" class="mt-4 space-y-3">
        @csrf @method('PUT')
        <label class="block text-sm font-medium text-ink-700 dark:text-ink-200">Telefon
            <input name="phone" value="{{ old('phone', $me->phone) }}" placeholder="+998 90 123 4567" class="input mt-1" inputmode="tel">
        </label>
        <button class="btn-primary w-full">Saqlash</button>
    </form>
</div></div>

<div class="card mb-5"><div class="card-body">
    <h2 class="mb-3 text-sm font-bold text-ink-900 dark:text-white">Parolni o'zgartirish</h2>
    <form method="POST" action="{{ route('cabinet.profile.password') }}" class="space-y-3">
        @csrf @method('PUT')
        <input type="password" name="current_password" placeholder="Joriy parol" class="input" autocomplete="current-password">
        <input type="password" name="password" placeholder="Yangi parol (kamida 8 belgi)" class="input" autocomplete="new-password">
        <input type="password" name="password_confirmation" placeholder="Yangi parolni takrorlang" class="input" autocomplete="new-password">
        <button class="btn-primary w-full">Parolni yangilash</button>
    </form>
</div></div>

<div class="mb-5">@include('partials.telegram-card')</div>
@endsection
