@extends('layouts.cabinet')
@section('title', 'Guruhlarim')
@section('content')
<h1 class="mb-4 text-lg font-extrabold text-ink-900 dark:text-white">Guruhlarim</h1>
@forelse ($groups as $g)
    @php $r = $rates[$g->id] ?? null; @endphp
    <a href="{{ route('cabinet.groups.show', $g) }}" class="card mb-3 block"><div class="card-body">
        <div class="flex items-center justify-between gap-2">
            <div class="truncate font-semibold text-ink-900 dark:text-white">{{ $g->name }}</div>
            <span class="badge badge-gray">{{ $g->status_label }}</span>
        </div>
        <div class="mt-1 text-xs text-ink-500">{{ $g->course?->name }} · {{ $g->schedule->label() }} · {{ $g->lessonTime?->label }}</div>
        <div class="mt-1 text-xs text-ink-500">O'qituvchi: {{ $g->teacher?->name ?? '—' }} · {{ $g->starts_on->format('d.m.Y') }} – {{ $g->ends_on->format('d.m.Y') }}</div>
        @if ($r && $r['rate'] !== null)
            <div class="mt-3 flex items-center gap-2">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-brand-600" style="width: {{ $r['rate'] }}%"></div></div>
                <span class="text-xs font-semibold text-ink-700 dark:text-ink-200">{{ $r['rate'] }}%</span>
            </div>
        @endif
    </div></a>
@empty
    <div class="card"><div class="card-body text-sm text-ink-500">Siz hali guruhga qo'shilmagansiz.</div></div>
@endforelse
@endsection
