@extends('layouts.app')
@section('title', 'Markazlar')

@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

@section('content')
    <x-page-header title="Markazlar" subtitle="Platformadagi barcha o'quv markazlari">
        <x-slot:actions><a href="{{ route('platform.centers.create') }}" class="btn-primary">+ Yangi markaz</a></x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Jami markaz', $summary['centers']], ['Faol', $summary['active']], ["Faqat ko'rish / kutilmoqda", $summary['read_only'].' / '.$summary['pending']], ["Tarif sotuvi (30 kun)", $fmt($summary['sales30']).' so\'m']] as [$l, $v])
            <div class="card card-body"><div class="text-xs text-ink-500">{{ $l }}</div><div class="mt-1 text-xl font-extrabold text-ink-900 dark:text-white">{{ $v }}</div></div>
        @endforeach
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Markaz</th><th>Holat</th><th>Tarif</th><th>Tugashi</th><th class="text-right">O'quvchi</th><th class="text-right">Balans</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    @php $c = $r['center']; @endphp
                    <tr>
                        <td class="font-semibold">{{ $c->name }}<div class="text-xs text-ink-500">{{ $c->owner_name }}</div></td>
                        <td><span class="{{ $c->status === \App\Enums\CenterStatus::Active ? 'badge-green' : 'badge-red' }}">{{ $c->status->label() }}</span></td>
                        <td>{{ $r['tariff'] }}</td>
                        <td>{{ $c->tariff_ends_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="text-right">{{ $r['students'] }}</td>
                        <td class="text-right">{{ $fmt($r['balance']) }}</td>
                        <td class="text-right"><a class="btn-secondary btn-sm" href="{{ route('platform.centers.show', $c) }}">Ochish</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">Markaz yo'q.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
