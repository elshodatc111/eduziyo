@extends('layouts.app')
@section('title', $ticket->subject)

@section('content')
    <x-page-header :title="$ticket->subject" :subtitle="($center?->name ?? '—').' · '.$ticket->statusLabel()">
        <x-slot:actions><a href="{{ route('platform.support') }}" class="btn-secondary">← Murojaatlar</a></x-slot:actions>
    </x-page-header>

    <div class="card card-body">
        @include('support._thread', ['messages' => $messages])
        @unless ($ticket->isClosed())
            <form method="POST" action="{{ route('platform.support.reply', $ticket->id) }}" class="mt-5 space-y-2" enctype="multipart/form-data">@csrf
                <textarea name="body" rows="3" class="input" placeholder="Javobingiz..." required maxlength="5000"></textarea>
                @include('support._attach')
                <div class="flex gap-2"><button class="btn-primary btn-sm">Javob berish</button></div>
            </form>
            <form method="POST" action="{{ route('platform.support.close', $ticket->id) }}" class="mt-2" onsubmit="return confirm('Murojaat yopilsinmi?')">@csrf<button class="btn-secondary btn-sm">Yopish</button></form>
        @else
            <div class="mt-4 text-sm text-ink-500">Yopilgan: {{ $ticket->closed_at?->format('d.m.Y H:i') }} ({{ $ticket->closed_reason === 'auto' ? 'avtomatik' : 'qo\'lda' }}) · baho: {{ $ticket->rating ? $ticket->rating.' ★' : 'yo\'q' }} {{ $ticket->rating_comment }}</div>
        @endunless
    </div>
@endsection
