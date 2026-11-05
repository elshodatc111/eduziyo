<!DOCTYPE html>
<html lang="uz" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', "EDUZIYO — o'quv markazlari uchun boshqaruv tizimi")</title>
    <meta name="description" content="@yield('description', "O'quv markazingizni bitta tizimdan boshqaring: o'quvchilar, guruhlar, davomad, to'lovlar, kassa, ish haqi, SMS va hisobotlar. Ko'p filialli markazlar uchun.")">
    <meta property="og:title" content="@yield('title', "EDUZIYO — o'quv markazlari uchun boshqaruv tizimi")">
    <meta property="og:description" content="@yield('description', "O'quv markazlari uchun CRM: o'quvchilar, to'lovlar, davomad, SMS va hisobotlar.")">
    <meta property="og:type" content="website">
    @if (trim($__env->yieldContent('og_image')) !== '')<meta property="og:image" content="@yield('og_image')">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-ink-800 antialiased">
<header class="sticky top-0 z-30 border-b border-ink-100 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-lg font-black text-white">E</span>
            <span class="text-lg font-extrabold tracking-tight text-ink-900">EDUZIYO</span>
        </a>
        <nav class="hidden items-center gap-6 text-sm font-medium text-ink-600 md:flex">
            <a href="{{ url('/') }}#imkoniyatlar" class="hover:text-brand-600">Imkoniyatlar</a>
            <a href="{{ url('/') }}#rollar" class="hover:text-brand-600">Kim uchun</a>
            <a href="{{ url('/') }}#tariflar" class="hover:text-brand-600">Tariflar</a>
            <a href="{{ url('/') }}#demo" class="hover:text-brand-600">Demo</a>
            <a href="{{ route('landing.news') }}" class="hover:text-brand-600">Yangiliklar</a>
            <a href="{{ url('/') }}#savollar" class="hover:text-brand-600">Savollar</a>
            <a href="{{ url('/') }}#aloqa" class="hover:text-brand-600">Aloqa</a>
        </nav>
        <div class="flex items-center gap-2">
            <a href="{{ route('login') }}" class="btn-secondary btn-sm">Kirish</a>
            <a href="{{ url('/') }}#ariza" class="btn-primary btn-sm hidden sm:inline-flex">Ariza qoldirish</a>
        </div>
    </div>
</header>

@yield('content')

<footer id="aloqa" class="border-t border-ink-100 bg-ink-50">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-lg font-black text-white">E</span>
                <span class="text-lg font-extrabold text-ink-900">EDUZIYO</span>
            </div>
            <p class="mt-3 max-w-xs text-sm text-ink-500">O'quv markazlari uchun boshqaruv tizimi. O'zbekiston markazlari uchun, o'zbek tilida.</p>
        </div>
        <div>
            <h3 class="text-sm font-bold text-ink-900">Bog'lanish</h3>
            <ul class="mt-3 space-y-2 text-sm text-ink-600">
                @if ($c['contact_phone1'] ?? null)<li><a class="hover:text-brand-600" href="tel:{{ preg_replace('/\s+/', '', $c['contact_phone1']) }}">{{ $c['contact_phone1'] }}</a></li>@endif
                @if ($c['contact_phone2'] ?? null)<li><a class="hover:text-brand-600" href="tel:{{ preg_replace('/\s+/', '', $c['contact_phone2']) }}">{{ $c['contact_phone2'] }}</a></li>@endif
                @if ($c['contact_email'] ?? null)<li><a class="hover:text-brand-600" href="mailto:{{ $c['contact_email'] }}">{{ $c['contact_email'] }}</a></li>@endif
                @if ($c['contact_address'] ?? null)<li>{{ $c['contact_address'] }}</li>@endif
                @if (! ($c['contact_phone1'] ?? null) && ! ($c['contact_email'] ?? null))<li class="text-ink-400">Aloqa ma'lumotlari tez orada qo'shiladi. Ariza qoldiring — o'zimiz bog'lanamiz.</li>@endif
            </ul>
        </div>
        <div>
            <h3 class="text-sm font-bold text-ink-900">Ijtimoiy tarmoqlar</h3>
            <ul class="mt-3 space-y-2 text-sm text-ink-600">
                @if ($c['contact_telegram'] ?? null)<li><a class="hover:text-brand-600" href="{{ $c['contact_telegram'] }}" target="_blank" rel="noopener nofollow">Telegram kanal</a></li>@endif
                @if ($c['contact_instagram'] ?? null)<li><a class="hover:text-brand-600" href="{{ $c['contact_instagram'] }}" target="_blank" rel="noopener nofollow">Instagram</a></li>@endif
                @if (! ($c['contact_telegram'] ?? null) && ! ($c['contact_instagram'] ?? null))<li class="text-ink-400">—</li>@endif
            </ul>
        </div>
    </div>
    <div class="border-t border-ink-100 py-4 text-center text-xs text-ink-400">© {{ date('Y') }} EDUZIYO. Barcha huquqlar himoyalangan.</div>
</footer>
</body>
</html>
