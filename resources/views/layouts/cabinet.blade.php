<!DOCTYPE html>
<html lang="uz" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kabinet') · {{ \App\Support\Brand::name() }}</title>
    @include('partials.pwa-head')
    <script>try{if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark')}}catch(e){}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-ink-50 pb-24 dark:bg-ink-950" x-data>
@include('partials.demo-banner')
@php $me = auth()->user(); @endphp

<header class="sticky top-0 z-20 border-b border-ink-200/70 bg-white/90 backdrop-blur dark:border-ink-800 dark:bg-ink-900/90" style="padding-top: env(safe-area-inset-top)">
    <div class="mx-auto flex h-14 max-w-2xl items-center gap-3 px-4">
        <x-logo />
        <div class="min-w-0 flex-1 leading-tight">
            <div class="truncate text-sm font-bold text-ink-900 dark:text-white">{{ $me->name }}</div>
            <div class="truncate text-xs text-ink-500">{{ $me->branch?->name }}</div>
        </div>
        <a href="{{ route('cabinet.notifications') }}" class="btn-ghost relative p-2" aria-label="Xabarlar">
            <x-icon name="bell" />
            @if (($unreadCount = $me->notificationRecipients()->whereNull('read_at')->count()) > 0)
                <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white">{{ $unreadCount }}</span>
            @endif
        </a>
        <x-logout-button />
    </div>
</header>

<main class="mx-auto max-w-2xl px-4 py-5">
    <x-alert />
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-900 dark:bg-brand-950/50 dark:text-brand-200">
            @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif
    @yield('content')
</main>

@php
    $tabs = [
        ['cabinet.home', 'Bosh sahifa', 'home'],
        ['cabinet.groups', 'Guruhlar', 'users'],
        ['cabinet.payments', "To'lov", 'wallet'],
        ['cabinet.courses', 'Kurslar', 'book'],
        ['cabinet.profile', 'Profil', 'user'],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-200/70 bg-white/95 backdrop-blur dark:border-ink-800 dark:bg-ink-900/95" style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="mx-auto grid max-w-2xl grid-cols-5">
        @foreach ($tabs as [$route, $label, $icon])
            @php $active = request()->routeIs($route === 'cabinet.home' ? 'cabinet.home' : $route.'*'); @endphp
            <a href="{{ route($route) }}" class="flex flex-col items-center gap-0.5 py-2.5 text-[11px] font-medium {{ $active ? 'text-brand-600' : 'text-ink-500' }}">
                <x-icon :name="$icon" class="h-5 w-5" />{{ $label }}
            </a>
        @endforeach
    </div>
</nav>
@include('partials.pwa-install')
</body>
</html>
