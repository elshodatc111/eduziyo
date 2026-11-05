@extends('layouts.app')
@section('title', 'Ish haqim')

@section('content')
    @php $fm = fn ($n) => \App\Support\Format::money($n, false); $remaining = collect($accruals)->sum(fn ($a) => max(0, $a['remaining'])); @endphp
    <x-page-header title="Ish haqim" subtitle="Guruhlar bo'yicha hisoblangan va to'langan summalar (faqat ko'rish)" />

    <div class="mb-4 grid gap-3 sm:grid-cols-2">
        <div class="card card-body"><div class="text-sm text-ink-500">To'lanishi kerak (davomat bo'yicha)</div><div class="mt-1 text-2xl font-bold text-brand-600">{{ $fm($remaining) }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Shu oyda to'langan</div><div class="mt-1 text-2xl font-bold">{{ $fm($payouts->filter(fn ($p) => $p->created_at->isCurrentMonth())->sum('amount')) }}</div></div>
    </div>

    <div class="card mb-6 overflow-hidden"><div class="table-wrap"><table class="table">
        <thead><tr><th>Guruh</th><th>O'quvchi</th><th>Darslar</th><th class="text-right">Hisoblangan</th><th class="text-right">To'langan</th><th class="text-right">Qoldiq</th></tr></thead>
        <tbody>
        @forelse ($accruals as $a)
            <tr><td class="font-medium">{{ $a['group']->name }}</td><td>{{ $a['students'] }}</td><td>{{ $a['held'] }} / {{ $a['group']->lesson_count }}</td>
                <td class="text-right">{{ $fm($a['accrued_by_attendance']) }}</td><td class="text-right">{{ $fm($a['paid']) }}</td>
                <td class="text-right font-semibold {{ $a['remaining'] > 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ $fm($a['remaining']) }}</td></tr>
        @empty<tr><td colspan="6" class="py-8 text-center text-ink-500">So'nggi 31 kunda guruh yo'q.</td></tr>@endforelse
        </tbody></table></div></div>

    <h2 class="mb-2 font-bold">So'nggi to'lovlar</h2>
    <div class="card overflow-hidden"><table class="table">
        <thead><tr><th>Sana</th><th>Guruh</th><th class="text-right">Summa</th></tr></thead>
        <tbody>
        @forelse ($payouts as $p)<tr><td>{{ $p->created_at->format('d.m.Y H:i') }}</td><td>{{ $p->group?->name ?? '—' }}</td><td class="text-right">{{ $fm($p->amount) }}</td></tr>
        @empty<tr><td colspan="3" class="py-6 text-center text-ink-500">To'lov yo'q.</td></tr>@endforelse
        </tbody></table></div>
@endsection
