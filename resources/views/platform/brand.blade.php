@extends('layouts.app')
@section('title', 'Brending')

@section('content')
    <x-page-header title="Brending" subtitle="Loyiha nomi va logotipi hamma joyda almashadi: panel, kirish sahifasi, sotuv sahifasi, kabinet, o'rnatiladigan ilova" />

    <form method="POST" action="{{ route('platform.brand.update') }}" enctype="multipart/form-data" class="card card-body max-w-2xl space-y-5">
        @csrf @method('PUT')
        <x-input name="brand_name" label="Loyiha nomi" :value="old('brand_name', $name)" required maxlength="40" />

        <div>
            <label class="label" for="logo">Logotip (PNG, JPG yoki WebP, kvadrat tavsiya, 1 MB gacha)</label>
            <div class="mb-2 flex items-center gap-3"><x-logo class="h-14 w-14" /><span class="text-sm text-ink-500">{{ $logo ? 'Joriy logotip' : 'Logotip yo\'q — nomning bosh harfi ko\'rsatiladi' }}</span></div>
            <input id="logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp" class="input">
            @error('logo')<p class="error-text">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="label" for="favicon">Favicon (PNG yoki ICO, 256 KB gacha; bo'sh qolsa logotip ishlatiladi)</label>
            @if ($favicon)<img src="{{ $favicon }}" alt="" class="mb-2 h-8 w-8">@endif
            <input id="favicon" name="favicon" type="file" accept=".png,.ico" class="input">
            @error('favicon')<p class="error-text">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-2"><button class="btn-primary">Saqlash</button></div>
    </form>

    <div class="mt-4 flex gap-2">
        @if ($logo)<form method="POST" action="{{ route('platform.brand.remove', 'logo') }}" onsubmit="return confirm('Logotip olib tashlansinmi?')">@csrf @method('DELETE')<button class="btn-secondary btn-sm">Logotipni olib tashlash</button></form>@endif
        @if ($favicon)<form method="POST" action="{{ route('platform.brand.remove', 'favicon') }}">@csrf @method('DELETE')<button class="btn-secondary btn-sm">Faviconni olib tashlash</button></form>@endif
    </div>
@endsection
