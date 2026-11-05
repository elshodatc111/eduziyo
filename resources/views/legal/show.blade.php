@extends('landing.layout')
@section('title', $label.' · '.\App\Support\Brand::name())

@section('content')
<section class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
    <span class="badge-red">Hujjat</span>
    <h1 class="mt-4 text-3xl font-black tracking-tight text-ink-900">{{ $label }}</h1>
    @forelse ($docs as $d)
        <article class="mt-8 rounded-3xl border border-ink-100 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-4 flex flex-wrap items-center gap-2 text-xs text-ink-500">
                <span class="badge-gray">{{ $d->audienceLabel() }}</span>
                <span>Versiya {{ $d->version }}</span>
                <span>· Kuchga kirgan: {{ $d->effective_at?->format('d.m.Y') }}</span>
            </div>
            <h2 class="text-xl font-bold text-ink-900">{{ $d->title }}</h2>
            <div class="legal-body mt-4 text-sm leading-relaxed text-ink-700">{!! $d->html() !!}</div>
        </article>
    @empty
        <p class="mt-8 text-ink-500">Bu hujjat tez orada e'lon qilinadi.</p>
    @endforelse
</section>
@endsection
