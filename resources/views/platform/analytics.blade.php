@extends('layouts.app')
@section('title', 'Platforma tahlili')

@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

@section('content')
    <x-page-header title="Platforma tahlili" subtitle="Har bir markaz bo'yicha asosiy ko'rsatkichlar (shaxsiy ma'lumotsiz)" />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Markazlar', $summary['centers']], ["Faol o'quvchilar", $summary['students']], ['Tarif sotuvi (30 kun)', $fmt($summary['sales30'])], ["Balans to'ldirish (30 kun)", $fmt($summary['topups30'])]] as [$l, $v])
            <div class="card card-body"><div class="text-xs text-ink-500">{{ $l }}</div><div class="mt-1 text-xl font-extrabold text-ink-900 dark:text-white">{{ $v }}</div></div>
        @endforeach
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Markaz</th><th>Tarif</th><th class="text-right">O'quvchi</th><th class="text-right">Filial</th><th class="text-right">Tushum (30 k.)</th><th class="text-right">SMS</th><th class="text-right">AI</th><th>Oxirgi kirish</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr><td class="font-semibold"><a href="{{ route('platform.centers.show', $r['center']) }}" class="text-brand-600">{{ $r['center']->name }}</a></td><td>{{ $r['tariff'] }}</td><td class="text-right">{{ $r['students'] }}</td><td class="text-right">{{ $r['branches'] }}</td><td class="text-right">{{ $fmt($r['revenue30']) }}</td><td class="text-right">{{ $r['sms'] }}</td><td class="text-right">{{ $r['ai'] }}</td><td>{{ $r['last_login']?->format('d.m.Y') ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="8" class="text-center text-ink-500">Markaz yo'q.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
