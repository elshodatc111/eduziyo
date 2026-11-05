@extends('layouts.app')
@section('title', 'Yangiliklar')

@section('content')
    <x-page-header title="Yangiliklar" subtitle="Faqat sotuv sahifasida ko'rinadi">
        <x-slot:actions><a href="{{ route('sales.news.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Yangilik yozish</a></x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Sarlavha</th><th>Holat</th><th>Chop etilgan</th><th class="text-right">Amallar</th></tr></thead>
                <tbody>
                @forelse ($posts as $p)
                    <tr>
                        <td class="font-medium text-ink-900 dark:text-white">{{ $p->title }} @if ($p->image_path)<span class="badge-gray ml-1">rasm</span>@endif @if ($p->youtube_id)<span class="badge-gray ml-1">video</span>@endif</td>
                        <td><span class="{{ $p->is_published ? 'badge-green' : 'badge-gray' }}">{{ $p->is_published ? 'Chop etilgan' : 'Qoralama' }}</span></td>
                        <td class="text-xs text-ink-500">{{ $p->published_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('sales.news.edit', $p->id) }}" class="btn-ghost btn-sm">Tahrirlash</a>
                                <form method="POST" action="{{ route('sales.news.destroy', $p->id) }}" onsubmit="return confirm('Yangilik o\'chirilsinmi?')">@csrf @method('DELETE')
                                    <button class="btn-ghost btn-sm text-brand-600">O'chirish</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-ink-500">Hozircha yangilik yo'q.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $posts->links() }}</div>
@endsection
