@extends('layouts.guest')
@section('title', 'Hujjatlarga rozilik')

@section('content')
    <h1 class="text-xl font-bold text-ink-900 dark:text-white">Davom etish uchun hujjatlar bilan tanishing</h1>
    <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400">Quyidagi hujjatlarni o'qib chiqing va rozilik bering. Rozilik sana va vaqt bilan saqlanadi.</p>

    <form method="POST" action="{{ route('legal.accept') }}" class="mt-6 space-y-4">
        @csrf
        @foreach ($docs as $d)
            <details class="rounded-xl border border-ink-200 p-3 dark:border-ink-700" @if ($loop->first) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-ink-900 dark:text-white">{{ $d->title }} <span class="text-xs font-normal text-ink-500">· v{{ $d->version }}</span></summary>
                <div class="legal-body mt-3 max-h-64 overflow-y-auto pr-1 text-sm leading-relaxed text-ink-700 dark:text-ink-300">{!! $d->html() !!}</div>
            </details>
        @endforeach

        <label class="flex items-start gap-2 text-sm text-ink-700 dark:text-ink-200">
            <input type="checkbox" name="agree" value="1" class="checkbox mt-0.5" required>
            <span>Yuqoridagi hujjatlar bilan tanishdim va ularga roziman.</span>
        </label>
        @error('agree')<p class="error-text">{{ $message }}</p>@enderror

        <button type="submit" class="btn-primary w-full py-3">Roziman, davom etish</button>
        <button type="submit" name="refuse" value="1" formnovalidate class="block w-full text-center text-sm font-semibold text-ink-500 hover:text-red-600">Rozi emasman — chiqish</button>
    </form>
@endsection
