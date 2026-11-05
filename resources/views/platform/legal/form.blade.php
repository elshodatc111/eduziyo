@extends('layouts.app')
@section('title', $doc->exists ? 'Hujjat' : 'Yangi hujjat')

@section('content')
    <x-page-header :title="$doc->exists ? $doc->title : 'Yangi hujjat'" :subtitle="$doc->exists ? $doc->typeLabel().' · '.$doc->audienceLabel().' · v'.$doc->version : 'Yangi hujjat qoralamasi'" />
    @php $locked = $doc->exists && $doc->isPublished(); @endphp

    <form method="POST" action="{{ $doc->exists ? route('platform.legal.update', $doc->id) : route('platform.legal.store') }}" class="card card-body max-w-4xl space-y-4">
        @csrf @if ($doc->exists) @method('PUT') @endif
        @unless ($doc->exists)
            <div class="grid gap-3 sm:grid-cols-2">
                <x-select name="type" label="Turi">@foreach (\App\Models\LegalDocument::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $doc->type) === $k)>{{ $l }}</option>@endforeach</x-select>
                <x-select name="audience" label="Kim uchun">@foreach (\App\Models\LegalDocument::AUDIENCES as $k => $l)<option value="{{ $k }}" @selected(old('audience', $doc->audience) === $k)>{{ $l }}</option>@endforeach</x-select>
            </div>
        @endunless
        <x-input name="title" label="Sarlavha" :value="$doc->title" required maxlength="200" :disabled="$locked" />
        <div>
            <label for="body" class="label">Matn (Markdown: ## sarlavha, **qalin**, - ro'yxat)</label>
            <textarea id="body" name="body" rows="22" class="input font-mono text-sm" @disabled($locked) required>{{ old('body', $doc->body) }}</textarea>
            <p class="mt-1 text-xs text-ink-500">{platforma} yozuvi joriy platforma nomi bilan almashtiriladi.</p>
            @error('body')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_reacceptance" value="1" class="checkbox" @checked(old('requires_reacceptance', $doc->requires_reacceptance)) @disabled($locked)> Bu versiya e'lon qilinganda hamma qayta rozilik bersin</label>
        @unless ($locked)<div><button class="btn-primary">Saqlash</button></div>@endunless
    </form>

    @if ($doc->exists && ! $locked)
        <form method="POST" action="{{ route('platform.legal.publish', $doc->id) }}" class="card card-body mt-4 max-w-4xl space-y-3" onsubmit="return confirm('E\'lon qilingach matnni o\'zgartirib bo\'lmaydi. Davom etilsinmi?')">@csrf
            <h2 class="font-bold">E'lon qilish</h2>
            <x-input name="effective_at" type="date" label="Kuchga kirish sanasi (bo'sh — hozir)" />
            <div><button class="btn-primary">E'lon qilish</button></div>
        </form>
        <form method="POST" action="{{ route('platform.legal.destroy', $doc->id) }}" class="mt-3" onsubmit="return confirm('Qoralama o\'chirilsinmi?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-600">Qoralamani o'chirish</button></form>
    @endif
@endsection
