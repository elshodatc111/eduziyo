@extends('layouts.app')
@section('title', 'Markaz')

@php
    $fmt = fn ($n) => number_format((int) $n, 0, '', ' ');
    $lim = fn ($v) => $v === null ? 'cheksiz' : $v;
    $bar = fn ($used, $limit) => $limit ? min(100, (int) round($used * 100 / $limit)) : 0;
@endphp

@section('content')
    <x-page-header :title="$center->name" subtitle="Obuna, balans va limitlar" />
    @include('center._nav')

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-body">
            <div class="text-xs text-ink-500">Holat</div>
            <div class="mt-1"><span class="{{ $center->status->isWritable() ? 'badge-green' : 'badge-red' }}">{{ $center->status->label() }}</span></div>
            <div class="mt-2 text-xs text-ink-500">{{ $limits['unlimited'] ? 'Cheksiz (eski markaz)' : ($limits['name'] ?? 'Tarif yo\'q') }}</div>
        </div>
        <div class="card card-body">
            <div class="text-xs text-ink-500">Tarif tugashi</div>
            <div class="mt-1 text-lg font-extrabold text-ink-900 dark:text-white">{{ $center->tariff_ends_at?->format('d.m.Y') ?? '—' }}</div>
            @if ($center->delete_after)<div class="mt-1 text-xs text-red-600">O'chirilish: {{ $center->delete_after->format('d.m.Y') }}</div>@endif
        </div>
        <div class="card card-body">
            <div class="text-xs text-ink-500">Markaz balansi</div>
            <div class="mt-1 text-lg font-extrabold text-ink-900 dark:text-white">{{ $fmt($balance) }} so'm</div>
            <a href="{{ route('center.topup') }}" class="mt-2 text-xs font-semibold text-brand-600">To'ldirish →</a>
        </div>
        <div class="card card-body">
            <div class="text-xs text-ink-500">Navbatdagi tarif</div>
            <div class="mt-1 text-sm font-semibold text-ink-900 dark:text-white">{{ $upcoming->first()?->name() ?? '—' }}</div>
            @if ($upcoming->first())<div class="text-xs text-ink-500">{{ $upcoming->first()->starts_at->format('d.m.Y') }} dan</div>@endif
        </div>
    </div>

    <div class="mb-6 card card-body">
        <h2 class="mb-4 font-bold text-ink-900 dark:text-white">Limitlar</h2>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['Filiallar', $branches, $limits['max_branches']], ["Faol o'quvchilar", $students, $limits['max_students']], ['SMS (shu oy)', $smsUsed, $limits['sms_monthly']], ['AI (shu oy)', $aiUsed, $limits['ai_monthly']]] as [$label, $used, $limit])
                <div>
                    <div class="flex justify-between text-sm"><span class="text-ink-600 dark:text-ink-300">{{ $label }}</span><b>{{ $used }} / {{ $lim($limit) }}</b></div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full {{ $bar($used, $limit) >= 90 ? 'bg-red-500' : 'bg-brand-600' }}" style="width: {{ $bar($used, $limit) }}%"></div></div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b border-ink-100 px-5 py-3 font-bold text-ink-900 dark:border-ink-800 dark:text-white">Balans harakatlari</div>
            <table class="table"><tbody>
                @forelse ($txns as $t)
                    <tr><td>{{ $t->created_at->format('d.m.Y H:i') }}<div class="text-xs text-ink-500">{{ $t->typeLabel() }}{{ $t->note ? ' · '.$t->note : '' }}</div></td>
                        <td class="text-right font-semibold {{ $t->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $t->amount >= 0 ? '+' : '' }}{{ $fmt($t->amount) }}</td></tr>
                @empty
                    <tr><td class="text-center text-ink-500">Hozircha harakat yo'q.</td></tr>
                @endforelse
            </tbody></table>
        </div>
        <div class="card card-body">
            <h2 class="mb-3 font-bold text-ink-900 dark:text-white">Ma'lumotni yuklab olish (Excel)</h2>
            @if ($exportAllowed)
                <div class="flex flex-wrap gap-2">
                    <a class="btn-secondary btn-sm" href="{{ route('center.export', 'students') }}">O'quvchilar</a>
                    <a class="btn-secondary btn-sm" href="{{ route('center.export', 'payments') }}">To'lovlar</a>
                    <a class="btn-secondary btn-sm" href="{{ route('center.export', 'groups') }}">Guruhlar</a>
                </div>
            @else
                <p class="text-sm text-ink-500">Tarif tugagach eksport platforma sozlamasiga ko'ra yopilgan.</p>
            @endif
            <p class="mt-3 text-xs text-ink-500">Tarif tugab, 30 kun ichida yangilanmasa markaz ma'lumoti butunlay o'chiriladi. Oldindan nusxa oling.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('center.logo.update') }}" enctype="multipart/form-data" class="mt-6 card card-body flex flex-wrap items-end gap-4">@csrf
        <x-logo :center="$center" class="h-14 w-14" />
        <div class="min-w-[14rem] flex-1"><label class="label" for="logo">Markaz logotipi (PNG, JPG, WebP; 1 MB gacha)</label>
            <input id="logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp" class="input" required>@error('logo')<p class="error-text">{{ $message }}</p>@enderror</div>
        <button class="btn-primary btn-sm">Yuklash</button>
        @if ($center->logo_path)<button class="btn-secondary btn-sm" form="logo-del">Olib tashlash</button>@endif
    </form>
    @if ($center->logo_path)<form id="logo-del" method="POST" action="{{ route('center.logo.destroy') }}">@csrf @method('DELETE')</form>@endif
@endsection
