@extends('layouts.app')
@section('title', 'Sotuv arizalari')

@section('content')
    <x-page-header title="Sotuv arizalari" subtitle="Sotuv sahifasidan kelgan arizalar. Faqat sAdmin ko'radi." />

    @if ($only)
        <div class="mb-3 rounded-xl bg-brand-50 p-3 text-sm text-brand-700 dark:bg-ink-800 dark:text-brand-200">Faqat #{{ $only }} ariza ko'rsatilmoqda. <a href="{{ route('sales.applications') }}" class="font-semibold underline">Hammasini ko'rish</a></div>
    @endif
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('sales.applications') }}" class="{{ ! $status ? 'btn-primary' : 'btn-secondary' }} btn-sm">Hammasi</a>
        @foreach (\App\Models\SalesApplication::STATUSES as $k => $label)
            <a href="{{ route('sales.applications', ['status' => $k]) }}" class="{{ $status === $k ? 'btn-primary' : 'btn-secondary' }} btn-sm">{{ $label }} ({{ $counts[$k] ?? 0 }})</a>
        @endforeach
        <form method="GET" class="ml-auto flex gap-2">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="search" name="q" value="{{ $q }}" placeholder="Markaz, ism yoki telefon" class="input w-56 py-2">
            <button class="btn-secondary btn-sm">Qidirish</button>
        </form>
    </div>

    <div class="space-y-3">
        @forelse ($apps as $a)
            <div class="card card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-ink-900 dark:text-white">{{ $a->center_name }}</span>
                            <span class="{{ $a->status === 'new' ? 'badge-red' : ($a->status === 'won' ? 'badge-green' : 'badge-gray') }}">{{ $a->statusLabel() }}</span>
                            @if ($a->phone_verified_at)<span class="badge-green">Telefon tasdiqlangan</span>@else<span class="badge-gray">Telefon tasdiqlanmagan</span>@endif
                            @if ($a->wants_demo)<span class="badge-blue">Demo so'radi</span>@endif
                            @if ($a->demoRoleLabels())<span class="badge-green" title="Demo'da ko'rgan rollar">Demo ko'rdi: {{ implode(', ', $a->demoRoleLabels()) }}</span>@endif
                            @if ($a->tariff_slug)<span class="badge-purple">Tarif: {{ $a->tariff_slug }}</span>@endif
                            @if ($a->isOverdue())<span class="badge-red">Muddati o'tgan</span>@endif
                        </div>
                        <div class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $a->name }} · {{ $a->phone }}@if ($a->city) · {{ $a->city }}@endif</div>
                        <div class="mt-1 text-xs text-ink-500">
                            @if ($a->branches_count)Filial: {{ $a->branches_count }} · @endif
                            @if ($a->students_count)O'quvchi: ~{{ $a->students_count }} · @endif
                            {{ $a->created_at->format('d.m.Y H:i') }}
                            @if ($a->utm_source || $a->utm_campaign) · UTM: {{ $a->utm_source }} {{ $a->utm_campaign }}@endif
                        </div>
                        @if ($a->message)<p class="mt-2 whitespace-pre-line text-sm text-ink-700 dark:text-ink-200">{{ $a->message }}</p>@endif
                    </div>
                    <form method="POST" action="{{ route('sales.applications.destroy', $a) }}" onsubmit="return confirm('Ariza o\'chirilsinmi?')">@csrf @method('DELETE')
                        <button class="btn-ghost btn-sm text-brand-600">O'chirish</button>
                    </form>
                </div>
                <form method="POST" action="{{ route('sales.applications.update', $a) }}" class="mt-4 grid gap-3 sm:grid-cols-4">
                    @csrf @method('PUT')
                    <div>
                        <label class="label">Holat</label>
                        <select name="status" class="input">
                            @foreach (\App\Models\SalesApplication::STATUSES as $k => $label)<option value="{{ $k }}" @selected($a->status === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Qayta bog'lanish</label>
                        <input type="date" name="follow_up_at" value="{{ $a->follow_up_at?->format('Y-m-d') }}" class="input">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Ichki izoh</label>
                        <input type="text" name="internal_note" value="{{ $a->internal_note }}" maxlength="2000" class="input">
                    </div>
                    <div class="sm:col-span-4"><button class="btn-primary btn-sm">Saqlash</button></div>
                </form>
            </div>
        @empty
            <div class="card card-body text-sm text-ink-500">Arizalar yo'q.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $apps->links() }}</div>
@endsection
