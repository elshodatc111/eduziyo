@extends('layouts.app')
@section('title', 'Tariflar')

@section('content')
    <x-page-header title="Tariflar" subtitle="3 ta tarif. Sotuv sahifasidagi narx va limitlar shu yerdan olinadi" />

    @if ($tariffs->contains(fn ($t) => ! $t->hasPrice()))
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Narxi kiritilmagan tarif sotuv sahifasida «Narxni bilish» deb ko'rinadi. Tahrirlash orqali narxni kiriting.</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        @foreach ($tariffs as $t)
            <div class="card card-body">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ $t->name }}</h2>
                    @if ($t->is_featured)<span class="badge-red">Mashhur</span>@endif
                    @unless ($t->is_active)<span class="badge-gray">Yashirin</span>@endunless
                </div>
                <div class="mt-2 text-sm text-ink-600 dark:text-ink-300">
                    Oylik: <b>{{ $t->price_monthly ? number_format($t->price_monthly, 0, '', ' ').' so\'m' : '—' }}</b><br>
                    Yillik: <b>{{ $t->price_yearly ? number_format($t->price_yearly, 0, '', ' ').' so\'m' : '—' }}</b>
                </div>
                <ul class="mt-3 space-y-1 text-xs text-ink-500">
                    <li>Filial: {{ $t->max_branches ?? 'cheksiz' }}</li>
                    <li>O'quvchi: {{ $t->max_students ?? 'cheksiz' }}</li>
                    <li>SMS / oy: {{ $t->sms_monthly }}</li>
                    <li>AI / oy: {{ $t->ai_monthly }}</li>
                </ul>
                <a href="{{ route('sales.tariffs.edit', $t) }}" class="btn-secondary btn-sm mt-4 w-fit">Tahrirlash</a>
            </div>
        @endforeach
    </div>
@endsection
