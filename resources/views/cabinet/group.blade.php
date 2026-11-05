@extends('layouts.cabinet')
@section('title', $group->name)
@section('content')
<a href="{{ route('cabinet.groups') }}" class="mb-3 inline-flex items-center gap-1 text-sm text-ink-500">&larr; Guruhlar</a>
<div class="card"><div class="card-body">
    <h1 class="text-lg font-extrabold text-ink-900 dark:text-white">{{ $group->name }}</h1>
    <dl class="mt-3 space-y-1.5 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-ink-500">Kurs</dt><dd class="text-right font-medium text-ink-800 dark:text-ink-100">{{ $group->course?->name }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-ink-500">Jadval</dt><dd class="text-right font-medium text-ink-800 dark:text-ink-100">{{ $group->schedule->label() }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-ink-500">Vaqt</dt><dd class="text-right font-medium text-ink-800 dark:text-ink-100">{{ $group->lessonTime?->label }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-ink-500">Xona</dt><dd class="text-right font-medium text-ink-800 dark:text-ink-100">{{ $group->room?->name ?? '—' }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-ink-500">O'qituvchi</dt><dd class="text-right font-medium text-ink-800 dark:text-ink-100">{{ $group->teacher?->name ?? '—' }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-ink-500">Muddat</dt><dd class="text-right font-medium text-ink-800 dark:text-ink-100">{{ $group->starts_on->format('d.m.Y') }} – {{ $group->ends_on->format('d.m.Y') }}</dd></div>
    </dl>
</div></div>

<h2 class="mb-2 mt-6 text-sm font-bold text-ink-900 dark:text-white">Davomatim</h2>
@if ($summary && $summary['rate'] !== null)
    <div class="card mb-3"><div class="card-body flex items-center justify-between text-sm">
        <span class="text-ink-500">Qatnashgan darslar</span>
        <span class="font-bold text-ink-900 dark:text-white">{{ $summary['present'] }} / {{ $summary['total'] }} ({{ $summary['rate'] }}%)</span>
    </div></div>
@endif
@forelse ($records as $r)
    @if ($loop->first)<div class="card divide-y divide-ink-100 dark:divide-ink-800">@endif
    <div class="flex items-center justify-between px-4 py-2.5 text-sm">
        <span class="text-ink-700 dark:text-ink-200">{{ $r->date->format('d.m.Y') }}</span>
        <span class="badge {{ $r->is_present ? 'badge-green' : 'badge-red' }}">{{ $r->is_present ? 'Keldi' : 'Kelmadi' }}</span>
    </div>
    @if ($loop->last)</div>@endif
@empty
    <div class="card"><div class="card-body text-sm text-ink-500">Davomat hali olinmagan.</div></div>
@endforelse
@endsection
