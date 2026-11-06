@extends('layouts.app')
@section('title', 'SMS shablonlar')

@section('content')
    <x-page-header title="SMS shablonlar" subtitle="Umumiy matnlarni tahrirlang va tasdiqlang. Eskiz orqali faqat tasdiqlangan matnlar ketadi">
        <x-slot:actions>
            <form method="POST" action="{{ route('platform.sms.templates.refresh') }}">@csrf<button class="btn-secondary">Eskiz holatini yangilash</button></form>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="card card-body text-sm"><div class="text-ink-500">Platforma Eskiz hisobi</div>
            <span class="{{ $platformEskiz ? 'badge-green' : 'badge-red' }} mt-1 w-fit">{{ $platformEskiz ? 'Kiritilgan' : 'Kiritilmagan' }}</span>
            <a class="mt-1 text-xs font-semibold text-brand-600" href="{{ route('platform.integrations') }}">Integratsiyalar →</a></div>
        <div class="card card-body text-sm"><div class="text-ink-500">Eskiz ulagan markazlar</div><div class="mt-1 text-2xl font-bold">{{ $connected }}</div></div>
        <div class="card card-body text-sm text-ink-600">Matnni o'zgartirsangiz tasdiq bekor bo'ladi. Tasdiqlaganda matn platforma hisobiga va Eskiz ulagan barcha markazlarga moderatsiyaga avtomatik yuboriladi. O'zgaruvchilar: <span class="font-mono text-xs">{{ implode(' ', $placeholders) }}</span></div>
    </div>

    <div class="space-y-4">
        @foreach ($templates as $t)
            @php $c = $sync[$t->key] ?? []; @endphp
            <div class="card card-body">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-bold text-ink-900 dark:text-white">{{ $t->label }} @if ($t->is_system)<span class="badge-gray ml-1">tizim</span>@endif</div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="{{ $t->is_approved ? 'badge-green' : 'badge-amber' }}">{{ $t->is_approved ? 'Tasdiqlangan' : 'Tasdiqlanmagan' }}</span>
                        @if ($t->is_approved)
                            <span class="{{ ['approved' => 'badge-green', 'pending' => 'badge-amber', 'rejected' => 'badge-red', 'error' => 'badge-red'][$t->eskiz_status] ?? 'badge-gray' }}" @if($t->eskiz_error) title="{{ $t->eskiz_error }}" @endif>Platforma Eskiz: {{ ['approved' => 'tasdiqlangan', 'pending' => 'moderatsiyada', 'rejected' => 'rad etilgan', 'error' => 'xato', 'none' => 'yuborilmagan'][$t->eskiz_status] ?? $t->eskiz_status }}</span>
                            <span class="badge-gray">Markazlarda: {{ $c['approved'] ?? 0 }} tasdiq · {{ $c['pending'] ?? 0 }} kutilmoqda · {{ ($c['rejected'] ?? 0) + ($c['error'] ?? 0) }} xato</span>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('platform.sms.templates.update', $t->key) }}" class="mt-3 space-y-2">@csrf @method('PUT')
                    <textarea name="body" rows="2" maxlength="320" class="input">{{ old('body', $t->body) }}</textarea>
                    @error('body')<p class="error-text">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap gap-2"><button class="btn-secondary btn-sm">Matnni saqlash</button></div>
                </form>
                <div class="mt-2 flex flex-wrap gap-2">
                    @if ($t->is_approved)
                        <form method="POST" action="{{ route('platform.sms.templates.revoke', $t->key) }}" onsubmit="return confirm('Tasdiq qaytariladi: matn Eskiz orqali ketmaydi. Davom etilsinmi?')">@csrf<button class="btn-ghost btn-sm text-red-600">Tasdiqni qaytarish</button></form>
                    @else
                        <form method="POST" action="{{ route('platform.sms.templates.approve', $t->key) }}">@csrf<button class="btn-primary btn-sm">Tasdiqlash va Eskiz'ga yuborish</button></form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection
