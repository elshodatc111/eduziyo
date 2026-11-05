@extends('layouts.cabinet')
@section('title', 'Kurslar')
@section('content')
<h1 class="mb-4 text-lg font-extrabold text-ink-900 dark:text-white">Kurslarim</h1>
@forelse ($courses as $c)
    <a href="{{ route('cabinet.courses.show', $c) }}" class="card mb-3 block"><div class="card-body">
        <div class="font-semibold text-ink-900 dark:text-white">{{ $c->name }}</div>
        <div class="mt-1 text-xs text-ink-500">{{ $c->videos_count }} video · {{ $c->audios_count }} audio · {{ $c->questions_count }} test savoli</div>
        @if (isset($best[$c->id]))<div class="mt-2 text-xs text-ink-600 dark:text-ink-300">Eng yaxshi natija: <b>{{ $best[$c->id]->best }}%</b> ({{ $best[$c->id]->attempts }} urinish)</div>@endif
    </div></a>
@empty
    <div class="card"><div class="card-body text-sm text-ink-500">Faol guruhingiz kurslari shu yerda ko'rinadi.</div></div>
@endforelse

@if ($books->isNotEmpty())
    <h2 class="mb-2 mt-6 text-sm font-bold text-ink-900 dark:text-white">Kitoblar</h2>
    <div class="card divide-y divide-ink-100 dark:divide-ink-800">
        @foreach ($books as $b)
            <a href="{{ $b->url }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 px-4 py-3 text-sm text-ink-800 dark:text-ink-100"><x-icon name="book" class="h-5 w-5 text-brand-600" />{{ $b->name }}</a>
        @endforeach
    </div>
@endif
@endsection
