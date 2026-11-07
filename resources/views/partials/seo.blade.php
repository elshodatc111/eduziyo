@php
    $brand = \App\Support\Brand::name();
    $seoTitle = trim($__env->yieldContent('title')) ?: $brand." — o'quv markazlari uchun CRM va boshqaruv tizimi";
    $seoDesc = trim($__env->yieldContent('description')) ?: "{$brand} — o'quv markazingizni bitta tizimdan boshqaring: o'quvchilar, guruhlar, davomad, to'lovlar, kassa, ish haqi, SMS va hisobotlar. Ko'p filialli markazlar uchun, o'zbek tilida.";
    $seoDesc = \App\Support\Seo::limit($seoDesc, 170);
    $seoImage = \App\Support\Seo::image(trim($__env->yieldContent('og_image')) ?: null);
    $seoType = trim($__env->yieldContent('og_type')) ?: 'website';
    $seoCanon = \App\Support\Seo::canonical();
@endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDesc }}">
    <meta name="robots" content="{{ \App\Support\Seo::indexable() ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, nofollow' }}">
    <meta name="theme-color" content="#e11d34">
    <meta name="application-name" content="{{ $brand }}">
    <link rel="canonical" href="{{ $seoCanon }}">
    <link rel="alternate" hreflang="uz" href="{{ $seoCanon }}">
    <link rel="alternate" hreflang="x-default" href="{{ $seoCanon }}">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:locale" content="uz_UZ">
    <meta property="og:type" content="{{ $seoType }}">
    <meta property="og:url" content="{{ $seoCanon }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDesc }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDesc }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    @yield('seo_meta')
