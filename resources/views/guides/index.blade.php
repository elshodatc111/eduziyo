@extends('layouts.app')
@section('title', "Video qo'llanma")

@section('content')
    <x-page-header title="Video qo'llanma" subtitle="Sizning lavozimingiz uchun o'quv videolar" />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($guides as $g)
            <a href="{{ route('guides.show', $g->id) }}" class="card card-body transition hover:shadow-md">
                <div class="relative mb-2 aspect-video overflow-hidden rounded-lg bg-ink-100 dark:bg-ink-800">
                    @if ($g->coverUrl())<img src="{{ $g->coverUrl() }}" alt="{{ $g->title }}" loading="lazy" class="h-full w-full object-cover">@endif
                    <span class="absolute inset-0 flex items-center justify-center"><span class="flex h-12 w-12 items-center justify-center rounded-full bg-black/60 text-white shadow-lg"><x-icon name="video" class="h-6 w-6" /></span></span>
                </div>
                <div class="font-bold text-ink-900 dark:text-white">{{ $g->title }}</div>
            </a>
        @empty
            <div class="card card-body text-sm text-ink-500 sm:col-span-2 lg:col-span-3">Hozircha video qo'llanma qo'shilmagan.</div>
        @endforelse
    </div>
@endsection
