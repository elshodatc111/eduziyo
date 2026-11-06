@extends('layouts.app')
@section('title', $branch->exists ? 'Filialni tahrirlash' : 'Yangi filial')

@section('content')
    <x-page-header :title="$branch->exists ? 'Filialni tahrirlash' : 'Yangi filial'" />

    <form method="POST" action="{{ $branch->exists ? route('branches.update', $branch) : route('branches.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($branch->exists) @method('PUT') @endif

        <div class="card card-body grid gap-5 sm:grid-cols-2">
            @if (! $branch->exists && ! \App\Support\CenterContext::id())
                <div class="sm:col-span-2">
                    <label class="label" for="center_id">Markaz</label>
                    <select id="center_id" name="center_id" class="input" required>
                        <option value="">— tanlang —</option>
                        @foreach (\App\Models\Center::where('status', '!=', 'deleted')->orderBy('name')->get() as $c)
                            <option value="{{ $c->id }}" @selected(old('center_id') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('center_id')<p class="error-text">{{ $message }}</p>@enderror
                </div>
            @endif
            <x-input name="name" label="Filial nomi" :value="$branch->name" required class="sm:col-span-2" />
            <x-input name="phone" phone label="Telefon" :value="$branch->phone" />
            <x-input name="address" label="Manzil" :value="$branch->address" />
        </div>

        <div class="card card-body">
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Ochiq murojaat sahifasi (Varonka)</h2>
            <p class="mb-5 mt-1 text-sm text-ink-500 dark:text-ink-400">Mijozlar murojaat qoldiradigan ochiq sahifada Edunova o'rniga shu filialning nomi, rangi va ma'lumoti ko'rsatiladi.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div x-data="{ color: '{{ old('brand_color', $branch->brand_color ?? \App\Models\Branch::DEFAULT_BRAND_COLOR) }}' }">
                    <label class="label" for="brand_color">Rang</label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="brand_color" name="brand_color" x-model="color" class="color-swatch">
                        <span class="input flex-1 cursor-default select-all uppercase" x-text="color"></span>
                    </div>
                    <p class="hint">Sahifadagi belgi va tugmalar rangi. Tanlangan: <span class="font-semibold uppercase" x-text="color"></span></p>
                    @error('brand_color')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="public_about">Filial haqida (ixtiyoriy)</label>
                    <textarea id="public_about" name="public_about" rows="3" maxlength="1000" class="input">{{ old('public_about', $branch->public_about) }}</textarea>
                    @error('public_about')<p class="error-text">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <button class="btn-primary">Saqlash</button>
            <a href="{{ route('branches.index') }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
