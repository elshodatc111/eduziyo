<div class="mb-5 flex flex-wrap gap-2 text-sm">
    @foreach ([['center.index', 'Umumiy', route('center.index')], ['center.tariffs', 'Tariflar', route('center.tariffs')], ['center.topup', 'Balans', route('center.topup')], ['center.sms', 'SMS', route('center.sms')], ['center.directors', 'Direktorlar', route('center.directors')], ['center.support*', 'Murojaat', route('center.support')], ['center.analytics', 'Tahlil', route('center.analytics')]] as [$r, $l, $u])
        <a href="{{ $u }}" class="{{ request()->routeIs($r) ? 'btn-primary' : 'btn-secondary' }} btn-sm">{{ $l }}</a>
    @endforeach
</div>
