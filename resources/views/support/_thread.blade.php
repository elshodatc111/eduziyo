<div class="space-y-3">
    @foreach ($messages as $m)
        <div class="flex {{ $m->is_staff ? 'justify-start' : 'justify-end' }}">
            <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm {{ $m->is_staff ? 'bg-ink-100 text-ink-900 dark:bg-ink-800 dark:text-white' : 'bg-brand-600 text-white' }}">
                <div class="mb-0.5 text-[11px] opacity-70">{{ $m->is_staff ? 'Qo\'llab-quvvatlash' : ($m->author?->name ?? 'Direktor') }} · {{ $m->created_at->format('d.m.Y H:i') }}</div>
                <div class="whitespace-pre-line break-words">{{ $m->body }}</div>
                @if ($m->attachments->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($m->attachments as $a)
                            @php $url = auth()->user()->isSuperAdmin() ? route('platform.support.file', $a->id) : route('center.support.file', $a->id); @endphp
                            <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="{{ $a->original_name }}" loading="lazy" class="h-24 max-w-[10rem] rounded-lg border border-white/30 object-cover"></a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
