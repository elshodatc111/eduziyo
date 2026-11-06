@extends('layouts.app')
@section('title', "Balans to'ldirish")

@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

@section('content')
    @if ($focus)<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('topup-{{ $focus }}')?.scrollIntoView({block:'center'}));</script>@endif
    <x-page-header title="Balans to'ldirish so'rovlari" subtitle="Chekni tekshirib tasdiqlang yoki rad eting" />

    <div class="mb-6 space-y-3">
        @forelse ($pending as $r)
            <div id="topup-{{ $r->id }}" class="card card-body {{ $focus === $r->id ? 'ring-2 ring-brand-500' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="font-bold text-ink-900 dark:text-white">{{ $centers[$r->center_id] ?? '—' }}</div>
                        <div class="text-sm text-ink-600 dark:text-ink-300">{{ $fmt($r->amount_claimed) }} so'm · {{ $r->paid_at?->format('d.m.Y H:i') }} · karta {{ $r->card?->number }}</div>
                        @if ($r->note)<div class="text-xs text-ink-500">{{ $r->note }}</div>@endif
                        <a class="mt-1 inline-block text-xs font-semibold text-brand-600" href="{{ route('platform.topups.receipt', $r->id) }}" target="_blank">Chekni ko'rish</a>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <form method="POST" action="{{ route('platform.topups.approve', $r->id) }}" class="flex items-end gap-2" onsubmit="return confirm('Chek tekshirildimi? Balans to\'ldiriladi.')">@csrf
                            <x-input name="approved_amount" money label="Tasdiqlangan summa" :value="$r->amount_claimed" required />
                            <button class="btn-primary btn-sm">Tasdiqlash</button>
                        </form>
                        <form method="POST" action="{{ route('platform.topups.reject', $r->id) }}" class="flex items-end gap-2">@csrf
                            <x-input name="reject_reason" label="Rad etish sababi" required />
                            <button class="btn-secondary btn-sm">Rad etish</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="card card-body text-center text-sm text-ink-500">Kutilayotgan so'rov yo'q.</div>
        @endforelse
    </div>

    <div class="mb-6 card overflow-hidden"><div class="border-b border-ink-100 px-5 py-3 font-bold text-ink-900 dark:border-ink-800 dark:text-white">Oxirgi qarorlar</div>
        <table class="table"><tbody>
            @forelse ($done as $r)<tr><td>{{ $centers[$r->center_id] ?? '—' }}</td><td>{{ $fmt($r->approved_amount ?? $r->amount_claimed) }}</td><td><span class="{{ $r->status === 'approved' ? 'badge-green' : 'badge-red' }}">{{ $r->statusLabel() }}</span></td><td class="text-xs text-ink-500">{{ $r->decided_at?->format('d.m.Y H:i') }}</td></tr>@empty<tr><td class="text-center text-ink-500">Yo'q</td></tr>@endforelse
        </tbody></table></div>

    <div class="card card-body">
        <h2 class="mb-3 font-bold text-ink-900 dark:text-white">To'lov kartalari</h2>
        @foreach ($cards as $c)
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-ink-200 p-3 text-sm dark:border-ink-800">
                <div><span class="font-mono font-bold">{{ $c->number }}</span> · {{ $c->holder }} {{ $c->bank ? '· '.$c->bank : '' }} <span class="{{ $c->is_active ? 'badge-green' : 'badge-gray' }}">{{ $c->is_active ? 'Faol' : 'Yopiq' }}</span></div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('platform.cards.toggle', $c) }}">@csrf<button class="btn-secondary btn-sm">{{ $c->is_active ? "O'chirib qo'yish" : 'Yoqish' }}</button></form>
                    <form method="POST" action="{{ route('platform.cards.destroy', $c) }}" onsubmit="return confirm('Karta o\'chirilsinmi?')">@csrf @method('DELETE')<button class="btn-secondary btn-sm">O'chirish</button></form>
                </div>
            </div>
        @endforeach
        <form method="POST" action="{{ route('platform.cards.store') }}" class="mt-3 grid gap-3 sm:grid-cols-4">@csrf
            <x-input name="number" label="Karta raqami" required /><x-input name="holder" label="Egasi" required /><x-input name="bank" label="Bank" /><div class="flex items-end"><button class="btn-primary btn-sm">Qo'shish</button></div>
        </form>
    </div>
@endsection
