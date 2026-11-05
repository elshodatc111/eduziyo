@extends('layouts.app')
@section('title', 'Balans')

@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

@section('content')
    <x-page-header title="Balansni to'ldirish" subtitle="Kartaga o'tkazing, chekni yuklang — administrator tasdiqlagach balansga tushadi" />
    @include('center._nav')

    <div class="mb-5 rounded-xl border border-ink-200 bg-white px-4 py-3 text-sm dark:border-ink-800 dark:bg-ink-900">Hozirgi balans: <b>{{ $fmt($balance) }} so'm</b></div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card card-body">
            <h2 class="mb-3 font-bold text-ink-900 dark:text-white">To'lov kartalari</h2>
            <div class="space-y-3">
            @forelse ($cards as $c)
                <x-pay-card :card="$c" :tone="$loop->index" />
            @empty
                <p class="text-sm text-ink-500">Hozircha karta kiritilmagan. Platforma administratoriga murojaat qiling.</p>
            @endforelse
            </div>
            @if (count($cards))<p class="mt-3 text-xs text-ink-500">«Nusxalash» tugmasi karta raqamini bank ilovasiga qo'yish uchun nusxalaydi.</p>@endif
        </div>

        <form method="POST" action="{{ route('center.topup.store') }}" enctype="multipart/form-data" class="card card-body space-y-4">
            @csrf
            <div>
                <label class="label" for="card_id">Qaysi kartaga to'lagansiz</label>
                <select id="card_id" name="card_id" class="input" required>@foreach ($cards as $c)<option value="{{ $c->id }}">{{ trim(chunk_split(preg_replace('/\D+/', '', $c->number), 4, ' ')) }} — {{ $c->holder }}</option>@endforeach</select>
                @error('card_id')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <x-input name="amount_claimed" money label="Summa (so'm)" required />
            <x-input name="paid_at" type="datetime-local" label="To'lov vaqti" :value="now()->format('Y-m-d\TH:i')" required />
            <div>
                <label class="label" for="receipt">Chek (JPG, PNG yoki PDF, 5 MB gacha)</label>
                <input id="receipt" name="receipt" type="file" accept=".jpg,.jpeg,.png,.pdf" class="input" required>
                @error('receipt')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <x-input name="note" label="Izoh (ixtiyoriy)" />
            <button class="btn-primary w-fit">So'rov yuborish</button>
        </form>
    </div>

    <div class="mt-6 card overflow-hidden">
        <div class="border-b border-ink-100 px-5 py-3 font-bold text-ink-900 dark:border-ink-800 dark:text-white">So'rovlarim</div>
        <table class="table"><tbody>
            @forelse ($requests as $r)
                <tr><td>{{ $r->created_at->format('d.m.Y H:i') }}</td><td>{{ $fmt($r->amount_claimed) }} so'm</td>
                    <td><span class="{{ ['pending' => 'badge-gray', 'approved' => 'badge-green', 'rejected' => 'badge-red'][$r->status] }}">{{ $r->statusLabel() }}</span>@if ($r->reject_reason)<div class="text-xs text-red-600">{{ $r->reject_reason }}</div>@endif</td>
                    <td class="text-right"><a class="text-xs font-semibold text-brand-600" href="{{ route('center.receipt', $r) }}" target="_blank">Chek</a></td></tr>
            @empty
                <tr><td class="text-center text-ink-500">So'rov yo'q.</td></tr>
            @endforelse
        </tbody></table>
    </div>
@endsection
