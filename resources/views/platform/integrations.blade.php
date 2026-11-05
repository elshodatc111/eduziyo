@extends('layouts.app')
@section('title', 'Integratsiyalar')

@section('content')
    <x-page-header title="Integratsiyalar" subtitle="Telegram bot va USB modem sozlamalari. Bu yerda saqlangan qiymat .env dagisidan ustun turadi">
        <x-slot:actions><a href="{{ route('platform.modems') }}" class="btn-secondary">USB modemlar</a></x-slot:actions>
    </x-page-header>

    {{-- Telegram --}}
    <form method="POST" action="{{ route('platform.integrations.telegram') }}" class="card card-body mb-4 max-w-3xl space-y-3">@csrf @method('PUT')
        <h2 class="font-bold text-ink-900 dark:text-white">Telegram bot</h2>
        <div>
            <label class="label" for="token">Bot tokeni (@BotFather beradi)</label>
            <input id="token" name="token" type="password" autocomplete="off" class="input" placeholder="{{ $tokenMask ? 'Saqlangan: '.$tokenMask.' — o\'zgartirish uchun yangisini kiriting' : '123456789:AA...' }}">
            @error('token')<p class="error-text">{{ $message }}</p>@enderror
            @if ($tokenFromEnv)<p class="mt-1 text-xs text-ink-500">Hozir token .env dan olinmoqda ({{ $tokenMask }}). Yangisini kiritsangiz, shu sahifadagisi ishlatiladi.</p>@endif
        </div>
        <x-input name="username" label="Bot nomi (@ siz; bo'sh qoldirsangiz token bo'yicha avtomatik olinadi)" :value="old('username', $username)" />
        <x-input name="webhook_url" label="Webhook manzili (https://...)" :value="old('webhook_url', $webhookUrl)" />
        <x-input name="alert_chats" label="Qo'shimcha ogohlantirish chat ID'lari (vergul bilan; guruh ID si minus bilan boshlanadi)" :value="old('alert_chats', $alertChats)" />
        <p class="-mt-2 text-xs text-ink-500">To'lov cheki, murojaat va jarayon to'xtashi xabarlari botga ulangan barcha sAdminlarga va shu chatlarga boradi. Hozir qabul qiluvchilar: <b>{{ $alertTargets }}</b> ta.</p>
        <div class="text-sm text-ink-600 dark:text-ink-300">
            Webhook maxfiy kaliti: <b>{{ $secretMask ?: 'hali yaratilmagan' }}</b> @if ($secretFromEnv)<span class="text-xs text-ink-500">(.env dan)</span>@endif
            <label class="ml-3 inline-flex items-center gap-1.5"><input type="checkbox" name="new_secret" value="1" class="checkbox"> Yangi kalit yaratish</label>
        </div>
        <div class="flex flex-wrap gap-2"><button class="btn-primary btn-sm">Saqlash</button></div>
    </form>

    <div class="card card-body mb-6 max-w-3xl space-y-3">
        <h3 class="font-semibold text-ink-900 dark:text-white">Webhook va holat</h3>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('platform.integrations.webhook') }}">@csrf<button class="btn-primary btn-sm">Webhook o'rnatish</button></form>
            <a href="{{ route('platform.integrations', ['tekshirish' => 1]) }}" class="btn-secondary btn-sm">Holatni tekshirish</a>
            <form method="POST" action="{{ route('platform.integrations.webhook.remove') }}" onsubmit="return confirm('Bot xabar olishdan to\'xtaydi. Davom etilsinmi?')">@csrf @method('DELETE')<button class="btn-secondary btn-sm">Webhook'ni olib tashlash</button></form>
            <form method="POST" action="{{ route('platform.integrations.telegram.clear') }}" onsubmit="return confirm('Sahifada saqlangan token va kalit o\'chiriladi. Davom etilsinmi?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-600">Token va kalitni o'chirish</button></form>
        </div>
        @if ($status)
            <div class="rounded-xl bg-ink-50 p-3 text-sm dark:bg-ink-800">
                @if ($status['me']['ok'])<div>Bot: <b>{{ $status['me']['name'] }}</b> (@{{ $status['me']['username'] }}) <span class="badge-green">Token to'g'ri</span></div>
                @else<div class="text-red-600">Token xatosi: {{ $status['me']['error'] }}</div>@endif
                @if ($status['hook']['ok'])
                    <div class="mt-1">Webhook: {{ $status['hook']['url'] ?: 'o\'rnatilmagan' }} · kutilayotgan: {{ $status['hook']['pending'] }}</div>
                    @if ($status['hook']['last_error'])<div class="mt-1 text-red-600">Telegram oxirgi xatosi: {{ $status['hook']['last_error'] }}</div>@endif
                @else<div class="mt-1 text-red-600">{{ $status['hook']['error'] }}</div>@endif
            </div>
        @endif
        <p class="text-xs text-ink-500">Telegram webhook uchun HTTPS va ochiq domen kerak. Mahalliy tarmoqda (domen yo'q) bot xabar qabul qila olmaydi, lekin yuborish ishlaydi.</p>
    </div>

    {{-- Modem --}}
    <form method="POST" action="{{ route('platform.integrations.modem') }}" class="card card-body max-w-3xl space-y-3">@csrf @method('PUT')
        <h2 class="font-bold text-ink-900 dark:text-white">USB modem parametrlari</h2>
        <x-input name="ports" label="Qidiriladigan portlar (vergul bilan; masalan /dev/ttyUSB*,/dev/ttyACM*)" :value="old('ports', $modem['ports'])" required />
        <p class="-mt-2 text-xs text-ink-500">Hozir topilgan portlar: {{ $detected ? implode(', ', $detected) : 'yo\'q (modem ulanmagan yoki server Linux emas)' }}</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="label" for="baud">Port tezligi (baud)</label>
                <select id="baud" name="baud" class="input">@foreach ($bauds as $b)<option value="{{ $b }}" @selected((int) old('baud', $modem['baud']) === $b)>{{ $b }}</option>@endforeach</select>@error('baud')<p class="error-text">{{ $message }}</p>@enderror</div>
            <x-input name="daily_limit" type="number" min="0" label="Bitta SIM uchun kunlik chegara (0 = cheksiz)" :value="old('daily_limit', $modem['daily_limit'])" required />
            <x-input name="pause_min" type="number" min="0" label="Xabarlar orasidagi eng qisqa pauza (soniya)" :value="old('pause_min', $modem['pause_min'])" required />
            <x-input name="pause_max" type="number" min="0" label="Eng uzun pauza (soniya)" :value="old('pause_max', $modem['pause_max'])" required />
        </div>
        <p class="text-xs text-ink-500">Operator bloklamasligi uchun xabarlar orasida tasodifiy pauza qilinadi va SIM kuniga chegaradan ko'p yubormaydi (chegaraga yetsa, SMS Eskiz orqali ketadi).</p>
        <div><button class="btn-primary btn-sm">Saqlash</button></div>
    </form>
@endsection
