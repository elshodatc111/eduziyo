@extends('layouts.app')
@section('title', 'Rozilik hisoboti')

@section('content')
    <x-page-header title="Rozilik hisoboti" subtitle="Kim qaysi hujjat versiyasini qachon qabul qilgan">
        <x-slot:actions>
        <a href="{{ route('platform.legal.report', array_filter(['center' => $centerId, 'export' => 'xlsx'])) }}" class="btn-secondary">Excel</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 flex gap-2">
        <select name="center" class="input max-w-xs" onchange="this.form.submit()">
            <option value="">Barcha markazlar</option>
            @foreach ($centers as $c)<option value="{{ $c->id }}" @selected($centerId === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </form>

    <div class="card mb-6 overflow-hidden"><table class="table">
        <thead><tr><th>Amaldagi hujjat</th><th>Jami</th><th>Qabul qilgan</th><th>Qabul qilmagan</th></tr></thead>
        <tbody>
        @forelse ($data['rows'] as $r)
            <tr><td><div class="font-semibold">{{ $r['doc']->title }}</div><div class="text-xs text-ink-500">{{ $r['doc']->audienceLabel() }} · v{{ $r['doc']->version }}</div></td>
                <td>{{ $r['total'] }}</td><td class="text-emerald-700">{{ $r['accepted'] }}</td><td class="{{ $r['missing'] ? 'font-bold text-red-600' : '' }}">{{ $r['missing'] }}</td></tr>
        @empty<tr><td colspan="4" class="py-8 text-center text-ink-500">E'lon qilingan hujjat yo'q.</td></tr>@endforelse
        </tbody></table></div>

    <h2 class="mb-2 font-bold">Qabul qilinganlar</h2>
    <div class="card overflow-hidden"><table class="table">
        <thead><tr><th>Vaqt</th><th>F.I.O</th><th>Markaz</th><th>Hujjat</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($consents as $c)
            <tr><td class="whitespace-nowrap text-sm">{{ $c->accepted_at->format('d.m.Y H:i') }}</td><td>{{ $c->user_name }} <span class="text-xs text-ink-500">({{ $c->user_role }})</span></td><td>{{ $c->center_name ?? '—' }}</td>
                <td class="text-sm">{{ $c->document?->title }} · v{{ $c->document?->version }}</td><td class="text-xs text-ink-500">{{ $c->ip }}</td></tr>
        @empty<tr><td colspan="5" class="py-8 text-center text-ink-500">Hozircha rozilik yo'q.</td></tr>@endforelse
        </tbody></table>{{ $consents->links() }}</div>
@endsection
