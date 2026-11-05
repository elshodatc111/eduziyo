@extends('layouts.app')
@section('title', 'Dars jadvalim')

@section('content')
    <x-page-header title="Dars jadvalim" subtitle="Kelgusi 14 kundagi darslaringiz" />
    @forelse ($days as $date => $items)
        <div class="card mb-3 overflow-hidden">
            <div class="border-b border-ink-100 px-5 py-2.5 font-semibold dark:border-ink-800">{{ \Illuminate\Support\Carbon::parse($date)->format('d.m.Y') }} @if ($date === today()->format('Y-m-d'))<span class="badge-green ml-2">Bugun</span>@endif</div>
            <ul class="divide-y divide-ink-100 dark:divide-ink-800">
                @foreach ($items as $d)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                        <a href="{{ route('groups.show', $d->group_id) }}" class="font-medium hover:text-brand-600">{{ $d->group?->name }}</a>
                        <span class="text-ink-500">{{ $d->lessonTime?->label }} · {{ $d->room?->name }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="card card-body py-10 text-center text-ink-500">Yaqin 14 kunda dars yo'q.</div>
    @endforelse
@endsection
