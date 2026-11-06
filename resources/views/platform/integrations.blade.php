@extends('layouts.app')
@section('title', 'Integratsiyalar')

@section('content')
    <x-page-header title="Integratsiyalar" subtitle="AI (OpenAI/Claude), SMS Gateway, Eskiz va Telegram bot sozlamalari. Bu yerda saqlangan qiymat .env dagisidan ustun turadi">
    </x-page-header>

    {{-- AI --}}
    <form method="POST" action="{{ route('platform.integrations.ai') }}" class="card card-body mb-4 max-w-3xl space-y-3">@csrf @method('PUT')
        <h2 class="font-bold text-ink-900 dark:text-white">AI yordamchi (OpenAI va Claude)</h2>
        <p class="text-xs text-ink-500">Ikkalasi bir xil vazifani bajaradi. Asosiy provayder xato bersa (kalit noto'g'ri, mablag' tugagan, xizmat ishlamayapti) so'rov avtomatik ikkinchisiga o'tadi va sizga Telegram'da xabar keladi. Markazlarning AI limiti ularning tarifidan olinadi.</p>
        <div>
            <label class="label" for="primary">Asosiy provayder</label>
            <select id="primary" name="primary" class="input">
                @foreach (\App\Services\OpenAiService::PROVIDERS as $k => $label)
                    <option value="{{ $k }}" @selected(old('primary', $ai['primary']) === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label" for="openai_key">OpenAI kaliti</label>
                <input id="openai_key" name="openai_key" type="password" autocomplete="off" class="input" placeholder="{{ $ai['openai_mask'] ? 'Saqlangan: '.$ai['openai_mask'].' — almashtirish uchun yangisini kiriting' : 'sk-...' }}">
                @error('openai_key')<p class="error-text">{{ $message }}</p>@enderror
                @if ($ai['openai_env'])<p class="mt-1 text-xs text-ink-500">Hozir kalit .env dan olinmoqda.</p>@endif
            </div>
            <x-input name="openai_model" label="OpenAI modeli" :value="old('openai_model', $ai['openai_model'])" />
            <div>
                <label class="label" for="anthropic_key">Claude (Anthropic) kaliti</label>
                <input id="anthropic_key" name="anthropic_key" type="password" autocomplete="off" class="input" placeholder="{{ $ai['anthropic_mask'] ? 'Saqlangan: '.$ai['anthropic_mask'].' — almashtirish uchun yangisini kiriting' : 'sk-ant-...' }}">
                @error('anthropic_key')<p class="error-text">{{ $message }}</p>@enderror
                @if ($ai['anthropic_env'])<p class="mt-1 text-xs text-ink-500">Hozir kalit .env dan olinmoqda.</p>@endif
            </div>
            <x-input name="anthropic_model" label="Claude modeli" :value="old('anthropic_model', $ai['anthropic_model'])" />
        </div>
        <p class="text-xs text-ink-500">Faol provayderlar: <b>{{ $ai['active'] ? implode(' → ', array_map(fn ($p) => \App\Services\OpenAiService::PROVIDERS[$p], $ai['active'])) : 'hech biri (kalit kiritilmagan)' }}</b></p>
        <div class="flex flex-wrap gap-2"><button class="btn-primary btn-sm">Saqlash</button></div>
    </form>
    <div class="mb-6 flex max-w-3xl flex-wrap gap-2">
        <form method="POST" action="{{ route('platform.integrations.ai.test') }}">@csrf<button class="btn-secondary btn-sm">Sinab ko'rish</button></form>
        @foreach (['openai' => 'OpenAI', 'anthropic' => 'Claude'] as $pk => $pl)
            @if ($ai[$pk.'_mask'] && ! $ai[$pk.'_env'])
                <form method="POST" action="{{ route('platform.integrations.ai.clear') }}" onsubmit="return confirm('{{ $pl }} kaliti o\'chiriladi. Davom etilsinmi?')">@csrf @method('DELETE')<input type="hidden" name="provider" value="{{ $pk }}"><button class="btn-ghost btn-sm text-red-600">{{ $pl }} kalitini o'chirish</button></form>
            @endif
        @endforeach
    </div>

    {{-- Eskiz (platforma hisobi) --}}
    <form method="POST" action="{{ route('platform.integrations.eskiz') }}" class="card card-body mb-4 max-w-3xl space-y-3">@csrf @method('PUT')
        <h2 class="font-bold text-ink-900 dark:text-white">Eskiz.uz (platforma hisobi)</h2>
        <p class="text-xs text-ink-500">SMS Gateway ishlamay qolganda tarifdagi SMS'lar shu hisob orqali ketadi. Faqat <a class="font-semibold text-brand-600" href="{{ route('platform.sms.templates') }}">«SMS shablonlar»da tasdiqlangan</a> matnlar yuboriladi. Markazlar o'z Eskiz akkauntini «Markaz → SMS» bo'limida ulaydi.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-input name="email" type="email" label="Eskiz email" :value="old('email', $eskiz['email'])" />
            <div>
                <label class="label" for="eskiz_password">Eskiz paroli</label>
                <input id="eskiz_password" name="password" type="password" autocomplete="new-password" class="input" placeholder="{{ $eskiz['mask'] ? 'Saqlangan: '.$eskiz['mask'].' — almashtirish uchun yangisini kiriting' : '' }}">
                @error('password')<p class="error-text">{{ $message }}</p>@enderror
                @if ($eskiz['env'])<p class="mt-1 text-xs text-ink-500">Hozir parol .env dan olinmoqda.</p>@endif
            </div>
            <x-input name="from" label="Yuboruvchi nomi" :value="old('from', $eskiz['from'] ?: '4546')" />
        </div>
        <div class="flex flex-wrap gap-2"><button class="btn-primary btn-sm">Saqlash va tekshirish</button></div>
    </form>

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

    {{-- SMS Gateway --}}
    <form method="POST" action="{{ route('platform.integrations.gateway') }}" class="card card-body mb-4 max-w-3xl space-y-3">@csrf @method('PUT')
        <h2 class="font-bold text-ink-900 dark:text-white">SMS Gateway (asosiy SMS kanali)</h2>
        <p class="text-xs text-ink-500">Tarif ichidagi SMS'lar avval shu gateway orqali ketadi. Gateway javob bermasa, rad etsa yoki xabar yuborilmay qolsa SMS avtomatik Eskiz'ga o'tadi (faqat tasdiqlangan matn).</p>
        <x-input name="url" label="Gateway manzili" :value="old('url', $gateway['url'])" required />
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label" for="gw_key">API kaliti (sk_sms_...)</label>
                <input id="gw_key" name="key" type="password" autocomplete="off" class="input" placeholder="{{ $gateway['key_mask'] ? 'Saqlangan: '.$gateway['key_mask'].' — almashtirish uchun yangisini kiriting' : 'sk_sms_...' }}">
                @error('key')<p class="error-text">{{ $message }}</p>@enderror
                @if ($gateway['key_env'])<p class="mt-1 text-xs text-ink-500">Hozir .env dan olinmoqda.</p>@endif
            </div>
            <div>
                <label class="label" for="gw_secret">Webhook siri</label>
                <input id="gw_secret" name="secret" type="password" autocomplete="off" class="input" placeholder="{{ $gateway['secret_mask'] ? 'Saqlangan: '.$gateway['secret_mask'].' — almashtirish uchun yangisini kiriting' : 'Gateway loyiha sozlamalaridagi sir' }}">
                @if ($gateway['secret_env'])<p class="mt-1 text-xs text-ink-500">Hozir .env dan olinmoqda.</p>@endif
            </div>
        </div>
        <p class="text-xs text-ink-500">Gateway loyiha sozlamalarida <b>Webhook URL</b> sifatida kiriting: <span class="select-all font-mono">{{ $gateway['webhook'] }}</span> va shu yerdagi bilan bir xil sirni belgilang.</p>
        <div class="flex flex-wrap gap-2"><button class="btn-primary btn-sm">Saqlash</button></div>
    </form>
    <form method="POST" action="{{ route('platform.integrations.gateway.test') }}" class="mb-6 max-w-3xl">@csrf<button class="btn-secondary btn-sm">Gateway'ni tekshirish</button></form>
@endsection
