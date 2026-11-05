@extends('layouts.cabinet')
@section('title', 'Test')
@section('content')
<h1 class="mb-1 text-lg font-extrabold text-ink-900 dark:text-white">{{ $course->name }} — test</h1>
<p class="mb-4 text-sm text-ink-500">{{ $attempt->total }} ta savol. Har bir savolga bitta javob tanlang.</p>
<form method="POST" action="{{ route('cabinet.test.submit', $attempt->id) }}" class="space-y-4">
    @csrf
    @foreach ($attempt->questions as $qi => $q)
        <fieldset class="card"><div class="card-body">
            <legend class="mb-3 text-sm font-semibold text-ink-900 dark:text-white">{{ $qi + 1 }}. {{ $q['question'] }}</legend>
            <div class="space-y-2">
                @foreach ($q['options'] as $oi => $opt)
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-200 px-3 py-2.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-ink-700 dark:has-[:checked]:bg-brand-950/40">
                        <input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $oi }}" class="text-brand-600">
                        <span class="text-ink-800 dark:text-ink-100">{{ $opt }}</span>
                    </label>
                @endforeach
            </div>
        </div></fieldset>
    @endforeach
    <button class="btn-primary w-full">Yakunlash</button>
</form>
@endsection
