@extends('layouts.cabinet')
@section('title', 'Test natijasi')
@section('content')
<div class="card mb-5"><div class="card-body text-center">
    <div class="text-sm text-ink-500">{{ $course->name }}</div>
    <div class="mt-2 text-5xl font-black {{ $result['score'] >= 60 ? 'text-emerald-600' : 'text-brand-600' }}">{{ $result['score'] }}%</div>
    <div class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $result['correct'] }} / {{ $result['total'] }} to'g'ri javob</div>
</div></div>
<div class="space-y-3">
    @foreach ($result['details'] as $i => $d)
        @php $q = $questions[$i]; @endphp
        <div class="card"><div class="card-body text-sm">
            <div class="font-semibold text-ink-900 dark:text-white">{{ $i + 1 }}. {{ $q['question'] }}</div>
            <div class="mt-2 {{ $d['is_correct'] ? 'text-emerald-600' : 'text-brand-600' }}">Sizning javobingiz: {{ $d['chosen'] !== null ? ($q['options'][$d['chosen']] ?? '—') : 'javob berilmagan' }}</div>
            @unless ($d['is_correct'])<div class="mt-1 text-emerald-600">To'g'ri javob: {{ $d['correct_answer'] }}</div>@endunless
        </div></div>
    @endforeach
</div>
<a href="{{ route('cabinet.courses.show', $course) }}" class="btn-primary mt-5 block text-center">Kursga qaytish</a>
@endsection
