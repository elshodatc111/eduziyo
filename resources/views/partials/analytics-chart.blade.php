{{-- Grafik / Jadval almashtirgichli oddiy ustunli diagramma. $title, $labels (list), $values (list), $unit (ixtiyoriy) --}}
@php $max = max(1, max($values ?: [0])); $unit = $unit ?? ''; @endphp
<div class="card card-body" x-data="{ mode: 'chart' }">
    <div class="mb-3 flex items-center justify-between">
        <h3 class="font-bold text-ink-900 dark:text-white">{{ $title }}</h3>
        <div class="flex gap-1 text-xs">
            <button type="button" @click="mode = 'chart'" :class="mode === 'chart' ? 'btn-primary' : 'btn-secondary'" class="btn-sm">Grafik</button>
            <button type="button" @click="mode = 'table'" :class="mode === 'table' ? 'btn-primary' : 'btn-secondary'" class="btn-sm">Jadval</button>
        </div>
    </div>
    <div x-show="mode === 'chart'" class="flex h-40 items-end gap-2">
        @foreach ($values as $i => $v)
            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                <span class="text-[10px] font-semibold text-ink-500">{{ number_format($v, 0, '', ' ') }}</span>
                <div class="w-full rounded-t bg-brand-600" style="height: {{ max(2, (int) round($v * 100 / $max)) }}%"></div>
                <span class="text-[10px] text-ink-400">{{ $labels[$i] }}</span>
            </div>
        @endforeach
    </div>
    <table x-show="mode === 'table'" x-cloak class="table"><tbody>
        @foreach ($values as $i => $v)<tr><td>{{ $labels[$i] }}</td><td class="text-right font-semibold">{{ number_format($v, 0, '', ' ') }} {{ $unit }}</td></tr>@endforeach
    </tbody></table>
</div>
