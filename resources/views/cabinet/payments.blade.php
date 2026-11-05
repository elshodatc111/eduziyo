@extends('layouts.cabinet')
@section('title', "To'lovlar")
@section('content')
@php $debt = max(0, -$me->balance); @endphp
<div class="card mb-5"><div class="card-body">
    <div class="text-xs font-medium text-ink-500">Joriy balans</div>
    <div class="mt-1 text-3xl font-extrabold {{ $me->balance < 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ \App\Support\Format::money($me->balance) }}</div>
    @if ($debt > 0)<div class="mt-1 text-sm text-brand-600">Qarzingiz: {{ \App\Support\Format::money($debt) }}. To'lov uchun o'quv markaziga murojaat qiling.</div>@endif
</div></div>

<h2 class="mb-2 text-sm font-bold text-ink-900 dark:text-white">Balans harakatlari</h2>
@if ($transactions->isEmpty())
    <div class="card"><div class="card-body text-sm text-ink-500">Harakatlar hali yo'q.</div></div>
@else
    <div class="card divide-y divide-ink-100 dark:divide-ink-800">
        @foreach ($transactions as $t)
            <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <div class="min-w-0">
                    <div class="truncate text-ink-800 dark:text-ink-100">{{ $t->typeLabel() }}@if ($t->group) · {{ $t->group->name }}@endif</div>
                    <div class="text-xs text-ink-500">{{ $t->created_at->format('d.m.Y H:i') }} · qoldiq {{ \App\Support\Format::money($t->balance_after) }}</div>
                </div>
                <div class="shrink-0 font-semibold {{ $t->amount < 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ $t->amount > 0 ? '+' : '' }}{{ \App\Support\Format::money($t->amount) }}</div>
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
@endif
@endsection
