@extends('layouts.cabinet')
@section('title', 'Xabarlar')
@section('content')
<h1 class="mb-4 text-lg font-extrabold text-ink-900 dark:text-white">Xabarlar</h1>
@forelse ($items as $r)
    <div class="card mb-3 {{ in_array($r->id, $unreadIds, true) ? 'ring-1 ring-brand-300' : '' }}"><div class="card-body">
        <div class="flex items-start justify-between gap-3">
            <div class="font-semibold text-ink-900 dark:text-white">{{ $r->notification?->title }}</div>
            <div class="shrink-0 text-xs text-ink-400">{{ $r->created_at->format('d.m.Y H:i') }}</div>
        </div>
        <p class="mt-1 whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{{ $r->notification?->body }}</p>
    </div></div>
@empty
    <div class="card"><div class="card-body text-sm text-ink-500">Xabarlar yo'q.</div></div>
@endforelse
{{ $items->links() }}
@endsection
