@props(['card', 'tone' => 0])
@php
    $digits = preg_replace('/\D+/', '', (string) $card->number);
    $grouped = trim(chunk_split($digits, 4, ' '));
    $tones = ['from-rose-600 via-red-600 to-orange-500', 'from-indigo-700 via-violet-600 to-fuchsia-500', 'from-emerald-700 via-teal-600 to-cyan-500', 'from-slate-800 via-slate-700 to-sky-600'];
    $grad = $tones[$tone % count($tones)];
@endphp
<div x-data="{ done: false, copy() {
        const t = @js($digits);
        const ok = () => { this.done = true; setTimeout(() => this.done = false, 1800); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(ok); return; }
        const a = document.createElement('textarea'); a.value = t; a.style.position = 'fixed'; a.style.opacity = 0; document.body.appendChild(a); a.select();
        try { document.execCommand('copy'); ok(); } catch (e) {} a.remove();
    } }"
    class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $grad }} p-5 text-white shadow-lg">
    <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
    <div class="pointer-events-none absolute -bottom-14 -left-8 h-44 w-44 rounded-full bg-white/10"></div>
    <div class="relative flex items-start justify-between">
        <div class="h-8 w-11 rounded-md bg-gradient-to-br from-amber-200 to-amber-400 shadow-inner"></div>
        <div class="text-right text-xs font-semibold uppercase tracking-wider opacity-90">{{ $card->bank ?: "To'lov kartasi" }}</div>
    </div>
    <div class="relative mt-5 font-mono text-xl font-bold tracking-widest sm:text-2xl">{{ $grouped }}</div>
    <div class="relative mt-4 flex items-end justify-between gap-3">
        <div class="min-w-0">
            <div class="text-[10px] uppercase tracking-wider opacity-70">Karta egasi</div>
            <div class="truncate text-sm font-semibold uppercase">{{ $card->holder }}</div>
        </div>
        <button type="button" @click="copy()" class="shrink-0 rounded-lg bg-white/20 px-3 py-1.5 text-xs font-semibold backdrop-blur transition hover:bg-white/30" :class="done && 'bg-white text-emerald-700'">
            <span x-show="!done">Nusxalash</span><span x-show="done" x-cloak>✓ Nusxalandi</span>
        </button>
    </div>
    @if ($card->note)<div class="relative mt-3 border-t border-white/20 pt-2 text-xs opacity-90">{{ $card->note }}</div>@endif
</div>
