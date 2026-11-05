@extends('layouts.app')
@section('title', 'Tariflar')

@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

@section('content')
    <x-page-header title="Tarif tanlash" subtitle="To'lov markaz balansidan yechiladi" />
    @include('center._nav')

    <div class="mb-5 rounded-xl border border-ink-200 bg-white px-4 py-3 text-sm dark:border-ink-800 dark:bg-ink-900">
        Balans: <b>{{ $fmt($balance) }} so'm</b>.
        @if ($sub) Joriy: <b>{{ $sub->name() }}</b> ({{ $sub->ends_at->format('d.m.Y') }} gacha). @endif
        <span class="text-ink-500">Qimmatroq tarif darhol yoqiladi (eski tarif qoldig'i balansga qaytadi); arzonroq yoki bir xil tarif joriy davr tugagach boshlanadi.</span>
    </div>

    @if ($lockedNow->isNotEmpty())
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Joriy tarif filial limitidan oshgan: <b>{{ $lockedNow->pluck('name')->implode(', ') }}</b> filiallari hozir <b>faqat ko'rish</b> rejimida. Ko'proq filialli tarifni tanlasangiz, ular qayta ishlaydi.
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        @foreach ($tariffs as $t)
            <div class="card card-body">
                <div class="flex items-center gap-2"><h2 class="text-lg font-bold text-ink-900 dark:text-white">{{ $t->name }}</h2>@if ($t->is_featured)<span class="badge-red">Mashhur</span>@endif</div>
                <ul class="mt-3 space-y-1 text-xs text-ink-500">
                    <li>Filial: {{ $t->max_branches ?? 'cheksiz' }}</li><li>O'quvchi: {{ $t->max_students ?? 'cheksiz' }}</li>
                    <li>SMS / oy: {{ $t->sms_monthly }}</li><li>AI / oy: {{ $t->ai_monthly }}</li>
                </ul>
                <div class="mt-4 space-y-2">
                    @foreach (['month' => ['Oylik', $t->price_monthly], 'year' => ['Yillik', $t->price_yearly]] as $period => [$label, $price])
                        @if ($price)
                            @php $pv = $previews[$t->id][$period] ?? null; @endphp
                            @if ($pv)
                                <div class="rounded-lg border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900">
                                    <b>Diqqat:</b> bu tarifda {{ $t->max_branches }} ta filial ishlaydi. <b>{{ implode(', ', $pv['names']) }}</b> filiali {{ $pv['from']->format('d.m.Y') }} dan <b>faqat ko'rish</b> rejimiga o'tadi (ma'lumot saqlanadi).
                                </div>
                            @endif
                            @php $msg = $t->name.' ('.$label.') — '.$fmt($price)." so'm balansdan yechiladi.".($pv ? " DIQQAT: ".implode(', ', $pv['names'])." filiali faqat ko'rish rejimiga o'tadi." : '').' Davom etasizmi?'; @endphp
                            <form method="POST" action="{{ route('center.tariffs.buy', $t) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from($msg) }})">@csrf
                                <input type="hidden" name="period" value="{{ $period }}">
                                @if ($pv)<input type="hidden" name="ack_branches" value="1">@endif
                                <button class="btn-primary w-full justify-between"><span>{{ $label }}</span><span>{{ $fmt($price) }} so'm</span></button>
                            </form>
                        @endif
                    @endforeach
                    @unless ($t->price_monthly || $t->price_yearly)<p class="text-xs text-ink-500">Narx belgilanmagan.</p>@endunless
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 card overflow-hidden">
        <div class="border-b border-ink-100 px-5 py-3 font-bold text-ink-900 dark:border-ink-800 dark:text-white">Obunalar tarixi</div>
        <table class="table"><tbody>
            @forelse ($history as $h)
                <tr><td>{{ $h->name() }}<div class="text-xs text-ink-500">{{ $h->source === 'grant' ? 'sAdmin sovg\'asi' : ($h->period === 'year' ? 'Yillik' : 'Oylik') }}</div></td>
                    <td>{{ $h->starts_at->format('d.m.Y') }} — {{ $h->ends_at->format('d.m.Y') }}</td><td class="text-right">{{ $fmt($h->price_paid) }}</td></tr>
            @empty
                <tr><td class="text-center text-ink-500">Hozircha obuna yo'q.</td></tr>
            @endforelse
        </tbody></table>
    </div>
@endsection
