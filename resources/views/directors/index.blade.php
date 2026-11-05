@extends('layouts.app')
@section('title', 'Direktorlar')

@php
    $storeUrl = $isPlatform ? route('platform.directors.store', $center->id) : route('center.directors.store');
    $toggleUrl = fn ($u) => $isPlatform ? route('platform.directors.toggle', [$center->id, $u->id]) : route('center.directors.toggle', $u->id);
@endphp

@section('content')
    <x-page-header title="Direktorlar" :subtitle="$center->name" />
    @unless ($isPlatform) @include('center._nav') @endunless

    @if (session('credentials'))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">Kirish ma'lumoti (faqat hozir ko'rinadi): login <b class="font-mono">{{ session('credentials')['login'] }}</b>, parol <b class="font-mono">{{ session('credentials')['password'] }}</b></div>
    @endif
    @if (session('owner_link'))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">Yangi direktorga shu havolani yuboring (7 kun, bir marta). U botda «Start» bosib login va parolni oladi:
            <div class="mt-1 break-all font-mono text-xs">{{ session('owner_link') }}</div></div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card overflow-hidden lg:col-span-2">
            <table class="table">
                <thead><tr><th>Ism</th><th>Login</th><th>Telefon</th><th>Holat</th><th></th></tr></thead>
                <tbody>
                @foreach ($directors as $d)
                    <tr><td class="font-semibold">{{ $d->name }}</td><td class="font-mono text-xs">{{ $d->username }}</td><td>{{ $d->phone }}</td>
                        <td><span class="{{ $d->status === \App\Enums\UserStatus::Active ? 'badge-green' : 'badge-red' }}">{{ $d->status === \App\Enums\UserStatus::Active ? 'Faol' : 'Bloklangan' }}</span></td>
                        <td class="text-right">@if ($d->id !== auth()->id())<form method="POST" action="{{ $toggleUrl($d) }}" onsubmit="return confirm('Holat o\'zgartirilsinmi?')">@csrf<button class="text-xs font-semibold text-brand-600">{{ $d->status === \App\Enums\UserStatus::Active ? 'Bloklash' : 'Faollashtirish' }}</button></form>@endif</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ $storeUrl }}" class="card card-body space-y-3">@csrf
            <h2 class="font-bold text-ink-900 dark:text-white">Direktor qo'shish</h2>
            <x-input name="name" label="Ism familiya" required />
            <x-input name="phone" label="Telefon" placeholder="+998 90 123 4567" required />
            <button class="btn-primary w-fit">Qo'shish</button>
            <p class="text-xs text-ink-500">Yangi direktor shu markazning to'liq huquqiga ega bo'ladi. Parol Telegram bot orqali beriladi.</p>
        </form>
    </div>
@endsection
