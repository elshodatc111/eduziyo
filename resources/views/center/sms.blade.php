@extends('layouts.app')
@section('title', 'SMS')

@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

@section('content')
    <x-page-header title="SMS" subtitle="Markaz SMS limiti, jurnal va qo'shimcha paketlar" />
    @include('center._nav')

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="card card-body"><div class="text-sm text-ink-500">Oylik limit</div><div class="mt-1 text-2xl font-bold">{{ $limit === null ? 'Cheksiz' : $fmt($limit) }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Shu oy ishlatilgan</div><div class="mt-1 text-2xl font-bold">{{ $fmt($used) }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Qo'shimcha paket qoldig'i</div><div class="mt-1 text-2xl font-bold text-emerald-600">{{ $fmt($extra) }}</div></div>
    </div>

    @if ($invoice)
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
            <div class="font-bold text-ink-900 dark:text-white">SMS faktura: {{ $fmt($invoice->sms_count) }} ta SMS — {{ $fmt($invoice->amount) }} so'm</div>
            @if ($invoice->note)<div class="text-sm text-ink-600">{{ $invoice->note }}</div>@endif
            <div class="text-xs text-ink-500">Qabul qilish muddati: {{ $invoice->expires_at->format('d.m.Y H:i') }} (so'ng avtomatik bekor bo'ladi). Summa balansdan yechiladi.</div>
            <div class="mt-3 flex gap-2">
                <form method="POST" action="{{ route('center.sms.accept', $invoice->id) }}" onsubmit="return confirm('Balansdan {{ $fmt($invoice->amount) }} so\'m yechiladi. Davom etasizmi?')">@csrf<button class="btn-primary btn-sm">Qabul qilish</button></form>
                <form method="POST" action="{{ route('center.sms.decline', $invoice->id) }}">@csrf<button class="btn-secondary btn-sm">Rad etish</button></form>
            </div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 sm:grid-cols-3">
            <input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="Telefon raqami" class="input">
            <select name="status" class="input" onchange="this.form.submit()"><option value="">Barcha holatlar</option>
                @foreach (['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
            <button class="btn-secondary">Qidirish</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Sana</th><th>Telefon</th><th>Xabar</th><th>Kanal</th><th>Holat</th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr><td class="whitespace-nowrap">{{ $m->created_at->format('d.m.Y H:i') }}</td><td>{{ \App\Support\Format::prettyPhone($m->phone) }}</td><td class="max-w-md text-sm">{{ $m->message }}</td>
                    <td>{{ $m->channel === 'modem' ? 'Modem' : 'Eskiz' }}</td>
                    <td><span class="{{ ['sent' => 'badge-green', 'queued' => 'badge-amber', 'failed' => 'badge-red'][$m->status] ?? 'badge-gray' }}">{{ ['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'][$m->status] ?? $m->status }}</span></td></tr>
            @empty<tr><td colspan="5" class="py-10 text-center text-ink-500">Xabar yo'q.</td></tr>@endforelse
            </tbody></table></div>
        {{ $messages->links() }}
    </div>

    @if ($history->isNotEmpty())
        <div class="mt-6 card overflow-hidden"><div class="border-b border-ink-100 px-5 py-3 font-bold dark:border-ink-800">Fakturalar</div>
            <table class="table"><tbody>@foreach ($history as $i)<tr><td>{{ $i->created_at->format('d.m.Y') }}</td><td>{{ $fmt($i->sms_count) }} ta</td><td>{{ $fmt($i->amount) }} so'm</td><td>{{ $i->statusLabel() }}</td></tr>@endforeach</tbody></table></div>
    @endif
@endsection
