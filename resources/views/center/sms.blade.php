@extends('layouts.app')
@section('title', 'SMS')

@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

@section('content')
    <x-page-header title="SMS" subtitle="Tarif limiti, o'z Eskiz akkauntingiz va yuborilgan xabarlar" />
    @include('center._nav')

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="card card-body"><div class="text-sm text-ink-500">Oylik tarif limiti</div><div class="mt-1 text-2xl font-bold">{{ $limit === null ? 'Cheksiz' : $fmt($limit) }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Shu oy ishlatilgan</div><div class="mt-1 text-2xl font-bold">{{ $fmt($used) }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Eskiz akkaunti</div>
            <div class="mt-1"><span class="{{ $account ? ($account->isUsable() ? 'badge-green' : 'badge-red') : 'badge-gray' }}">{{ $account ? ($account->isUsable() ? 'Ulangan' : 'Xato') : 'Ulanmagan' }}</span></div></div>
    </div>

    @if ($exhausted && ! ($account && $account->isUsable()))
        <div class="mb-5 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <b>Tarifdagi SMS limiti tugadi.</b> Yangi xabarlar yuborilmayapti. Davom ettirish uchun quyida o'z Eskiz akkauntingizni ulang yoki tarifni yangilang.
        </div>
    @elseif ($nearLimit && ! $account)
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            Tarif SMS limitining 80% dan ko'pi ishlatildi. Limit tugab qolsa ham SMS to'xtamasligi uchun <b>Eskiz akkauntingizni ulashni tavsiya qilamiz</b>.
        </div>
    @endif

    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('center.sms.eskiz.connect') }}" class="card card-body space-y-3">@csrf @method('PUT')
            <h2 class="font-bold text-ink-900 dark:text-white">Markazning Eskiz akkaunti</h2>
            <p class="text-xs text-ink-500">Bitta akkaunt butun markaz (barcha filiallar) uchun. SMS avval tarif limitidan sarflanadi; limit tugagach xabarlar shu akkaunt orqali ketadi va Eskiz hisobingizdan to'lanadi. Faqat platforma tasdiqlagan matnlar yuboriladi.</p>
            @if ($account && ! $account->isUsable())<div class="rounded-lg bg-red-50 p-2 text-xs text-red-700">Xato: {{ $account->last_error }}</div>@endif
            <x-input name="email" type="email" label="Eskiz email" :value="old('email', $account?->email)" required />
            <x-input name="password" type="password" label="Eskiz paroli" :hint="$account ? 'O\'zgartirmaslik uchun bo\'sh qoldiring.' : null" autocomplete="new-password" />
            <x-input name="sender" label="Yuboruvchi nomi (Eskiz bergan)" :value="old('sender', $account?->sender ?? '4546')" required />
            <div class="flex flex-wrap gap-2"><button class="btn-primary btn-sm">{{ $account ? 'Yangilash' : 'Ulash' }}</button></div>
        </form>
        @if ($account)
            <form method="POST" action="{{ route('center.sms.eskiz.disconnect') }}" class="hidden" id="eskiz-off">@csrf @method('DELETE')</form>
        @endif

        <div class="card card-body">
            <h2 class="font-bold text-ink-900 dark:text-white">SMS matnlari (Eskiz tasdig'i)</h2>
            <p class="mb-2 text-xs text-ink-500">Platforma tasdiqlagan matnlar akkauntingiz ulanganda Eskiz moderatsiyasiga avtomatik yuboriladi. Tasdiqlangunga qadar limit tugagach xabarlar o'tmasligi mumkin.</p>
            @if (! $account)
                <p class="text-sm text-ink-500">Eskiz ulangach shu yerda har bir matnning holati ko'rinadi.</p>
            @else
                <div class="table-wrap"><table class="table"><tbody>
                    @forelse ($templates as $t)
                        @php $st = $sync[$t->key]->status ?? 'none'; @endphp
                        <tr><td class="text-sm">{{ $t->label }}</td>
                            <td class="text-right"><span class="{{ ['approved' => 'badge-green', 'pending' => 'badge-amber', 'rejected' => 'badge-red', 'error' => 'badge-red'][$st] ?? 'badge-gray' }}">{{ ['approved' => 'Tasdiqlangan', 'pending' => 'Moderatsiyada', 'rejected' => 'Rad etilgan', 'error' => 'Xato', 'none' => 'Yuborilmagan'][$st] }}</span></td></tr>
                    @empty<tr><td class="py-4 text-center text-ink-500">Platforma hali matnlarni tasdiqlamagan.</td></tr>@endforelse
                </tbody></table></div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('center.sms.eskiz.resync') }}">@csrf<button class="btn-secondary btn-sm">Holatni yangilash</button></form>
                    <button form="eskiz-off" class="btn-ghost btn-sm text-red-600" onclick="return confirm('Eskiz akkaunti uziladi. Davom etilsinmi?')">Akkauntni uzish</button>
                </div>
            @endif
        </div>
    </div>

    <div class="card overflow-hidden">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 dark:border-ink-800 sm:grid-cols-3">
            <input name="q" value="{{ \App\Support\SafeInput::string(request('q')) }}" placeholder="Telefon raqami" class="input">
            <select name="status" class="input" onchange="this.form.submit()"><option value="">Barcha holatlar</option>
                @foreach (['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
            <button class="btn-secondary">Qidirish</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Sana</th><th>Telefon</th><th>Xabar</th><th>Kanal</th><th>Holat</th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr><td class="whitespace-nowrap">{{ $m->created_at->format('d.m.Y H:i') }}</td><td>{{ \App\Support\Format::prettyPhone($m->phone) }}</td><td class="max-w-md text-sm">{{ $m->message }}</td>
                    <td>{{ ['gateway' => 'Gateway', 'own' => 'Sizning Eskiz'][$m->channel] ?? 'Eskiz' }}</td>
                    <td><span class="{{ ['sent' => 'badge-green', 'queued' => 'badge-amber', 'failed' => 'badge-red'][$m->status] ?? 'badge-gray' }}">{{ ['sent' => 'Yuborilgan', 'queued' => 'Navbatda', 'failed' => 'Xato'][$m->status] ?? $m->status }}</span></td></tr>
            @empty<tr><td colspan="5" class="py-10 text-center text-ink-500">Xabar yo'q.</td></tr>@endforelse
            </tbody></table></div>
        {{ $messages->links() }}
    </div>

@endsection
