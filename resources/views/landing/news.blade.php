@extends('landing.layout')
@section('title', 'Yangiliklar · '.\App\Support\Brand::name().($posts->currentPage() > 1 ? ' — '.$posts->currentPage().'-sahifa' : ''))
@section('description', \App\Support\Brand::name()." yangiliklari: yangi imkoniyatlar, yangilanishlar va o'quv markazlari uchun foydali maqolalar.")
@section('seo_meta')
    @if ($posts->previousPageUrl())<link rel="prev" href="{{ $posts->previousPageUrl() }}">@endif
    @if ($posts->nextPageUrl())<link rel="next" href="{{ $posts->nextPageUrl() }}">@endif
@endsection

@section('content')
<section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
    <span class="lp-reveal badge-red">Yangiliklar</span>
    <h1 class="lp-reveal lp-d1 mt-4 text-4xl font-black tracking-tight text-ink-900">So'nggi yangiliklar</h1>
    <div class="mt-8 space-y-4">
        @forelse ($posts as $n)
            <a href="{{ route('landing.news.show', $n->slug) }}" class="lp-reveal lp-card group flex flex-col overflow-hidden rounded-3xl border border-ink-100 bg-white shadow-sm sm:flex-row">
                @if ($n->imageUrl())<img src="{{ $n->imageUrl() }}" alt="{{ $n->title }}" loading="lazy" class="h-44 w-full object-cover sm:h-auto sm:w-56">@endif
                <div class="p-5">
                    <div class="text-xs text-ink-400">{{ $n->published_at?->format('d.m.Y') }}</div>
                    <h2 class="mt-1 text-lg font-bold text-ink-900">{{ $n->title }}</h2>
                    @if ($n->excerpt)<p class="mt-1 text-sm text-ink-500">{{ $n->excerpt }}</p>@endif
                </div>
            </a>
        @empty
            <p class="text-ink-500">Hozircha yangilik yo'q.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $posts->links() }}</div>
</section>
@endsection
