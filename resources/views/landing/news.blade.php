@extends('landing.layout')
@section('title', 'Yangiliklar · EDUZIYO')

@section('content')
<section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
    <h1 class="text-3xl font-extrabold tracking-tight text-ink-900">Yangiliklar</h1>
    <div class="mt-8 space-y-4">
        @forelse ($posts as $n)
            <a href="{{ route('landing.news.show', $n->slug) }}" class="flex flex-col overflow-hidden rounded-2xl border border-ink-100 transition hover:border-brand-300 sm:flex-row">
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
