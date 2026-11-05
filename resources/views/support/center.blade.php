@extends('layouts.app')
@section('title', 'Murojaat')

@php $t = $viewing ?? $current; @endphp

@section('content')
    <x-page-header title="Qo'llab-quvvatlash" subtitle="Savol yoki muammo bo'lsa yozing. Javob ish vaqtida beriladi" />
    @include('center._nav')

    @if ($t)
        <div class="card card-body">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div><div class="text-lg font-bold text-ink-900 dark:text-white">{{ $t->subject }}</div>
                    <span class="{{ $t->isClosed() ? 'badge-gray' : ($t->status === 'open' ? 'badge-amber' : 'badge-green') }}">{{ $t->statusLabel() }}</span>
                    @if ($t->sla_due_at && $t->status === 'open' && ! $t->first_response_at)<span class="ml-2 text-xs text-ink-500">Javob muddati: {{ $t->sla_due_at->format('d.m.Y H:i') }}</span>@endif</div>
                @unless ($t->isClosed())<form method="POST" action="{{ route('center.support.close', $t->id) }}" onsubmit="return confirm('Murojaat yopilsinmi?')">@csrf<button class="btn-secondary btn-sm">Yopish</button></form>@endunless
            </div>
            @include('support._thread', ['messages' => $messages])

            @unless ($t->isClosed())
                <form method="POST" action="{{ route('center.support.reply', $t->id) }}" class="mt-5 space-y-2" enctype="multipart/form-data">@csrf
                    <textarea name="body" rows="3" class="input" placeholder="Xabaringiz..." required maxlength="5000"></textarea>
                    @error('body')<p class="error-text">{{ $message }}</p>@enderror
                    @include('support._attach')
                    <button class="btn-primary btn-sm">Yuborish</button>
                </form>
            @elseif ($t->rating === null)
                <form method="POST" action="{{ route('center.support.rate', $t->id) }}" class="mt-5 space-y-2 border-t border-ink-100 pt-4 dark:border-ink-800">@csrf
                    <div class="text-sm font-semibold">Xizmatga baho bering</div>
                    <div class="flex gap-3">@foreach ([1, 2, 3, 4, 5] as $n)<label class="flex items-center gap-1 text-sm"><input type="radio" name="rating" value="{{ $n }}" required> {{ $n }} ★</label>@endforeach</div>
                    <input name="comment" class="input" maxlength="255" placeholder="Izoh (ixtiyoriy)">
                    <button class="btn-primary btn-sm">Baho berish</button>
                </form>
            @else
                <div class="mt-4 text-sm text-ink-500">Bahoyingiz: {{ $t->rating }} ★ @if ($t->rating_comment)· {{ $t->rating_comment }}@endif</div>
            @endunless
        </div>
    @else
        <form method="POST" action="{{ route('center.support.store') }}" class="card card-body max-w-2xl space-y-3" enctype="multipart/form-data">@csrf
            <h2 class="font-bold text-ink-900 dark:text-white">Yangi murojaat</h2>
            <x-input name="subject" label="Mavzu" required maxlength="160" />
            <div><label class="label" for="body">Xabar</label><textarea id="body" name="body" rows="5" class="input" required maxlength="5000">{{ old('body') }}</textarea>@error('body')<p class="error-text">{{ $message }}</p>@enderror</div>
            @include('support._attach')
            <button class="btn-primary w-fit">Yuborish</button>
            <p class="text-xs text-ink-500">Bir vaqtda faqat bitta ochiq murojaat bo'lishi mumkin.</p>
        </form>
    @endif

    @if ($history->isNotEmpty())
        <div class="mt-6 card overflow-hidden"><div class="border-b border-ink-100 px-5 py-3 font-bold dark:border-ink-800">Oldingi murojaatlar</div>
            <table class="table"><tbody>@foreach ($history as $h)<tr><td>{{ $h->created_at->format('d.m.Y') }}</td><td><a class="font-semibold text-brand-600" href="{{ route('center.support.show', $h->id) }}">{{ $h->subject }}</a></td><td>{{ $h->rating ? $h->rating.' ★' : 'baholanmagan' }}</td></tr>@endforeach</tbody></table></div>
    @endif
@endsection
