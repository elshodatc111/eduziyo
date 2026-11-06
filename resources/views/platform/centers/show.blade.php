@extends('layouts.app')
@section('title', $center->name)

@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

@section('content')
    <x-page-header :title="$center->name" :subtitle="'Direktor: '.$center->owner_name.' · '.$center->owner_phone">
        <x-slot:actions><a href="{{ route('platform.directors', $center->id) }}" class="btn-secondary">Direktorlar</a> <a href="{{ route('platform.centers.index') }}" class="btn-secondary">← Markazlar</a></x-slot:actions>
    </x-page-header>

    @if (session('credentials'))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
            Direktor kirish ma'lumoti (faqat hozir ko'rinadi): login <b class="font-mono">{{ session('credentials')['login'] }}</b>, parol <b class="font-mono">{{ session('credentials')['password'] }}</b>
        </div>
    @endif

    @if (session('owner_link'))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
            Direktorga shu havolani yuboring (7 kun amal qiladi, bir marta ishlaydi). U botda «Start» bosganda login va parolni o'zi oladi:
            <div class="mt-1 break-all font-mono text-xs">{{ session('owner_link') }}</div>
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card card-body"><div class="text-xs text-ink-500">Holat</div><div class="mt-1"><span class="{{ $center->status === \App\Enums\CenterStatus::Active ? 'badge-green' : 'badge-red' }}">{{ $center->status->label() }}</span></div></div>
        <div class="card card-body"><div class="text-xs text-ink-500">Tarif</div><div class="mt-1 font-bold text-ink-900 dark:text-white">{{ $limits['unlimited'] ? 'Cheksiz (eski)' : ($limits['name'] ?? '—') }}</div><div class="text-xs text-ink-500">{{ $center->tariff_ends_at?->format('d.m.Y') }}</div></div>
        <div class="card card-body"><div class="text-xs text-ink-500">Balans</div><div class="mt-1 text-lg font-extrabold text-ink-900 dark:text-white">{{ $fmt($a['totals']['balance']) }}</div></div>
        <div class="card card-body"><div class="text-xs text-ink-500">Limit (filial / o'quvchi)</div><div class="mt-1 font-bold text-ink-900 dark:text-white">{{ $limits['max_branches'] ?? '∞' }} / {{ $limits['max_students'] ?? '∞' }}</div></div>
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <form method="POST" action="{{ route('platform.centers.grant', $center) }}" class="card card-body space-y-3">@csrf
            <h2 class="font-bold text-ink-900 dark:text-white">Tarif sovg'a qilish</h2>
            <div><label class="label" for="tariff_id">Tarif</label><select id="tariff_id" name="tariff_id" class="input">@foreach ($tariffs as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
            <x-input name="days" type="number" min="1" max="3660" label="Necha kun" :value="30" required />
            <x-input name="note" label="Izoh" />
            <button class="btn-primary btn-sm w-fit">Berish</button>
        </form>
        <form method="POST" action="{{ route('platform.centers.adjust', $center) }}" class="card card-body space-y-3" onsubmit="return confirm('Markaz balansi o\'zgartiriladi. Davom etasizmi?')">@csrf
            <h2 class="font-bold text-ink-900 dark:text-white">Balansni tuzatish</h2>
            <x-input name="amount" type="number" label="Summa (+ qo'shish, − ayirish)" required />
            <x-input name="reason" label="Sabab" required />
            <button class="btn-secondary btn-sm w-fit">Tuzatish</button>
        </form>
        <div class="card card-body space-y-3">
            <h2 class="font-bold text-ink-900 dark:text-white">Holat</h2>
            @if ($center->status === \App\Enums\CenterStatus::Suspended)
                <form method="POST" action="{{ route('platform.centers.resume', $center) }}">@csrf<button class="btn-primary btn-sm">Tiklash</button></form>
            @else
                <form method="POST" action="{{ route('platform.centers.suspend', $center) }}" onsubmit="return confirm('Markaz to\'xtatilib, xodimlar tizimdan chiqariladi. Davom etasizmi?')">@csrf<button class="btn-secondary btn-sm">To'xtatish</button></form>
            @endif
            <p class="text-xs text-ink-500">To'xtatilgan markaz xodimlari tizimga kira olmaydi; ma'lumot saqlanadi.</p>
        </div>
    </div>

    @unless ($center->is_demo)
        <form method="POST" action="{{ route('platform.centers.destroy', $center) }}" class="card card-body mb-6 max-w-2xl space-y-3 border-red-200 dark:border-red-900" onsubmit="return confirm('Markaz, uning BARCHA filiallari, xodimlari, o\'quvchilari va to\'lovlari butunlay o\'chadi. Qaytarib bo\'lmaydi. Davom etasizmi?')">@csrf @method('DELETE')
            <h2 class="font-bold text-red-700">Markazni o'chirish</h2>
            <p class="text-sm text-ink-600 dark:text-ink-300">Markaz, barcha filiallari va ma'lumotlari (foydalanuvchilar, guruhlar, to'lovlar, murojaatlar, fayllar) <b>butunlay o'chadi</b>. Foydalanuvchilarning hujjatlarga bergan roziligi jurnali saqlanib qoladi. Avval markaz Excel'ini yuklab olishni unutmang.</p>
            <x-input name="confirm_name" label="Tasdiqlash uchun markaz nomini yozing: {{ $center->name }}" required />
            <x-input name="password" type="password" label="Sizning parolingiz" required autocomplete="current-password" />
            <button class="btn-primary btn-sm w-fit !bg-red-600 hover:!bg-red-700">Markazni butunlay o'chirish</button>
        </form>
    @endunless

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden"><div class="border-b border-ink-100 px-5 py-3 font-bold text-ink-900 dark:border-ink-800 dark:text-white">Obunalar</div>
            <table class="table"><tbody>
                @forelse ($history as $h)<tr><td>{{ $h->name() }}<div class="text-xs text-ink-500">{{ $h->source === 'grant' ? 'sovg\'a' : 'sotib olingan' }}</div></td><td>{{ $h->starts_at->format('d.m.Y') }} — {{ $h->ends_at->format('d.m.Y') }}</td><td class="text-right">{{ $fmt($h->price_paid) }}</td></tr>@empty<tr><td class="text-center text-ink-500">Yo'q</td></tr>@endforelse
            </tbody></table></div>
        <div class="card overflow-hidden"><div class="border-b border-ink-100 px-5 py-3 font-bold text-ink-900 dark:border-ink-800 dark:text-white">Balans harakatlari</div>
            <table class="table"><tbody>
                @forelse ($txns as $t)<tr><td>{{ $t->created_at->format('d.m.Y H:i') }}<div class="text-xs text-ink-500">{{ $t->typeLabel() }}{{ $t->note ? ' · '.$t->note : '' }}</div></td><td class="text-right font-semibold {{ $t->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $t->amount >= 0 ? '+' : '' }}{{ $fmt($t->amount) }}</td></tr>@empty<tr><td class="text-center text-ink-500">Yo'q</td></tr>@endforelse
            </tbody></table></div>
    </div>

    @include('center._analytics-body', ['a' => $a])
@endsection
