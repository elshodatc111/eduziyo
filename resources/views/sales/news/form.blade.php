@extends('layouts.app')
@section('title', $post->exists ? 'Yangilikni tahrirlash' : 'Yangilik yozish')

@section('content')
    <x-page-header :title="$post->exists ? 'Yangilikni tahrirlash' : 'Yangi yangilik'" subtitle="Matn oddiy matn sifatida ko'rsatiladi (HTML qabul qilinmaydi)" />

    <form method="POST" action="{{ $post->exists ? route('sales.news.update', $post->id) : route('sales.news.store') }}" class="max-w-3xl space-y-6" enctype="multipart/form-data">
        @csrf
        @if ($post->exists) @method('PUT') @endif
        <div class="card card-body grid gap-5">
            <x-input name="title" label="Sarlavha" :value="$post->title" required />
            <x-input name="excerpt" label="Qisqa matn" :value="$post->excerpt" hint="Ro'yxatda sarlavha ostida ko'rinadi." />
            <div>
                <label for="body" class="label">Matn <span class="text-brand-600">*</span></label>
                <textarea id="body" name="body" rows="10" required class="input">{{ old('body', $post->body) }}</textarea>
                @error('body')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="image" class="label">Rasm</label>
                @if ($post->imageUrl())
                    <img src="{{ $post->imageUrl() }}" alt="" class="mb-2 h-32 rounded-xl object-cover">
                    <label class="mb-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1"> Rasmni olib tashlash</label>
                @endif
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="input">
                <p class="hint">JPG, PNG yoki WEBP, 3 MB gacha. Yangi rasm yuklasangiz eskisi almashadi.</p>
                @error('image')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <x-input name="youtube_url" label="YouTube havolasi" :value="$post->youtube_id ? 'https://www.youtube.com/watch?v='.$post->youtube_id : null" hint="Ixtiyoriy. Video yangilik matni ostida ko'rinadi." />
            <x-input name="published_at" type="datetime-local" label="Chop etish vaqti" :value="$post->published_at?->format('Y-m-d\TH:i')" hint="Bo'sh qolsa — hozir." />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published))> Sotuv sahifasida chop etilsin</label>
        </div>
        <div class="flex gap-2">
            <button class="btn-primary">Saqlash</button>
            <a href="{{ route('sales.news.index') }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
