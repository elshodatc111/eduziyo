@extends('layouts.app')
@section('title', $guide->title)

@section('content')
    <x-page-header :title="$guide->title">
        <x-slot:actions><a href="{{ route('guides.index') }}" class="btn-secondary">← Barcha videolar</a></x-slot:actions>
    </x-page-header>
    {{-- Faqat tanlangan video: o'yin ro'yxati/tavsiya yo'q (youtube-nocookie, rel=0). Pastdagi ro'yxat — ilovaning o'z havolalari. --}}
    <div class="mx-auto max-w-4xl overflow-hidden rounded-2xl bg-black shadow-lg" style="aspect-ratio:16/9">
        <iframe class="h-full w-full" src="{{ $guide->embedUrl() }}" title="{{ $guide->title }}" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
    </div>
    @if ($others->isNotEmpty())
        <div class="mx-auto mt-6 max-w-4xl"><div class="mb-2 text-sm font-semibold text-ink-500">Boshqa videolar</div>
            <ul class="space-y-1 text-sm">@foreach ($others as $o)<li><a class="text-brand-600 hover:underline" href="{{ route('guides.show', $o->id) }}">{{ $o->title }}</a></li>@endforeach</ul></div>
    @endif
@endsection
