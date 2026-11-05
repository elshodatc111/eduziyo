@extends('layouts.app')
@section('title', 'Murojaatlar')

@section('content')
    <x-page-header title="Murojaatlar" subtitle="Markaz direktorlaridan kelgan savollar · o'rtacha baho: {{ $avg ?: '—' }}" />

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach ([null => 'Hammasi', 'open' => 'Javob kutilmoqda', 'pending_customer' => 'Mijoz javobi kutilmoqda', 'closed' => 'Yopilgan'] as $k => $l)
            <a href="{{ route('platform.support', $k ? ['status' => $k] : []) }}" class="{{ (request('status') ?: null) === $k ? 'btn-primary' : 'btn-secondary' }} btn-sm">{{ $l }}</a>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <table class="table">
            <thead><tr><th>Sana</th><th>Markaz</th><th>Mavzu</th><th>Holat</th><th>SLA</th><th>Baho</th></tr></thead>
            <tbody>
            @forelse ($tickets as $t)
                <tr><td class="whitespace-nowrap">{{ $t->created_at->format('d.m.Y H:i') }}</td><td>{{ $centers[$t->center_id] ?? '—' }}</td>
                    <td><a class="font-semibold text-brand-600" href="{{ route('platform.support.show', $t->id) }}">{{ $t->subject }}</a></td>
                    <td><span class="{{ $t->isClosed() ? 'badge-gray' : ($t->status === 'open' ? 'badge-amber' : 'badge-green') }}">{{ $t->statusLabel() }}</span></td>
                    <td class="text-xs {{ $t->status === 'open' && ! $t->first_response_at && $t->sla_due_at?->isPast() ? 'font-bold text-red-600' : 'text-ink-500' }}">{{ $t->status === 'open' && ! $t->first_response_at ? $t->sla_due_at?->format('d.m H:i') : '—' }}</td>
                    <td>{{ $t->rating ? $t->rating.' ★' : '—' }}</td></tr>
            @empty<tr><td colspan="6" class="py-10 text-center text-ink-500">Murojaat yo'q.</td></tr>@endforelse
            </tbody>
        </table>
        {{ $tickets->links() }}
    </div>

    <form method="POST" action="{{ route('platform.support.settings') }}" class="mt-6 card card-body max-w-3xl space-y-3">@csrf @method('PUT')
        <h2 class="font-bold text-ink-900 dark:text-white">Ish vaqti va SLA</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            <x-input name="support_start" type="time" label="Boshlanish" :value="old('support_start', $cfg['start'])" required />
            <x-input name="support_end" type="time" label="Tugash" :value="old('support_end', $cfg['end'])" required />
            <x-input name="support_sla_hours" type="number" step="0.5" min="0.5" label="Birinchi javob (ish soati)" :value="old('support_sla_hours', $cfg['sla_hours'])" required />
        </div>
        <div class="flex flex-wrap gap-3 text-sm">@foreach ([1 => 'Du', 2 => 'Se', 3 => 'Chor', 4 => 'Pay', 5 => 'Ju', 6 => 'Sha', 7 => 'Yak'] as $n => $l)<label class="flex items-center gap-1"><input type="checkbox" name="support_days[]" value="{{ $n }}" @checked(in_array($n, old('support_days', $cfg['days'])))> {{ $l }}</label>@endforeach</div>
        @error('support_days')<p class="error-text">{{ $message }}</p>@enderror
        <button class="btn-primary w-fit btn-sm">Saqlash</button>
    </form>
@endsection
