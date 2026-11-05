@extends('layouts.app')
@section('title', 'Videolar')

@section('content')
    <x-page-header title="Sotuv videolari" subtitle="YouTube'dagi videolar sotuv sahifasining «Video qo'llanmalar» bo'limida ko'rinadi" />

    <form method="POST" action="{{ route('sales.videos.store') }}" class="card card-body mb-6 grid max-w-3xl gap-4 sm:grid-cols-2">
        @csrf
        <x-input name="title" label="Sarlavha" required />
        <x-input name="url" label="YouTube havolasi" required hint="https://www.youtube.com/watch?v=... yoki youtu.be/..." />
        <x-input name="description" label="Qisqa tavsif" class="sm:col-span-2" />
        <div class="sm:col-span-2"><button class="btn-primary">Video qo'shish</button></div>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Sarlavha</th><th>Video</th><th>Holat</th><th class="text-right">Amallar</th></tr></thead>
                <tbody>
                @forelse ($videos as $v)
                    <tr>
                        <td class="font-medium text-ink-900 dark:text-white">{{ $v->title }}</td>
                        <td><a href="https://www.youtube.com/watch?v={{ $v->youtube_id }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ $v->youtube_id }}</a></td>
                        <td><span class="{{ $v->is_active ? 'badge-green' : 'badge-gray' }}">{{ $v->is_active ? 'Ko\'rinadi' : 'Yashirin' }}</span></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <form method="POST" action="{{ route('sales.videos.toggle', $v) }}">@csrf<button class="btn-ghost btn-sm">{{ $v->is_active ? 'Yashirish' : 'Ko\'rsatish' }}</button></form>
                                <form method="POST" action="{{ route('sales.videos.destroy', $v) }}" onsubmit="return confirm('Video o\'chirilsinmi?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-brand-600">O'chirish</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-ink-500">Hozircha video yo'q.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
