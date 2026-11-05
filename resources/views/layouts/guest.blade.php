<!DOCTYPE html>
<html lang="uz" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ \App\Support\Brand::name() }}</title>
    @include('partials.pwa-head')
    <script>try{if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark')}}catch(e){}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white dark:bg-ink-950">
<div class="flex min-h-full items-center justify-center p-6">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex items-center gap-3"><x-logo /><span class="text-xl font-extrabold tracking-tight text-ink-900 dark:text-white">{{ \App\Support\Brand::name() }}</span></div>
        <x-alert />
        @yield('content')
    </div>
</div>
</body>
</html>
