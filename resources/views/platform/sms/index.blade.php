@extends('layouts.app')
@section('title', 'SMS jurnali')

@section('content')
    <x-page-header title="SMS jurnali (barcha markazlar)" subtitle="Gateway, Eskiz kanallari va yuborilgan xabarlar" />

    <div class="mb-5 grid gap-4 lg:grid-cols-3">
        <div class="card card-body text-sm">
            <div class="font-bold">SMS Gateway</div>
            @if (! $gateway['configured'])
                <span class="badge-red mt-1 w-fit">Sozlanmagan — SMS Eskiz orqali ketadi</span>
            @else
                <span class="{{ $gateway['alive'] ? 'badge-green' : 'badge-red' }} mt-1 w-fit">{{ $gateway['alive'] ? 'Ishlayapti' : 'Javob bermayapti — Eskiz ishlatiladi' }}</span>
            @endif
            <div class="mt-1 text-ink-500">Navbatda: {{ $gateway['queue'] }} · <a class="font-semibold text-brand-600" href="{{ route('platform.integrations') }}">Sozlash</a></div>
        </div>
        <form method="POST" action="{{ route('platform.sms.settings') }}" class="card card-body flex items-end gap-2">@method('PUT')@csrf
            <x-input name="sms_text_retention_days" type="number" min="0" label="SMS matnini saqlash (kun, 0 = cheksiz)" :value="$retention" required />
            <button class="btn-primary btn-sm">Saqlash</button>
        </form>
        <div class="card card-body text-sm text-ink-600">Umumiy matnlarni tasdiqlash va Eskiz'ga yuborish: <a class="font-semibold text-brand-600" href="{{ route('platform.sms.templates') }}">SMS shablonlar</a>. Platforma Eskiz hisobi: <a class="font-semibold text-brand-600" href="{{ route('platform.integrations') }}">Integratsiyalar</a>.</div>
    </div>

    <div class="card overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 sm:grid-cols-5">
            <select name="center_id" class="input"><option value="">Barcha markazlar</option>@foreach ($centers as $id => $n)<option value="{{ $id }}" @selected((string) request('center_id') === (string) $id)>{{ $n }}</option>@endforeach</select>
            <select name="status" class="input"><option value="">Holat</option>@foreach (['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
            <select name="channel" class="input"><option value="">Kanal</option><option value="gateway" @selected(request('channel') === 'gateway')>Gateway</option><option value="eskiz" @selected(request('channel') === 'eskiz')>Eskiz (platforma)</option><option value="own" @selected(request('channel') === 'own')>Markazning Eskizi</option></select>
            <input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="Telefon" class="input">
            <button class="btn-secondary">Filtr</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Sana</th><th>Markaz</th><th>Telefon</th><th>Xabar</th><th>Kanal</th><th>Holat</th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr><td class="whitespace-nowrap">{{ $m->created_at->format('d.m.Y H:i') }}</td><td>{{ $centers[$m->center_id] ?? '—' }}</td><td>{{ \App\Support\Format::prettyPhone($m->phone) }}</td><td class="max-w-md text-sm">{{ $m->message }}</td>
                    <td>{{ ['gateway' => 'Gateway', 'own' => 'Markaz Eskizi'][$m->channel] ?? 'Eskiz' }}</td>
                    <td><span class="{{ ['sent' => 'badge-green', 'queued' => 'badge-amber', 'failed' => 'badge-red'][$m->status] ?? 'badge-gray' }}">{{ ['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'][$m->status] ?? $m->status }}</span>@if ($m->status === 'failed' && $m->provider_response)<div class="max-w-[14rem] truncate text-xs text-ink-400" title="{{ $m->provider_response }}">{{ $m->provider_response }}</div>@endif</td></tr>
            @empty<tr><td colspan="6" class="py-10 text-center text-ink-500">Xabar yo'q.</td></tr>@endforelse
            </tbody></table></div>
        {{ $messages->links() }}
    </div>
@endsection
