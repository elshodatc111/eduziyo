@extends('layouts.app')
@section('title', 'Hujjatlar va rozilik')

@section('content')
    <x-page-header title="Hujjatlar va rozilik" subtitle="Oferta, maxfiylik siyosati va foydalanish shartlari. E'lon qilingan versiya o'zgarmaydi — o'zgartirish yangi versiya bo'ladi">
        <x-slot:actions>
        <a href="{{ route('platform.legal.report') }}" class="btn-secondary">Rozilik hisoboti</a>
        <a href="{{ route('platform.legal.create') }}" class="btn-primary">Yangi hujjat</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Matnlarni e'lon qilishdan oldin yurist ko'rib chiqsin. E'lon qilinganda foydalanuvchilar keyingi kirishda rozilik berishi shart bo'ladi.</div>

    <div class="card overflow-hidden"><table class="table">
        <thead><tr><th>Hujjat</th><th>Kim uchun</th><th>Versiya</th><th>Holat</th><th>Kuchga kirgan</th><th></th></tr></thead>
        <tbody>
        @foreach ($docs as $d)
            <tr><td><div class="font-semibold">{{ $d->title }}</div><div class="text-xs text-ink-500">{{ $d->typeLabel() }}</div></td>
                <td>{{ $d->audienceLabel() }}</td><td>v{{ $d->version }}</td>
                <td><span class="{{ $d->isPublished() ? 'badge-green' : 'badge-amber' }}">{{ $d->isPublished() ? "E'lon qilingan" : 'Qoralama' }}</span></td>
                <td class="text-sm">{{ $d->effective_at?->format('d.m.Y') ?? '—' }}</td>
                <td class="space-x-2 whitespace-nowrap text-right">
                    @if ($d->isPublished())
                        <form method="POST" action="{{ route('platform.legal.version', $d->id) }}" class="inline">@csrf<button class="text-xs font-semibold text-brand-600">Yangi versiya</button></form>
                    @else
                        <a class="text-xs font-semibold text-brand-600" href="{{ route('platform.legal.edit', $d->id) }}">Tahrirlash</a>
                    @endif</td></tr>
        @endforeach
        </tbody></table></div>
@endsection
