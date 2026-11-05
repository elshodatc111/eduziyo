@extends('layouts.app')
@section('title', 'Sotuv sozlamalari')

@section('content')
    <x-page-header title="Sotuv sahifasi sozlamalari" subtitle="Aloqa ma'lumotlari va tarif matni sotuv sahifasining pastida va tariflar bo'limida ko'rinadi" />

    <form method="POST" action="{{ route('sales.settings.update') }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')
        <div class="card card-body grid gap-5 sm:grid-cols-2">
            <x-input name="contact_phone1" phone label="1-telefon" :value="$c['contact_phone1']" />
            <x-input name="contact_phone2" phone label="2-telefon" :value="$c['contact_phone2']" />
            <x-input name="contact_email" type="email" label="Email" :value="$c['contact_email']" />
            <x-input name="contact_address" label="Manzil" :value="$c['contact_address']" />
            <x-input name="contact_telegram" label="Telegram kanal havolasi" :value="$c['contact_telegram']" hint="https://t.me/..." />
            <x-input name="contact_instagram" label="Instagram havolasi" :value="$c['contact_instagram']" hint="https://instagram.com/..." />
            <div class="sm:col-span-2">
                <label for="tariff_text" class="label">Tariflar bo'limi matni</label>
                <textarea id="tariff_text" name="tariff_text" rows="3" maxlength="1000" class="input">{{ old('tariff_text', $c['tariff_text']) }}</textarea>
                <p class="hint">Bo'sh qolsa, standart matn ko'rsatiladi. Tariflar tizimi tayyor bo'lgach narxlar jadvali qo'shiladi.</p>
                @error('tariff_text')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="card card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <h2 class="font-bold text-ink-900 dark:text-white">Demo</h2>
                <p class="hint">Login va parol ikkalasi ham kiritilsa, ular sotuv sahifasida hammaga ochiq ko'rsatiladi. Faqat demo hisob uchun ishlating (haqiqiy markaz hisobini emas). Bo'sh qoldirsangiz «Demo so'rash» tugmasi chiqadi.</p>
            </div>
            <x-input name="demo_login" label="Demo login" :value="$c['demo_login']" />
            <x-input name="demo_password" label="Demo parol" :value="$c['demo_password']" />
            <div class="sm:col-span-2">
                <label for="demo_text" class="label">Demo bo'limi matni</label>
                <textarea id="demo_text" name="demo_text" rows="3" maxlength="1000" class="input">{{ old('demo_text', $c['demo_text']) }}</textarea>
                @error('demo_text')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        </div>
        <button class="btn-primary">Saqlash</button>
    </form>
@endsection
