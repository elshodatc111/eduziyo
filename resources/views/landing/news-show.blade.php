@extends('landing.layout')
@section('title', $post->title.' · '.\App\Support\Brand::name())
@section('og_image', (string) $post->imageUrl())
@section('description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 150))

@section('content')
<article class="lp-reveal mx-auto max-w-3xl px-4 py-16 sm:px-6">
    <a href="{{ route('landing.news') }}" class="text-sm font-semibold text-brand-600 hover:underline">← Barcha yangiliklar</a>
    <div class="mt-4 text-xs text-ink-400">{{ $post->published_at?->format('d.m.Y') }}</div>
    <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-ink-900">{{ $post->title }}</h1>
    @if ($post->imageUrl())<img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" class="mt-6 max-h-[28rem] w-full rounded-2xl object-cover">@endif
    @if ($post->youtube_id)
        <div class="mt-6 aspect-video overflow-hidden rounded-2xl bg-ink-100">
            <iframe class="h-full w-full" loading="lazy" src="{{ \App\Support\YouTube::embedUrl($post->youtube_id) }}" title="{{ $post->title }}" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
        </div>
    @endif
    {{-- Matn escape qilinadi; yangi qatorlar saqlanadi (HTML qabul qilinmaydi) --}}
    <div class="mt-6 whitespace-pre-line text-ink-700 leading-relaxed">{{ $post->body }}</div>
</article>
@endsection
