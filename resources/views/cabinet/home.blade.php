@extends('layouts.cabinet')
@section('title', 'Bosh sahifa')
@section('content')
@php $debt = max(0, -$me->balance); @endphp

<div class="grid grid-cols-2 gap-3">
    <div class="card"><div class="card-body">
        <div class="text-xs font-medium text-ink-500">Balans</div>
        <div class="mt-1 text-xl font-extrabold {{ $me->balance < 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ \App\Support\Format::money($me->balance) }}</div>
        @if ($debt > 0)<div class="mt-1 text-xs text-brand-600">Qarzingiz: {{ \App\Support\Format::money($debt) }}</div>@endif
    </div></div>
    <div class="card"><div class="card-body">
        <div class="text-xs font-medium text-ink-500">Davomat</div>
        <div class="mt-1 text-xl font-extrabold text-ink-900 dark:text-white">{{ $overall !== null ? $overall.'%' : '—' }}</div>
        <div class="mt-1 text-xs text-ink-500">barcha guruhlar bo'yicha</div>
    </div></div>
</div>

@if (!empty($botLink))
    <a href="{{ $botLink }}" target="_blank" rel="noopener" class="card mt-4 block"><div class="card-body text-sm">
        <div class="font-semibold text-ink-900 dark:text-white">📲 Telegram bot</div>
        <div class="text-ink-500">Dars jadvali, qarz va davomat xabarlari uchun botga ulaning; ota-onangiz ham o'z raqami bilan ulanishi mumkin: <b>/ulanish</b></div>
    </div></a>
@endif

<h2 class="mb-2 mt-6 text-sm font-bold text-ink-900 dark:text-white">Bugungi darslar</h2>
@php $todayGroups = $groups->whereIn('id', $todayIds); @endphp
@forelse ($todayGroups as $g)
    <a href="{{ route('cabinet.groups.show', $g) }}" class="card mb-2 block"><div class="card-body flex items-center gap-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950"><x-icon name="calendar" /></span>
        <div class="min-w-0 flex-1">
            <div class="truncate font-semibold text-ink-900 dark:text-white">{{ $g->name }}</div>
            <div class="truncate text-xs text-ink-500">{{ $g->lessonTime?->label }} · {{ $g->room?->name }} · {{ $g->teacher?->name }}</div>
        </div>
    </div></a>
@empty
    <div class="card"><div class="card-body text-sm text-ink-500">Bugun darsingiz yo'q.</div></div>
@endforelse

<h2 class="mb-2 mt-6 text-sm font-bold text-ink-900 dark:text-white">Guruhlarim</h2>
@forelse ($groups as $g)
    <a href="{{ route('cabinet.groups.show', $g) }}" class="card mb-2 block"><div class="card-body">
        <div class="flex items-center justify-between gap-2">
            <div class="truncate font-semibold text-ink-900 dark:text-white">{{ $g->name }}</div>
            <span class="badge badge-gray">{{ $g->status_label }}</span>
        </div>
        <div class="mt-1 text-xs text-ink-500">{{ $g->course?->name }} · {{ $g->schedule->label() }} · {{ $g->lessonTime?->label }}</div>
    </div></a>
@empty
    <div class="card"><div class="card-body text-sm text-ink-500">Hozircha faol guruhingiz yo'q.</div></div>
@endforelse

@if ($recent->isNotEmpty())
    <div class="mb-2 mt-6 flex items-center justify-between"><h2 class="text-sm font-bold text-ink-900 dark:text-white">So'nggi harakatlar</h2><a href="{{ route('cabinet.payments') }}" class="text-xs font-semibold text-brand-600">Hammasi</a></div>
    <div class="card divide-y divide-ink-100 dark:divide-ink-800">
        @foreach ($recent as $t)
            <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <div class="min-w-0"><div class="truncate text-ink-800 dark:text-ink-100">{{ $t->typeLabel() }}</div><div class="text-xs text-ink-500">{{ $t->created_at->format('d.m.Y H:i') }}</div></div>
                <div class="font-semibold {{ $t->amount < 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ $t->amount > 0 ? '+' : '' }}{{ \App\Support\Format::money($t->amount) }}</div>
            </div>
        @endforeach
    </div>
@endif
@endsection
