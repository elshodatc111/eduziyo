@extends('layouts.app')
@section('title', 'USB modemlar')

@section('content')
    <x-page-header title="USB modemlar" subtitle="Serverga ulangan modemlar avtomatik topiladi va ulanadi">
        <x-slot:actions>
            <form method="POST" action="{{ route('platform.modems.scan') }}">@csrf<button class="btn-primary">Qayta qidirish</button></form>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="card card-body text-sm"><div class="text-ink-500">Modem ishchisi (<code>sms:modem-run</code>)</div>
            <span class="{{ $worker ? 'badge-green' : 'badge-red' }} mt-1 w-fit">{{ $worker ? 'Ishlayapti' : "Ishlamayapti — SMS Eskiz orqali ketadi" }}</span></div>
        <div class="card card-body text-sm"><div class="text-ink-500">Tayyor modemlar</div><div class="mt-1 text-2xl font-bold">{{ $modems->where('status', 'ready')->where('enabled', true)->count() }} / {{ $modems->count() }}</div></div>
        <div class="card card-body text-sm"><div class="text-ink-500">Navbatdagi SMS</div><div class="mt-1 text-2xl font-bold">{{ $queue }}</div></div>
    </div>

    <div class="space-y-4">
        @forelse ($modems as $m)
            <div class="card card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2"><span class="text-base font-bold text-ink-900 dark:text-white">{{ $m->name() }}</span>
                            <span class="{{ ['ready' => 'badge-green', 'no_network' => 'badge-amber', 'sim_error' => 'badge-red', 'error' => 'badge-red', 'offline' => 'badge-gray'][$m->status] ?? 'badge-gray' }}">{{ $m->statusLabel() }}</span>
                            @unless ($m->enabled)<span class="badge-gray">O'chirilgan</span>@endunless</div>
                        <div class="mt-1 text-xs text-ink-500">IMEI {{ $m->imei }} · {{ $m->device ?? '—' }} · {{ $m->operator ?? 'operator yo\'q' }} · SIM: {{ $m->sim_status ?? '—' }} · signal: {{ $m->signal !== null ? $m->signal.'%' : '—' }}</div>
                        <div class="text-xs text-ink-500">Yuborilgan: {{ $m->sent_count }} · xato: {{ $m->failed_count }} · oxirgi ko'ringan: {{ $m->last_seen_at?->diffForHumans() ?? '—' }}</div>
                        @if ($m->last_error)<div class="mt-1 text-xs text-red-600">{{ $m->last_error }}</div>@endif
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <form method="POST" action="{{ route('platform.modems.update', $m->id) }}" class="flex items-end gap-1">@csrf @method('PUT')
                            <input name="label" value="{{ $m->label }}" placeholder="Nom (masalan: Ucell-1)" class="input w-44"><button class="btn-secondary btn-sm">Saqlash</button></form>
                        <form method="POST" action="{{ route('platform.modems.toggle', $m->id) }}">@csrf<button class="btn-secondary btn-sm">{{ $m->enabled ? "O'chirish" : 'Faollashtirish' }}</button></form>
                        @if ($m->fail_streak > 0)<form method="POST" action="{{ route('platform.modems.reset', $m->id) }}">@csrf<button class="btn-secondary btn-sm">Xatoni tozalash</button></form>@endif
                    </div>
                </div>
                @if ($m->status === 'ready')
                    <form method="POST" action="{{ route('platform.modems.test', $m->id) }}" class="mt-3 flex items-end gap-2 border-t border-ink-100 pt-3 dark:border-ink-800">@csrf
                        <x-input name="phone" label="Sinov SMS (telefon)" placeholder="+998 90 123 4567" required />
                        <button class="btn-primary btn-sm">Yuborish</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="card card-body text-sm text-ink-600">Modem topilmadi. USB modemni ulang va «Qayta qidirish»ni bosing. Qo'llanma: <code>docs/SMS_MODEM.md</code>.</div>
        @endforelse
    </div>

    @if ($report)
        <div class="mt-6 card overflow-hidden"><div class="border-b border-ink-100 px-5 py-3 font-bold dark:border-ink-800">Diagnostika (so'nggi qidiruv: {{ $report['at'] }})</div>
            <table class="table"><tbody>
                @forelse ($report['rows'] as $r)<tr><td class="font-mono text-xs">{{ $r['device'] }}</td><td class="text-sm">{{ $r['result'] }}</td></tr>
                @empty<tr><td class="text-center text-ink-500">USB-serial port (/dev/ttyUSB*, /dev/ttyACM*) topilmadi. Modem <code>usb_modeswitch</code> bilan rejimga o'tganini tekshiring.</td></tr>@endforelse
            </tbody></table></div>
    @endif
@endsection
