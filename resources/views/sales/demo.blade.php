@extends('layouts.app')
@section('title', 'Demo kirishlar')

@section('content')
    <x-page-header title="Demo kirishlar" subtitle="So'nggi 30 kun: sotuv sahifasidan rol tanlab demo'ga kirganlar">
        <x-slot:actions>
        <form method="POST" action="{{ route('sales.demo.reset') }}" onsubmit="return confirm('Demo markaz tozalanib, qayta yaratiladi. Davom etasizmi?')">@csrf
            <button class="btn-secondary">Demo'ni hozir tiklash</button>
        </form>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="card card-body"><div class="text-xs text-ink-500">Jami kirishlar (30 kun)</div><div class="mt-1 text-2xl font-extrabold text-ink-900 dark:text-white">{{ $total30 }}</div></div>
        <div class="card card-body"><div class="text-xs text-ink-500">Holat</div><div class="mt-1"><span class="{{ $available ? 'badge-green' : 'badge-red' }}">{{ $available ? 'Tayyor' : "Tayyor emas (tiklab ko'ring yoki yoqing)" }}</span></div></div>
        <div class="card card-body"><div class="text-xs text-ink-500">Oxirgi qo'lda tiklash</div><div class="mt-1 text-sm font-semibold text-ink-900 dark:text-white">{{ $lastReset ?? '—' }}</div><div class="mt-1 text-xs text-ink-400">Avtomatik: har tun 03:10</div></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b border-ink-100 px-5 py-3 font-bold text-ink-900 dark:border-ink-800 dark:text-white">Rollar bo'yicha</div>
            <table class="table"><tbody>
                @foreach ($roles as $key => [$label])
                    <tr><td>{{ $label }}</td><td class="text-right font-semibold">{{ $byRole[$key] ?? 0 }}</td></tr>
                @endforeach
            </tbody></table>
        </div>
        <div class="card overflow-hidden">
            <div class="border-b border-ink-100 px-5 py-3 font-bold text-ink-900 dark:border-ink-800 dark:text-white">Kunlar bo'yicha (14 kun)</div>
            <table class="table"><tbody>
                @forelse ($byDay as $day => $n)
                    <tr><td>{{ \Illuminate\Support\Carbon::parse($day)->format('d.m.Y') }}</td><td class="text-right font-semibold">{{ $n }}</td></tr>
                @empty
                    <tr><td class="text-center text-ink-500">Hozircha kirish yo'q.</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>
@endsection
