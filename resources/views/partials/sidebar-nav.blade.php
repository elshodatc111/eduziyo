{{--
    v13: yon menyu bo'limlarga ajratilgan va yig'iladi. Bo'lim ichida foydalanuvchiga ko'rinadigan band
    bo'lmasa, bo'lim sarlavhasi ham chiqmaydi. Faol sahifaning bo'limi o'zi ochiq turadi; qolganlarining
    holati brauzerda (localStorage) saqlanadi.
--}}
@php
    $isTeacher = $me->role === \App\Enums\Role::Teacher;

    $groups = [
        [
            'key' => 'learn', 'title' => "O'quv jarayoni", 'icon' => 'cap',
            'items' => [
                ['label' => "O'quvchilar", 'href' => route('students.index'), 'icon' => 'cap', 'active' => request()->routeIs('students.*'), 'show' => $me->can('students.view')],
                ['label' => $isTeacher ? 'Guruhlarim' : 'Guruhlar', 'href' => route('groups.index'), 'icon' => 'users', 'active' => request()->routeIs('groups.*'), 'show' => $me->can('viewAny', \App\Models\Group::class)],
                ['label' => 'Bugungi davomad', 'href' => route('attendance.today'), 'icon' => 'calendar', 'active' => request()->routeIs('attendance.today'), 'show' => $me->can('attendance.view')],
                ['label' => 'Mening jadvalim', 'href' => route('teacher.schedule'), 'icon' => 'calendar', 'active' => request()->routeIs('teacher.schedule'), 'show' => $isTeacher],
                ['label' => 'Mening ish haqim', 'href' => route('teacher.payroll'), 'icon' => 'briefcase', 'active' => request()->routeIs('teacher.payroll'), 'show' => $isTeacher],
                ['label' => 'Davomad statistikasi', 'href' => route('attendance.stats'), 'icon' => 'chart', 'active' => request()->routeIs('attendance.stats'), 'show' => $me->can('attendance.stats')],
                ['label' => 'Kurslar', 'href' => route('catalog.index', 'courses'), 'icon' => 'book', 'active' => request()->is('settings/courses*') || request()->routeIs('courses.*'), 'show' => $me->canany(['courses.view', 'courses.manage'])],
            ],
        ],
        [
            'key' => 'leadsms', 'title' => 'Murojaat va aloqa', 'icon' => 'funnel',
            'items' => [
                ['label' => 'Varonka', 'href' => route('leads.index'), 'icon' => 'funnel', 'active' => request()->routeIs('leads.*'), 'show' => $me->can('leads.view')],
                ['label' => 'SMS', 'href' => route('sms.index'), 'icon' => 'message', 'active' => request()->routeIs('sms.*'), 'show' => $me->canany(['sms.view', 'sms.send', 'sms.manage'])],
            ],
        ],
        [
            'key' => 'money', 'title' => 'Moliya', 'icon' => 'wallet',
            'items' => [
                ['label' => "To'lovlar", 'href' => route('payments.index'), 'icon' => 'banknote', 'active' => request()->routeIs('payments.*'), 'show' => $me->can('payments.view')],
                ['label' => 'Kassa', 'href' => route('cashbox.index'), 'icon' => 'wallet', 'active' => request()->routeIs('cashbox.*'), 'show' => $me->can('cashbox.view')],
                ['label' => 'Moliya', 'href' => route('finance.index'), 'icon' => 'chart', 'active' => request()->routeIs('finance.*'), 'show' => $me->can('finance.view')],
                ['label' => 'Ish haqi', 'href' => route('payroll.index'), 'icon' => 'briefcase', 'active' => request()->routeIs('payroll.*'), 'show' => $me->can('teachers.view') || $me->can('staff.view')],
            ],
        ],
        [
            'key' => 'analytics', 'title' => 'Tahlil', 'icon' => 'chart',
            'items' => [
                ['label' => 'Statistika', 'href' => route('statistics.index'), 'icon' => 'chart', 'active' => request()->routeIs('statistics.*'), 'show' => $me->can('statistics.view')],
                ['label' => 'Hisobotlar', 'href' => route('reports.index'), 'icon' => 'log', 'active' => request()->routeIs('reports.*'), 'show' => $me->can('reports.view')],
            ],
        ],
        [
            'key' => 'manage', 'title' => 'Boshqaruv', 'icon' => 'shield',
            'items' => [
                $me->can('staff.view')
                    ? ['label' => 'Hodimlar', 'href' => route('staff.index'), 'icon' => 'shield', 'active' => request()->routeIs('staff.index', 'staff.create', 'staff.edit'), 'show' => true]
                    : ['label' => "O'qituvchilar", 'href' => route('staff.index', ['role' => 'teacher']), 'icon' => 'shield', 'active' => request()->routeIs('staff.index', 'staff.create', 'staff.edit'), 'show' => $me->can('teachers.view')],
                ['label' => 'Sozlamalar', 'href' => route('catalog.index', 'rooms'), 'icon' => 'cog', 'active' => request()->is('settings/*') && ! request()->is('settings/courses*'), 'show' => $me->can('settings.branch')],
                ['label' => 'Harakatlar jurnali', 'href' => route('audit.index'), 'icon' => 'log', 'active' => request()->routeIs('audit.*'), 'show' => $me->can('audit.view') && ! $me->isSuperAdmin()],
            ],
        ],
        // Direktor (markaz egasi): o'z markazi
        [
            'key' => 'center', 'title' => 'Markaz', 'icon' => 'building',
            'items' => [
                ['label' => 'Obuna, balans, SMS', 'href' => route('center.index'), 'icon' => 'wallet', 'active' => request()->routeIs('center.index', 'center.tariffs', 'center.topup', 'center.sms', 'center.directors*', 'center.support*'), 'show' => $me->role === \App\Enums\Role::Owner],
                ['label' => 'Markaz tahlili', 'href' => route('center.analytics'), 'icon' => 'chart', 'active' => request()->routeIs('center.analytics'), 'show' => $me->role === \App\Enums\Role::Owner],
                ['label' => 'Filiallar', 'href' => route('branches.index'), 'icon' => 'building', 'active' => request()->routeIs('branches.*'), 'show' => $me->role === \App\Enums\Role::Owner],
            ],
        ],
        // sAdmin: platforma
        [
            'key' => 'platform', 'title' => 'Platforma', 'icon' => 'building',
            'items' => [
                ['label' => 'Markazlar', 'href' => route('platform.centers.index'), 'icon' => 'building', 'active' => request()->routeIs('platform.centers.*', 'platform.directors*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Filiallar', 'href' => route('branches.index'), 'icon' => 'building', 'active' => request()->routeIs('branches.*'), 'show' => $me->isSuperAdmin()],
                ['label' => "Balans to'ldirish", 'href' => route('platform.topups'), 'icon' => 'wallet', 'active' => request()->routeIs('platform.topups*', 'platform.cards*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Murojaatlar'.(($me->isSuperAdmin() && ($n = app(\App\Services\SupportService::class)->waitingCount())) ? " ($n)" : ''), 'href' => route('platform.support'), 'icon' => 'lifebuoy', 'active' => request()->routeIs('platform.support*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Platforma tahlili', 'href' => route('platform.analytics'), 'icon' => 'chart', 'active' => request()->routeIs('platform.analytics'), 'show' => $me->isSuperAdmin()],
            ],
        ],
        [
            'key' => 'salesadmin', 'title' => 'Sotuv', 'icon' => 'chart',
            'items' => [
                ['label' => 'Arizalar', 'href' => route('sales.applications'), 'icon' => 'users', 'active' => request()->routeIs('sales.applications*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Tariflar', 'href' => route('sales.tariffs'), 'icon' => 'wallet', 'active' => request()->routeIs('sales.tariffs*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Demo kirishlar', 'href' => route('sales.demo'), 'icon' => 'users', 'active' => request()->routeIs('sales.demo*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Yangiliklar', 'href' => route('sales.news.index'), 'icon' => 'bell', 'active' => request()->routeIs('sales.news.*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Videolar (sotuv sahifasi)', 'href' => route('sales.videos'), 'icon' => 'phone', 'active' => request()->routeIs('sales.videos*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Sotuv sozlamalari', 'href' => route('sales.settings'), 'icon' => 'cog', 'active' => request()->routeIs('sales.settings*'), 'show' => $me->isSuperAdmin()],
            ],
        ],
        [
            'key' => 'comms', 'title' => 'Aloqa va AI', 'icon' => 'plug',
            'items' => [
                ['label' => 'Integratsiyalar', 'href' => route('platform.integrations'), 'icon' => 'plug', 'active' => request()->routeIs('platform.integrations*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'SMS shablonlari', 'href' => route('platform.sms.templates'), 'icon' => 'message', 'active' => request()->routeIs('platform.sms.templates*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'SMS jurnali', 'href' => route('platform.sms'), 'icon' => 'message', 'active' => request()->routeIs('platform.sms') || request()->routeIs('platform.sms.settings'), 'show' => $me->isSuperAdmin()],
            ],
        ],
        [
            'key' => 'system', 'title' => 'Tizim', 'icon' => 'cog',
            'items' => [
                ['label' => 'Tizim holati', 'href' => route('system-status.index'), 'icon' => 'cog', 'active' => request()->routeIs('system-status.*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Hujjatlar va rozilik', 'href' => route('platform.legal'), 'icon' => 'document', 'active' => request()->routeIs('platform.legal*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Brending (nom, logotip)', 'href' => route('platform.brand'), 'icon' => 'palette', 'active' => request()->routeIs('platform.brand*'), 'show' => $me->isSuperAdmin()],
                ['label' => "Video qo'llanmalar", 'href' => route('platform.guides'), 'icon' => 'video', 'active' => request()->routeIs('platform.guides*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Super adminlar', 'href' => route('superadmins.index'), 'icon' => 'shield', 'active' => request()->routeIs('superadmins.*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Bildirishnomalar', 'href' => route('notifications.index'), 'icon' => 'bell', 'active' => request()->routeIs('notifications.*'), 'show' => $me->isSuperAdmin()],
                ['label' => 'Harakatlar jurnali', 'href' => route('audit.index'), 'icon' => 'log', 'active' => request()->routeIs('audit.*'), 'show' => $me->isSuperAdmin() && $me->can('audit.view')],
            ],
        ],
    ];
@endphp

<x-nav-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">Bosh sahifa</x-nav-link>
<x-nav-link :href="route('help.index')" icon="sparkles" :active="request()->routeIs('help.*', 'ai.*')">Yordam</x-nav-link>
<x-nav-link :href="route('guides.index')" icon="video" :active="request()->routeIs('guides.*')">Video qo'llanma</x-nav-link>

@foreach ($groups as $group)
    @php
        $visible = array_values(array_filter($group['items'], fn ($i) => $i['show']));
        $hasActive = (bool) collect($visible)->contains(fn ($i) => $i['active']);
    @endphp
    @if ($visible !== [])
        <div x-data="navGroup('{{ $group['key'] }}', {{ $hasActive ? 'true' : 'false' }})" class="pt-2">
            <button type="button" @click="toggle()" :aria-expanded="open.toString()"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold uppercase tracking-wider text-ink-400 transition hover:bg-ink-100 hover:text-ink-700 dark:hover:bg-ink-800 dark:hover:text-ink-200">
                <x-icon :name="$group['icon']" class="h-4 w-4 shrink-0" />
                <span class="flex-1 text-left">{{ $group['title'] }}</span>
                @if ($hasActive)<span class="h-1.5 w-1.5 rounded-full bg-brand-500" x-show="!open"></span>@endif
                <x-icon name="chevron" class="h-4 w-4 shrink-0 transition-transform" ::class="open ? 'rotate-180' : ''" />
            </button>
            <div x-show="open" x-cloak class="mt-1 space-y-1 border-l border-ink-100 pl-2 ml-4 dark:border-ink-800">
                @foreach ($visible as $item)
                    <x-nav-link :href="$item['href']" :icon="$item['icon']" :active="$item['active']" :target="! empty($item['blank']) ? '_blank' : null">{{ $item['label'] }}</x-nav-link>
                @endforeach
            </div>
        </div>
    @endif
@endforeach
