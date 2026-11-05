@php $t = $a['totals']; $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    @foreach ([["Faol o'quvchilar", $t['active_students']], ['Qarzdorlar', $t['debtors']], ['Filiallar', $t['branches']], ['Davom etayotgan guruhlar', $t['groups']], ["Markaz balansi", $fmt($t['balance']).' so\'m']] as [$l, $v])
        <div class="card card-body"><div class="text-xs text-ink-500">{{ $l }}</div><div class="mt-1 text-xl font-extrabold text-ink-900 dark:text-white">{{ $v }}</div></div>
    @endforeach
</div>
<div class="grid gap-6 lg:grid-cols-2">
    @include('partials.analytics-chart', ['title' => "Yangi o'quvchilar", 'labels' => $a['labels'], 'values' => $a['new_students']])
    @include('partials.analytics-chart', ['title' => "Ketgan (arxivlangan) o'quvchilar", 'labels' => $a['labels'], 'values' => $a['churn']])
    @include('partials.analytics-chart', ['title' => "O'quvchilardan tushum (so'm)", 'labels' => $a['labels'], 'values' => $a['revenue']])
    @include('partials.analytics-chart', ['title' => "Murojaatlar (lid)", 'labels' => $a['labels'], 'values' => $a['leads']])
    @include('partials.analytics-chart', ['title' => "Platformaga to'ldirilgan balans (so'm)", 'labels' => $a['labels'], 'values' => $a['topups']])
    @include('partials.analytics-chart', ['title' => 'SMS sarfi', 'labels' => $a['labels'], 'values' => $a['sms']])
    @include('partials.analytics-chart', ['title' => 'AI so\'rovlar', 'labels' => $a['labels'], 'values' => $a['ai']])
</div>
