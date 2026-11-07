@php
    $brand = \App\Support\Brand::name();
    // @section('x', '...') matni allaqachon HTML-escape qilingan; {{ }} ikkinchi marta escape qilmasligi uchun avval qaytaramiz
    $seoY = fn (string $k) => trim(html_entity_decode($__env->yieldContent($k), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $seoTitle = $seoY('title') ?: $brand." — o'quv markazlari uchun CRM va boshqaruv tizimi";
    $seoDesc = $seoY('description') ?: "{$brand} — o'quv markazingizni bitta tizimdan boshqaring: o'quvchilar, guruhlar, davomad, to'lovlar, kassa, ish haqi, SMS va hisobotlar. Ko'p filialli markazlar uchun, o'zbek tilida.";
    $seoDesc = \App\Support\Seo::limit($seoDesc, 170);
    $seoImage = \App\Support\Seo::image($seoY('og_image') ?: null);
    $seoType = $seoY('og_type') ?: 'website';
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
