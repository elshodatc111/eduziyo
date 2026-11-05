@extends('layouts.cabinet')
@section('title', $course->name)
@section('content')
<a href="{{ route('cabinet.courses') }}" class="mb-3 inline-flex items-center gap-1 text-sm text-ink-500">&larr; Kurslar</a>
<h1 class="mb-4 text-lg font-extrabold text-ink-900 dark:text-white">{{ $course->name }}</h1>

<div class="card mb-5"><div class="card-body flex items-center justify-between gap-3">
    <div class="text-sm text-ink-600 dark:text-ink-300">Bilimingizni sinab ko'ring: har safar tasodifiy savollar beriladi.</div>
    @if ($hasQuestions)
        <form method="POST" action="{{ route('cabinet.courses.test', $course) }}">@csrf<button class="btn-primary whitespace-nowrap">Testni boshlash</button></form>
    @else
        <span class="text-xs text-ink-400">Savollar yo'q</span>
    @endif
</div></div>

@if ($attempts->isNotEmpty())
    <h2 class="mb-2 text-sm font-bold text-ink-900 dark:text-white">Natijalarim</h2>
    <div class="card mb-5 divide-y divide-ink-100 dark:divide-ink-800">
        @foreach ($attempts as $a)
            <div class="flex items-center justify-between px-4 py-2.5 text-sm"><span class="text-ink-600 dark:text-ink-300">{{ $a->finished_at->format('d.m.Y H:i') }}</span><span class="font-semibold text-ink-900 dark:text-white">{{ $a->correct_count }}/{{ $a->total }} · {{ $a->score }}%</span></div>
        @endforeach
    </div>
@endif

@foreach ([['Video darslar', $videos], ['Audio darslar', $audios]] as [$title, $items])
    @if ($items->isNotEmpty())
        <h2 class="mb-2 text-sm font-bold text-ink-900 dark:text-white">{{ $title }}</h2>
        <div class="card mb-5 divide-y divide-ink-100 dark:divide-ink-800">
            @foreach ($items as $i)
                <a href="{{ $i->url }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 px-4 py-3 text-sm text-ink-800 dark:text-ink-100">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-xs font-bold text-brand-600 dark:bg-brand-950">{{ $i->number }}</span>{{ $i->title }}
                </a>
            @endforeach
        </div>
    @endif
@endforeach
@endsection
