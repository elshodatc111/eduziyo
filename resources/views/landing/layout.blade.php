<!DOCTYPE html>
<html lang="uz" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo')
    @include('partials.pwa-head')
    <script>document.documentElement.classList.add('js')</script>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('jsonld')
</head>
<body class="bg-white text-ink-800 antialiased selection:bg-brand-100 selection:text-brand-800">
<div id="lp-progress"></div>

<header id="lp-nav" class="sticky top-0 z-50 border-b border-ink-100/80 bg-white/75 backdrop-blur-xl" x-data="{ open: false }">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
        <a href="{{ route('landing') }}" class="flex items-center gap-2.5">
            @if ($lg = \App\Support\Brand::logoUrl())<img src="{{ $lg }}" alt="{{ \App\Support\Brand::name() }}" class="h-9 w-9 rounded-xl object-contain">@else<span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-lg font-black text-white shadow-lg shadow-brand-600/30">{{ \App\Support\Brand::initial() }}</span>@endif
            <span class="text-lg font-extrabold tracking-tight text-ink-900">{{ \App\Support\Brand::name() }}</span>
        </a>
        <nav class="hidden items-center gap-7 text-sm font-medium text-ink-600 lg:flex">
            <a href="{{ route('landing') }}#imkoniyatlar" class="lp-link hover:text-brand-600">Imkoniyatlar</a>
            <a href="{{ route('landing') }}#rollar" class="lp-link hover:text-brand-600">Kim uchun</a>
            <a href="{{ route('landing') }}#tariflar" class="lp-link hover:text-brand-600">Tariflar</a>
            <a href="{{ route('landing') }}#demo" class="lp-link hover:text-brand-600">Demo</a>
            <a href="{{ route('landing.news') }}" class="lp-link hover:text-brand-600">Yangiliklar</a>
            <a href="{{ route('landing') }}#savollar" class="lp-link hover:text-brand-600">Savollar</a>
            <a href="{{ route('landing') }}#aloqa" class="lp-link hover:text-brand-600">Aloqa</a>
        </nav>
        <div class="flex items-center gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary btn-sm lp-shine"><x-lp-icon name="dashboard" class="h-4 w-4" /> Panelga o'tish</a>
            @else
                <a href="{{ route('login') }}" class="btn-secondary btn-sm hidden sm:inline-flex"><x-lp-icon name="log-in" class="h-4 w-4" /> Kirish</a>
                <a href="{{ route('landing') }}#ariza" class="btn-primary btn-sm lp-shine hidden sm:inline-flex">Ariza qoldirish</a>
            @endauth
            <button type="button" class="btn-ghost -mr-2 p-2 lg:hidden" @click="open = !open" aria-label="Menyu">
                <x-lp-icon name="menu" x-show="!open" /><x-lp-icon name="x" x-show="open" x-cloak />
            </button>
        </div>
    </div>
    <div x-show="open" x-cloak x-transition.opacity class="border-t border-ink-100 bg-white lg:hidden" @click.outside="open = false">
        <div class="mx-auto flex max-w-7xl flex-col px-4 py-3 text-sm font-medium text-ink-700">
            @foreach ([['imkoniyatlar', 'Imkoniyatlar'], ['rollar', 'Kim uchun'], ['tariflar', 'Tariflar'], ['demo', 'Demo'], ['savollar', 'Savollar'], ['aloqa', 'Aloqa']] as [$id, $label])
                <a href="{{ route('landing') }}#{{ $id }}" @click="open = false" class="rounded-lg px-3 py-2.5 hover:bg-ink-50">{{ $label }}</a>
            @endforeach
            <a href="{{ route('landing.news') }}" class="rounded-lg px-3 py-2.5 hover:bg-ink-50">Yangiliklar</a>
            @guest
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <a href="{{ route('login') }}" class="btn-secondary justify-center">Kirish</a>
                    <a href="{{ route('landing') }}#ariza" @click="open = false" class="btn-primary justify-center">Ariza</a>
                </div>
            @endguest
        </div>
    </div>
</header>

@yield('content')

<footer id="aloqa" class="relative overflow-hidden bg-ink-950 text-ink-300">
    <div class="lp-blob b2 -left-24 top-0 h-72 w-72 bg-brand-700/40"></div>
    <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-4">
        <div class="lp-reveal md:col-span-2">
            <div class="flex items-center gap-2.5">
                @if ($lg = \App\Support\Brand::logoUrl())<img src="{{ $lg }}" alt="{{ \App\Support\Brand::name() }}" class="h-10 w-10 rounded-xl bg-white object-contain">@else<span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-xl font-black text-white">{{ \App\Support\Brand::initial() }}</span>@endif
                <span class="text-xl font-extrabold text-white">{{ \App\Support\Brand::name() }}</span>
            </div>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-400">O'quv markazlari uchun boshqaruv tizimi. O'zbekiston markazlari uchun, o'zbek tilida yaratilgan.</p>
            <div class="mt-5 flex gap-3">
                @if ($c['contact_telegram'] ?? null)<a href="{{ $c['contact_telegram'] }}" target="_blank" rel="noopener nofollow" aria-label="Telegram" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 transition hover:-translate-y-1 hover:bg-brand-600 hover:text-white"><x-lp-icon name="send" class="h-5 w-5" /></a>@endif
                @if ($c['contact_instagram'] ?? null)<a href="{{ $c['contact_instagram'] }}" target="_blank" rel="noopener nofollow" aria-label="Instagram" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 transition hover:-translate-y-1 hover:bg-brand-600 hover:text-white"><x-lp-icon name="instagram" class="h-5 w-5" /></a>@endif
            </div>
        </div>
        <div class="lp-reveal lp-d2">
            <h3 class="text-sm font-bold uppercase tracking-wider text-white">Bo'limlar</h3>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a class="hover:text-white" href="{{ route('landing') }}#imkoniyatlar">Imkoniyatlar</a></li>
                <li><a class="hover:text-white" href="{{ route('landing') }}#tariflar">Tariflar</a></li>
                <li><a class="hover:text-white" href="{{ route('landing') }}#demo">Demo</a></li>
                <li><a class="hover:text-white" href="{{ route('landing.news') }}">Yangiliklar</a></li>
                <li><a class="hover:text-white" href="{{ route('login') }}">Tizimga kirish</a></li>
            </ul>
        </div>
        <div class="lp-reveal lp-d3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-white">Bog'lanish</h3>
            <ul class="mt-4 space-y-3 text-sm">
                @if ($c['contact_phone1'] ?? null)<li class="flex items-center gap-2.5"><x-lp-icon name="phone" class="h-4 w-4 text-brand-400" /><a class="hover:text-white" href="tel:{{ preg_replace('/\s+/', '', $c['contact_phone1']) }}">{{ $c['contact_phone1'] }}</a></li>@endif
                @if ($c['contact_phone2'] ?? null)<li class="flex items-center gap-2.5"><x-lp-icon name="phone" class="h-4 w-4 text-brand-400" /><a class="hover:text-white" href="tel:{{ preg_replace('/\s+/', '', $c['contact_phone2']) }}">{{ $c['contact_phone2'] }}</a></li>@endif
                @if ($c['contact_email'] ?? null)<li class="flex items-center gap-2.5"><x-lp-icon name="mail" class="h-4 w-4 text-brand-400" /><a class="hover:text-white" href="mailto:{{ $c['contact_email'] }}">{{ $c['contact_email'] }}</a></li>@endif
                @if ($c['contact_address'] ?? null)<li class="flex items-start gap-2.5"><x-lp-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-brand-400" /><span>{{ $c['contact_address'] }}</span></li>@endif
                @if (! ($c['contact_phone1'] ?? null) && ! ($c['contact_phone2'] ?? null) && ! ($c['contact_email'] ?? null) && ! ($c['contact_address'] ?? null))
                    <li class="text-ink-500">Ariza qoldiring — o'zimiz bog'lanamiz.</li>
                @endif
            </ul>
        </div>
    </div>
    <div class="relative flex flex-wrap items-center justify-center gap-x-5 gap-y-1 border-t border-white/10 pt-5 text-xs text-ink-400">
        <a class="hover:text-white" href="{{ route('legal.offer') }}">Ommaviy oferta</a>
        <a class="hover:text-white" href="{{ route('legal.privacy') }}">Maxfiylik siyosati</a>
        <a class="hover:text-white" href="{{ route('legal.terms') }}">Foydalanish shartlari</a>
    </div>
    <div class="relative py-5 text-center text-xs text-ink-500">© {{ date('Y') }} {{ \App\Support\Brand::name() }}. Barcha huquqlar himoyalangan.</div>
</footer>

<button id="lp-top" type="button" aria-label="Yuqoriga" class="fixed bottom-6 right-6 z-40 inline-flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 text-white shadow-xl shadow-brand-600/30 hover:bg-brand-700"><x-lp-icon name="arrow-up" class="h-5 w-5" /></button>
<script src="{{ asset('js/landing.js') }}?v={{ @filemtime(public_path('js/landing.js')) }}" defer></script>
@include('partials.pwa-install')
</body>
</html>
