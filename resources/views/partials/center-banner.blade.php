@php
    $bannerUser = auth()->user();
    $bannerCenter = $bannerUser && $bannerUser->center_id && ! $bannerUser->isSuperAdmin() ? \App\Models\Center::find($bannerUser->center_id) : null;
    $bannerText = null;
    $bannerTone = 'amber';
    if ($bannerCenter && ! $bannerCenter->is_demo) {
        if ($bannerCenter->status === \App\Enums\CenterStatus::Pending) {
            $bannerText = "Tarif tanlanmagan: tizim faqat ko'rish rejimida.";
            $bannerTone = 'red';
        } elseif ($bannerCenter->status === \App\Enums\CenterStatus::ReadOnly) {
            $left = $bannerCenter->delete_after ? max(0, (int) now()->diffInDays($bannerCenter->delete_after, false)) : null;
            $bannerText = "Tarif muddati tugagan: faqat ko'rish rejimi.".($left !== null ? " Tarif olinmasa ma'lumot {$left} kundan keyin o'chiriladi." : '');
            $bannerTone = 'red';
        } elseif ($bannerCenter->tariff_ends_at && $bannerCenter->tariff_ends_at->isFuture() && now()->diffInDays($bannerCenter->tariff_ends_at, false) <= 7) {
            $bannerText = 'Tarif '.$bannerCenter->tariff_ends_at->format('d.m.Y').' kuni tugaydi.';
        }
    }
@endphp
@if ($bannerText)
    <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2 text-center text-xs font-semibold {{ $bannerTone === 'red' ? 'bg-red-600 text-white' : 'bg-amber-400 text-ink-900' }}">
        <span>{{ $bannerText }}</span>
        @if ($bannerUser->role === \App\Enums\Role::Owner)
            <a href="{{ route('center.tariffs') }}" class="rounded-full bg-white/90 px-3 py-0.5 font-bold text-ink-900 hover:bg-white">Tarifni ko'rish</a>
        @else
            <span>Direktorga murojaat qiling.</span>
        @endif
    </div>
@endif
