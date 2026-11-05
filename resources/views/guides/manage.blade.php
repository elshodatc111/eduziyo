@extends('layouts.app')
@section('title', "Video qo'llanmalar (boshqarish)")

@section('content')
    <x-page-header title="Video qo'llanmalar" subtitle="YouTube havolasini kiriting va qaysi rollarga ko'rinishini belgilang. O'quvchilar ko'rmaydi" />

    <form method="POST" action="{{ route('platform.guides.store') }}" class="card card-body mb-6 max-w-3xl space-y-3" enctype="multipart/form-data">@csrf
        <x-input name="title" label="Sarlavha" required maxlength="160" />
        <x-input name="url" label="YouTube havolasi" placeholder="https://www.youtube.com/watch?v=..." required />
        <div><div class="label">Qaysi rollarga</div>
            <div class="flex flex-wrap gap-3 text-sm">@foreach ($roles as $v => $l)<label class="flex items-center gap-1"><input type="checkbox" name="roles[]" value="{{ $v }}" @checked(in_array($v, old('roles', [])))> {{ $l }}</label>@endforeach</div>
            @error('roles')<p class="error-text">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="cover">Muqova rasmi (ixtiyoriy; bo'sh qolsa YouTube rasmi ishlatiladi)</label><input id="cover" type="file" name="cover" accept="image/png,image/jpeg,image/webp" class="input">@error('cover')<p class="error-text">{{ $message }}</p>@enderror</div>
        <x-input name="sort_order" type="number" label="Tartib raqami (kichigi birinchi)" value="100" />
        <button class="btn-primary w-fit">Qo'shish</button>
    </form>

    <div class="card overflow-hidden"><table class="table">
        <thead><tr><th>Sarlavha</th><th>Rollar</th><th>Holat</th><th></th></tr></thead>
        <tbody>
        @forelse ($guides as $g)
            <tr><td><div class="flex items-center gap-3">@if ($g->coverUrl())<img src="{{ $g->coverUrl() }}" alt="" class="h-12 w-20 shrink-0 rounded object-cover">@endif<div><div class="font-semibold">{{ $g->title }}</div><div class="break-all text-xs text-ink-500">{{ $g->url }}</div></div></div></td>
                <td class="text-sm">{{ collect($g->roles)->map(fn ($r) => $roles[$r] ?? $r)->implode(', ') }}</td>
                <td><span class="{{ $g->is_active ? 'badge-green' : 'badge-gray' }}">{{ $g->is_active ? 'Faol' : "O'chirilgan" }}</span></td>
                <td class="space-x-2 whitespace-nowrap text-right">
                    <form method="POST" action="{{ route('platform.guides.toggle', $g->id) }}" class="inline">@csrf<button class="text-xs font-semibold text-brand-600">{{ $g->is_active ? "O'chirish" : 'Yoqish' }}</button></form>
                    <form method="POST" action="{{ route('platform.guides.cover', $g->id) }}" enctype="multipart/form-data" class="inline"><label class="cursor-pointer text-xs font-semibold text-brand-600">Muqova yuklash<input type="file" name="cover" accept="image/png,image/jpeg,image/webp" class="hidden" onchange="this.form.submit()"></label>@csrf</form>
                    @if ($g->cover_path)<form method="POST" action="{{ route('platform.guides.cover.remove', $g->id) }}" class="inline">@csrf @method('DELETE')<button class="text-xs font-semibold text-ink-500">Muqovani olib tashlash</button></form>@endif
                    <form method="POST" action="{{ route('platform.guides.destroy', $g->id) }}" class="inline" onsubmit="return confirm('O\'chirilsinmi?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600">O'chirish</button></form></td></tr>
        @empty<tr><td colspan="4" class="py-8 text-center text-ink-500">Video qo'llanma yo'q.</td></tr>@endforelse
        </tbody></table></div>
@endsection
