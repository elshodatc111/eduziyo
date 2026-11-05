@extends('layouts.app')
@section('title', 'SMS jurnali')

@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

@section('content')
    <x-page-header title="SMS jurnali (barcha markazlar)" subtitle="Modem kanali, Eskiz zaxirasi va SMS fakturalar" />

    <div class="mb-5 grid gap-4 lg:grid-cols-3">
        <div class="card card-body text-sm">
            <div class="font-bold">USB modem</div>
            <span class="{{ $modem['alive'] ? 'badge-green' : 'badge-red' }} mt-1 w-fit">{{ $modem['alive'] ? 'Ishlayapti ('.$modem['count'].' ta tayyor)' : "Tayyor modem yo'q — Eskiz ishlatiladi" }}</span>
            <div class="mt-1 text-ink-500">Navbat: {{ $modem['queue'] }} · <a class="font-semibold text-brand-600" href="{{ route('platform.modems') }}">Modemlarni boshqarish</a></div>
        </div>
        <form method="POST" action="{{ route('platform.sms.settings') }}" class="card card-body flex items-end gap-2">@method('PUT')@csrf
            <x-input name="sms_text_retention_days" type="number" min="0" label="SMS matnini saqlash (kun, 0 = cheksiz)" :value="$retention" required />
            <button class="btn-primary btn-sm">Saqlash</button>
        </form>
        <div class="card card-body text-sm text-ink-600">Fakturani markaz sahifasidan («Markazlar» → markaz) yuborasiz. Faol faktura bittadan ko'p bo'lmaydi, 3 kunda avtomatik bekor bo'ladi.</div>
    </div>

    <div class="card overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 sm:grid-cols-5">
            <select name="center_id" class="input"><option value="">Barcha markazlar</option>@foreach ($centers as $id => $n)<option value="{{ $id }}" @selected((string) request('center_id') === (string) $id)>{{ $n }}</option>@endforeach</select>
            <select name="status" class="input"><option value="">Holat</option>@foreach (['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
            <select name="channel" class="input"><option value="">Kanal</option><option value="modem" @selected(request('channel') === 'modem')>Modem</option><option value="eskiz" @selected(request('channel') === 'eskiz')>Eskiz</option></select>
            <input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="Telefon" class="input">
            <button class="btn-secondary">Filtr</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Sana</th><th>Markaz</th><th>Telefon</th><th>Xabar</th><th>Kanal</th><th>Holat</th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr><td class="whitespace-nowrap">{{ $m->created_at->format('d.m.Y H:i') }}</td><td>{{ $centers[$m->center_id] ?? '—' }}</td><td>{{ \App\Support\Format::prettyPhone($m->phone) }}</td><td class="max-w-md text-sm">{{ $m->message }}</td>
                    <td>{{ $m->channel === 'modem' ? 'Modem' : 'Eskiz' }}</td>
                    <td><span class="{{ ['sent' => 'badge-green', 'queued' => 'badge-amber', 'failed' => 'badge-red'][$m->status] ?? 'badge-gray' }}">{{ ['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'][$m->status] ?? $m->status }}</span>@if ($m->status === 'failed' && $m->provider_response)<div class="max-w-[14rem] truncate text-xs text-ink-400" title="{{ $m->provider_response }}">{{ $m->provider_response }}</div>@endif</td></tr>
            @empty<tr><td colspan="6" class="py-10 text-center text-ink-500">Xabar yo'q.</td></tr>@endforelse
            </tbody></table></div>
        {{ $messages->links() }}
    </div>

    <div class="mt-6 card overflow-hidden"><div class="border-b border-ink-100 px-5 py-3 font-bold dark:border-ink-800">So'nggi SMS fakturalar</div>
        <table class="table"><tbody>
            @forelse ($invoices as $i)
                <tr><td>{{ $i->created_at->format('d.m.Y') }}</td><td>{{ $centers[$i->center_id] ?? '—' }}</td><td>{{ $fmt($i->sms_count) }} ta</td><td>{{ $fmt($i->amount) }} so'm</td><td>{{ $i->statusLabel() }}</td>
                    <td class="text-right">@if ($i->status === 'pending')<form method="POST" action="{{ route('platform.sms.invoices.cancel', $i->id) }}">@csrf<button class="text-xs font-semibold text-red-600">Bekor qilish</button></form>@endif</td></tr>
            @empty<tr><td class="text-center text-ink-500">Faktura yo'q.</td></tr>@endforelse
        </tbody></table>
    </div>
@endsection
