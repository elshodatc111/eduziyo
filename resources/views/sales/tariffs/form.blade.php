@extends('layouts.app')
@section('title', $tariff->name.' tarifi')

@section('content')
    <x-page-header :title="$tariff->name.' tarifi'" subtitle="Bo'sh limit = cheksiz (filial va o'quvchi uchun). Narx bo'sh bo'lsa «Narxni bilish» ko'rinadi" />

    <form method="POST" action="{{ route('sales.tariffs.update', $tariff) }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')
        <div class="card card-body grid gap-5 sm:grid-cols-2">
            <x-input name="name" label="Nomi" :value="$tariff->name" required />
            <x-input name="sort_order" type="number" min="1" max="99" label="Tartib" :value="$tariff->sort_order" required />
            <x-input name="tagline" label="Qisqa tavsif" :value="$tariff->tagline" class="sm:col-span-2" />
            <x-input name="price_monthly" money label="Oylik narx (so'm)" :value="$tariff->price_monthly" />
            <x-input name="price_yearly" money label="Yillik narx (so'm, to'liq summa)" :value="$tariff->price_yearly" hint="Masalan 12 oylik narxdan arzon qilib belgilang — sahifada tejash ko'rsatiladi." />
            <x-input name="max_branches" type="number" min="1" label="Filiallar limiti" :value="$tariff->max_branches" hint="Bo'sh = cheksiz" />
            <x-input name="max_students" type="number" min="1" label="O'quvchilar limiti" :value="$tariff->max_students" hint="Bo'sh = cheksiz" />
            <x-input name="sms_monthly" type="number" min="0" label="Oylik SMS limiti" :value="$tariff->sms_monthly" required />
            <x-input name="ai_monthly" type="number" min="0" label="Oylik AI limiti" :value="$tariff->ai_monthly" required hint="0 = AI yo'q" />
            <div class="sm:col-span-2">
                <label for="features" class="label">Imkoniyatlar (har qatorda bittadan)</label>
                <textarea id="features" name="features" rows="6" class="input">{{ old('features', $tariff->features) }}</textarea>
                @error('features')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $tariff->is_featured))> «Mashhur» deb belgilash (faqat bittasi)</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tariff->is_active))> Sotuv sahifasida ko'rinsin</label>
        </div>
        <div class="flex gap-2">
            <button class="btn-primary">Saqlash</button>
            <a href="{{ route('sales.tariffs') }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
