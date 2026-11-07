@extends('landing.layout')
@section('title', \App\Support\Brand::name()." — o'quv markazlari uchun CRM: o'quvchilar, to'lovlar, davomad, SMS")
@section('description', \App\Support\Brand::name()." bilan o'quv markazingizni bitta tizimdan boshqaring: o'quvchilar, guruhlar, davomad, to'lovlar, kassa, ish haqi, SMS va hisobotlar. Ko'p filialli markazlar uchun.")

@section('content')
@php $fmt = fn ($n) => number_format((int) $n, 0, '', ' '); @endphp

{{-- ===================== HERO ===================== --}}
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50/70 via-white to-white">
    <div class="lp-grid-bg absolute inset-0"></div>
    <div class="lp-blob left-[-6rem] top-[-4rem] h-80 w-80 bg-brand-300"></div>
    <div class="lp-blob b2 right-[-5rem] top-24 h-96 w-96 bg-rose-200"></div>
    <div class="lp-blob b3 bottom-[-6rem] left-1/3 h-72 w-72 bg-amber-100"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-4 pb-20 pt-14 sm:px-6 lg:grid-cols-2 lg:pb-28 lg:pt-20">
        <div>
            <span class="lp-reveal inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/80 px-3.5 py-1.5 text-xs font-semibold text-brand-700 shadow-sm backdrop-blur">
                <span class="lp-ping relative inline-block h-2 w-2 rounded-full bg-brand-600 text-brand-600"></span>
                O'zbekiston o'quv markazlari uchun
            </span>
            <h1 class="lp-reveal lp-d1 mt-5 text-4xl font-black leading-[1.1] tracking-tight text-ink-900 sm:text-5xl lg:text-5xl xl:text-[3.4rem]">
                O'quv markazingizni <span id="lp-rotate" class="lp-gradient-text inline-block" data-words='["bitta tizimdan","oson va tez","barcha filiallar bilan","aniq hisob bilan"]'>bitta tizimdan</span> boshqaring
            </h1>
            <p class="lp-reveal lp-d2 mt-6 max-w-xl text-lg leading-relaxed text-ink-600">O'quvchilar, guruhlar, davomad, to'lovlar, kassa, ish haqi, SMS va hisobotlar — hammasi bir joyda. Bir nechta filial bo'lsa ham, har biri alohida, hammasi sizning nazoratingizda.</p>
            <div class="lp-reveal lp-d3 mt-8 flex flex-wrap items-center gap-3">
                <a href="#ariza" class="btn-primary lp-shine px-6 py-3 text-base shadow-xl shadow-brand-600/25">Bepul maslahat olish <x-lp-icon name="arrow-right" class="h-5 w-5" /></a>
                <a href="#demo" class="btn-secondary group px-6 py-3 text-base"><span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white transition group-hover:scale-110"><x-lp-icon name="play" class="h-3 w-3" /></span> Demoni ko'rish</a>
            </div>
            <ul class="lp-reveal lp-d4 mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-ink-600">
                <li class="flex items-center gap-2"><x-lp-icon name="globe" class="h-5 w-5 text-brand-600" /> O'zbek tilida</li>
                <li class="flex items-center gap-2"><x-lp-icon name="lock" class="h-5 w-5 text-brand-600" /> Ma'lumotlar markazlar bo'yicha ajratilgan</li>
                <li class="flex items-center gap-2"><x-lp-icon name="devices" class="h-5 w-5 text-brand-600" /> Telefon va kompyuterda</li>
            </ul>
        </div>

        {{-- Tizim maketi (namuna) --}}
        <div class="lp-reveal lp-zoom lp-d2 relative mx-auto w-full max-w-xl">
            <div class="lp-tilt relative rounded-3xl border border-ink-200/70 bg-white p-4 shadow-2xl shadow-ink-900/10">
                <div class="mb-3 flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full bg-brand-400"></span><span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                    <span class="ml-3 rounded-md bg-ink-100 px-3 py-0.5 text-[10px] text-ink-400">eduziyo · boshqaruv paneli</span>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    @foreach ([['Tushum', '24,5 mln', 'text-emerald-600', 'wallet'], ["O'quvchilar", '312', 'text-brand-600', 'users'], ['Davomad', '91%', 'text-sky-600', 'calendar-check']] as [$l, $v, $cl, $ic])
                        <div class="rounded-2xl bg-ink-50 p-3">
                            <x-lp-icon :name="$ic" class="h-4 w-4 {{ $cl }}" />
                            <div class="mt-2 text-lg font-extrabold text-ink-900">{{ $v }}</div>
                            <div class="text-[11px] text-ink-500">{{ $l }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 rounded-2xl bg-ink-50 p-4">
                    <div class="mb-2 flex items-center justify-between text-[11px] text-ink-500"><span>Oylik tushum</span><span class="font-semibold text-emerald-600">+12%</span></div>
                    <div class="flex h-28 items-end gap-2">
                        @foreach ([38, 52, 44, 66, 58, 78, 70, 92] as $i => $h)
                            <div class="lp-bar flex-1 rounded-t-lg bg-gradient-to-t from-brand-600 to-brand-300" style="height: {{ $h }}%; animation-delay: {{ 0.15 * $i }}s"></div>
                        @endforeach
                    </div>
                </div>
                <div class="mt-3 space-y-2">
                    @foreach ([['Koreys tili A1', "18 o'quvchi", 'Dars boshlandi'], ['IELTS 6.5', "12 o'quvchi", 'Davomad belgilandi'], ['Matematika', "20 o'quvchi", "To'lov kutilmoqda"]] as [$g, $n, $s])
                        <div class="flex items-center gap-3 rounded-xl border border-ink-100 px-3 py-2">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-lp-icon name="graduation" class="h-4 w-4" /></span>
                            <div class="flex-1"><div class="text-xs font-bold text-ink-900">{{ $g }}</div><div class="text-[10px] text-ink-500">{{ $n }}</div></div>
                            <span class="rounded-full bg-ink-100 px-2 py-0.5 text-[10px] font-medium text-ink-600">{{ $s }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-center text-[10px] text-ink-400">Namuna ma'lumotlar bilan tasvir</p>
            </div>
            <div class="lp-float absolute -left-4 -bottom-2 hidden items-center gap-3 rounded-2xl border border-ink-100 bg-white px-4 py-3 shadow-xl sm:flex">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><x-lp-icon name="check" class="h-5 w-5" /></span>
                <div><div class="text-xs font-bold text-ink-900">To'lov qabul qilindi</div><div class="text-[10px] text-ink-500">Kassa avtomatik yangilandi</div></div>
            </div>
            <div class="lp-float slow absolute -right-2 -top-4 hidden items-center gap-3 rounded-2xl border border-ink-100 bg-white px-4 py-3 shadow-xl sm:flex">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-lp-icon name="message" class="h-5 w-5" /></span>
                <div><div class="text-xs font-bold text-ink-900">SMS yuborildi</div><div class="text-[10px] text-ink-500">Qarz eslatmasi</div></div>
            </div>
        </div>
    </div>
</section>

{{-- ===================== MARQUEE ===================== --}}
@php $chips = [['building', "Ko'p filialli boshqaruv"], ['upload', "Excel'dan import"], ['message', 'SMS xabarnomalar'], ['sparkles', 'AI yordamchi'], ['wallet', 'Kassa va moliya'], ['shield-check', 'Ruxsatlar nazorati'], ['bar-chart', 'Hisobotlar'], ['calendar-check', 'Davomad'], ['filter', 'Murojaatlar varonkasi']]; @endphp
<section class="overflow-hidden border-y border-ink-100 bg-ink-50/60 py-5" aria-hidden="true">
    <div class="lp-marquee">
        @for ($k = 0; $k < 2; $k++)
            @foreach ($chips as [$ic, $t])
                <div class="flex items-center gap-2.5 whitespace-nowrap text-sm font-semibold text-ink-600"><x-lp-icon :name="$ic" class="h-5 w-5 text-brand-600" /> {{ $t }}</div>
            @endforeach
        @endfor
    </div>
</section>

{{-- ===================== IMKONIYATLAR ===================== --}}
<section id="imkoniyatlar" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:py-28">
    <div class="mx-auto max-w-2xl text-center">
        <span class="lp-reveal badge-red">Imkoniyatlar</span>
        <h2 class="lp-reveal lp-d1 mt-4 text-3xl font-black tracking-tight text-ink-900 sm:text-4xl">Kundalik ishda kerak bo'lgan hamma narsa</h2>
        <p class="lp-reveal lp-d2 mt-3 text-ink-500">Alohida dasturlar va Excel jadvallarini bitta tizimga yig'ing.</p>
    </div>
    @php
        $features = [
            ['users', "O'quvchilar va guruhlar", "O'quvchi kartasi, guruhga qo'shish, davom ettirish va arxiv. Excel'dan ommaviy import."],
            ['calendar-check', 'Davomad va kalendar', "Dars kalendari, davomatni belgilash, bayram va dam olish kunlari."],
            ['wallet', "To'lovlar va kassa", "To'lov, chegirma, qaytarish va kassa yopish. Pul faqat jurnalga yozilib o'zgaradi."],
            ['banknote', 'Ish haqi', "O'qituvchi va xodimlarga hisoblash va to'lash, to'lanmagan qoldiq nazorati."],
            ['filter', 'Murojaatlar (varonka)', "Saytdan va qo'lda kelgan murojaatlar, eslatmalar, o'quvchiga aylantirish."],
            ['message', 'SMS xabarnomalar', "To'lov, qarz, tug'ilgan kun shablonlari; ommaviy yuborish va to'liq tarix."],
            ['bar-chart', 'Hisobotlar va statistika', "Tushum, foyda, qarzdorlar, davomad, filiallar solishtiruvi — bir manbadan."],
            ['sparkles', 'AI yordamchi', "Statistika bo'yicha tahlil va tavsiyalar. Ism va telefonlar tashqariga yuborilmaydi."],
            ['shield-check', 'Filiallar va ruxsatlar', "Har bir markaz va filial ma'lumoti ajratilgan. Xodimga faqat kerakli ruxsatlar beriladi."],
        ];
    @endphp
    <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($features as $i => [$ic, $t, $d])
            <div class="lp-reveal lp-d{{ ($i % 3) + 1 }} lp-card group rounded-3xl border border-ink-100 bg-white p-7 shadow-sm">
                <span class="lp-ico inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><x-lp-icon :name="$ic" class="h-6 w-6" /></span>
                <h3 class="mt-5 text-lg font-bold text-ink-900">{{ $t }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-500">{{ $d }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ===================== QANDAY BOSHLANADI ===================== --}}
<section class="relative overflow-hidden bg-ink-950 py-20 lg:py-28">
    <div class="lp-blob left-1/4 top-0 h-72 w-72 bg-brand-700/40"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <span class="lp-reveal inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-brand-200">Qanday boshlanadi</span>
            <h2 class="lp-reveal lp-d1 mt-4 text-3xl font-black tracking-tight text-white sm:text-4xl">3 qadamda ishga tushiring</h2>
        </div>
        <div class="lp-steps relative mt-14 grid gap-10 md:grid-cols-3">
            @foreach ([['message', 'Ariza qoldiring', "Shaklni to'ldiring — biz siz bilan bog'lanamiz va ehtiyojingizni aniqlaymiz."], ['play', "Demo va tarif", "Tizimni ko'rsatamiz, markazingiz hajmiga mos tarifni taklif qilamiz."], ['rocket', 'Ishga tushiring', "Markazingiz ochiladi, o'quvchilarni Excel'dan ko'chirib, ishni boshlaysiz."]] as $i => [$ic, $t, $d])
                <div class="lp-reveal lp-d{{ $i + 1 }} relative text-center">
                    <div class="relative mx-auto flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-3xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-xl shadow-brand-600/30">
                        <x-lp-icon :name="$ic" class="h-8 w-8" />
                        <span class="absolute -right-2 -top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-white text-xs font-black text-brand-700">{{ $i + 1 }}</span>
                    </div>
                    <h3 class="mt-6 text-lg font-bold text-white">{{ $t }}</h3>
                    <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-ink-400">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== ROLLAR ===================== --}}
@php
    $roles = [
        ['crown', 'Direktor', "Butun markaz bir ekranda", ["Barcha filiallar bo'yicha tushum, foyda va qarzdorlar", "Filiallarni birga yoki alohida ko'rish", "Xodim qo'shish va ularga ruxsat berish", "Filiallar solishtiruvi va AI tahlil"]],
        ['briefcase', 'Admin va menejer', "Kundalik ish — tez va aniq", ["O'quvchi qabul qilish, guruhga yozish", "To'lov, chegirma va qaytarishni kiritish", "Guruh ochish va davom ettirish", "Murojaatlar varonkasi va kassa"]],
        ['book-open', "O'qituvchi", "Faqat o'ziga kerakli narsa", ["O'z guruhlari va dars kalendari", "Davomatni bir bosishda belgilash", "Guruhdagi o'quvchilar ro'yxati", "Ortiqcha bo'limlar ko'rinmaydi"]],
        ['headphones', 'Operator', "Bir nechta filial bo'ylab", ["Filiallar bo'yicha murojaatlar va o'quvchilarni ko'rish", "Faqat o'qish rejimi — tasodifiy o'zgarish yo'q", "Murojaatlar bilan ishlash", "Har bir filialning o'z ma'lumoti ajratilgan"]],
    ];
@endphp
<section id="rollar" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:py-28" x-data="{ tab: 0 }">
    <div class="mx-auto max-w-2xl text-center">
        <span class="lp-reveal badge-red">Kim uchun</span>
        <h2 class="lp-reveal lp-d1 mt-4 text-3xl font-black tracking-tight text-ink-900 sm:text-4xl">Har bir rol uchun qulay</h2>
    </div>
    <div class="mt-12 grid gap-6 lg:grid-cols-5">
        <div class="lp-reveal lp-left lg:col-span-2">
            <div class="flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible">
                @foreach ($roles as $i => [$ic, $t, $sub])
                    <button type="button" @click="tab = {{ $i }}" :class="tab === {{ $i }} ? 'border-brand-500 bg-brand-50 shadow-lg shadow-brand-600/10' : 'border-ink-100 bg-white hover:border-brand-200'" class="flex min-w-[13rem] items-center gap-4 rounded-2xl border p-4 text-left transition lg:min-w-0">
                        <span :class="tab === {{ $i }} ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600'" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition"><x-lp-icon :name="$ic" class="h-5 w-5" /></span>
                        <span><span class="block font-bold text-ink-900">{{ $t }}</span><span class="block text-xs text-ink-500">{{ $sub }}</span></span>
                    </button>
                @endforeach
            </div>
        </div>
        <div class="lp-reveal lp-right lp-d2 lg:col-span-3">
            @foreach ($roles as $i => [$ic, $t, $sub, $points])
                <div x-show="tab === {{ $i }}" x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" @if ($i) x-cloak @endif class="h-full rounded-3xl border border-ink-100 bg-gradient-to-br from-white to-brand-50/60 p-8 shadow-sm">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/30"><x-lp-icon :name="$ic" class="h-7 w-7" /></span>
                    <h3 class="mt-5 text-2xl font-extrabold text-ink-900">{{ $t }} uchun</h3>
                    <p class="mt-1 text-ink-500">{{ $sub }}</p>
                    <ul class="mt-6 space-y-3.5">
                        @foreach ($points as $p)
                            <li class="flex items-start gap-3 text-ink-700"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><x-lp-icon name="check" class="h-4 w-4" :stroke="2.5" /></span>{{ $p }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== TARIFLAR ===================== --}}
@php $hasYearly = $tariffs->contains(fn ($t) => $t->price_yearly !== null); @endphp
<section id="tariflar" class="relative overflow-hidden bg-gradient-to-b from-white via-brand-50/50 to-white py-20 lg:py-28" x-data="{ yearly: false }">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <span class="lp-reveal badge-red">Tariflar</span>
            <h2 class="lp-reveal lp-d1 mt-4 text-3xl font-black tracking-tight text-ink-900 sm:text-4xl">Markazingizga mos tarifni tanlang</h2>
            <p class="lp-reveal lp-d2 mt-3 text-ink-500">{{ $c['tariff_text'] ?? "Asosiy imkoniyatlar hamma tarifda bir xil. Farq — filial, o'quvchi, SMS va AI limitida." }}</p>
            @if ($hasYearly)
                <div class="lp-reveal lp-d3 mt-6 inline-flex rounded-full bg-ink-100 p-1 text-sm font-semibold">
                    <button type="button" @click="yearly = false" :class="!yearly ? 'bg-white text-ink-900 shadow' : 'text-ink-500'" class="rounded-full px-5 py-2 transition">Oylik</button>
                    <button type="button" @click="yearly = true" :class="yearly ? 'bg-white text-ink-900 shadow' : 'text-ink-500'" class="rounded-full px-5 py-2 transition">Yillik</button>
                </div>
            @endif
        </div>

        <div class="mt-14 grid items-stretch gap-6 lg:grid-cols-3">
            @foreach ($tariffs as $i => $t)
                <div class="lp-reveal lp-d{{ $i + 1 }} lp-card relative flex flex-col rounded-3xl border {{ $t->is_featured ? 'border-brand-500 bg-white shadow-2xl shadow-brand-600/15 ring-4 ring-brand-500/10 lg:-mt-4 lg:mb-[-1rem] lg:py-4' : 'border-ink-100 bg-white shadow-sm' }} p-8">
                    @if ($t->is_featured)<span class="lp-shine absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-1 text-xs font-bold text-white shadow-lg"><x-lp-icon name="star" class="mr-1 inline h-3 w-3" /> Mashhur</span>@endif
                    <h3 class="text-xl font-extrabold text-ink-900">{{ $t->name }}</h3>
                    @if ($t->tagline)<p class="mt-1 text-sm text-ink-500">{{ $t->tagline }}</p>@endif

                    <div class="mt-6 min-h-[5rem]">
                        @if ($t->hasPrice())
                            <div x-show="!yearly" x-transition.opacity>
                                <span class="text-4xl font-black tracking-tight text-ink-900">{{ $fmt($t->price_monthly) }}</span>
                                <span class="text-sm text-ink-500">so'm / oy</span>
                            </div>
                            <div x-show="yearly" x-cloak x-transition.opacity>
                                @if ($t->price_yearly !== null)
                                    <span class="text-4xl font-black tracking-tight text-ink-900">{{ $fmt($t->price_yearly) }}</span>
                                    <span class="text-sm text-ink-500">so'm / yil</span>
                                    @if ($t->yearlySaving())<div class="mt-1 inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">{{ $fmt($t->yearlySaving()) }} so'm tejaysiz</div>@endif
                                @else
                                    <span class="text-sm text-ink-500">Yillik narx so'rov bo'yicha</span>
                                @endif
                            </div>
                        @else
                            <div class="text-3xl font-black text-ink-900">Narxni bilish</div>
                            <div class="mt-1 text-sm text-ink-500">Ariza qoldiring — taklif yuboramiz</div>
                        @endif
                    </div>

                    <dl class="mt-6 grid grid-cols-2 gap-3 rounded-2xl bg-ink-50 p-4 text-sm">
                        <div class="flex items-center gap-2"><x-lp-icon name="building" class="h-4 w-4 text-brand-600" /><div><dt class="text-[11px] text-ink-500">Filiallar</dt><dd class="font-bold text-ink-900">{{ $t->max_branches ? $t->max_branches.' gacha' : 'Cheksiz' }}</dd></div></div>
                        <div class="flex items-center gap-2"><x-lp-icon name="users" class="h-4 w-4 text-brand-600" /><div><dt class="text-[11px] text-ink-500">O'quvchilar</dt><dd class="font-bold text-ink-900">{{ $t->max_students ? $fmt($t->max_students).' gacha' : 'Cheksiz' }}</dd></div></div>
                        <div class="flex items-center gap-2"><x-lp-icon name="message" class="h-4 w-4 text-brand-600" /><div><dt class="text-[11px] text-ink-500">SMS / oy</dt><dd class="font-bold text-ink-900">{{ $t->sms_monthly ? $fmt($t->sms_monthly) : '—' }}</dd></div></div>
                        <div class="flex items-center gap-2"><x-lp-icon name="sparkles" class="h-4 w-4 text-brand-600" /><div><dt class="text-[11px] text-ink-500">AI / oy</dt><dd class="font-bold text-ink-900">{{ $t->ai_monthly ? $fmt($t->ai_monthly) : '—' }}</dd></div></div>
                    </dl>

                    <ul class="mt-6 flex-1 space-y-3 text-sm text-ink-700">
                        @foreach ($t->featureList() as $f)
                            <li class="flex items-start gap-2.5"><span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><x-lp-icon name="check" class="h-3.5 w-3.5" :stroke="3" /></span>{{ $f }}</li>
                        @endforeach
                    </ul>

                    <a href="{{ route('landing') }}?tariff={{ $t->slug }}#ariza" class="{{ $t->is_featured ? 'btn-primary lp-shine' : 'btn-secondary' }} mt-8 w-full justify-center py-3">Shu tarifni tanlash <x-lp-icon name="arrow-right" class="h-4 w-4" /></a>
                </div>
            @endforeach
        </div>
        <p class="lp-reveal mt-8 text-center text-xs text-ink-400">Qo'shimcha SMS paketi alohida sotib olinadi. AI limiti tarifga kiradi va tarif yangilanganda tiklanadi. Narxlar so'mda.</p>
    </div>
</section>

{{-- ===================== DEMO ===================== --}}
<section id="demo" class="relative overflow-hidden bg-ink-950 py-20 lg:py-28">
    <div class="lp-blob right-0 top-0 h-80 w-80 bg-brand-700/50"></div>
    <div class="lp-blob b2 bottom-0 left-0 h-72 w-72 bg-rose-700/30"></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
        <div>
            <span class="lp-reveal inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-brand-200">Demo</span>
            <h2 class="lp-reveal lp-d1 mt-4 text-3xl font-black tracking-tight text-white sm:text-4xl">Sotib olishdan oldin o'zingiz sinab ko'ring</h2>
            <p class="lp-reveal lp-d2 mt-4 leading-relaxed text-ink-300">{{ $c['demo_text'] ?? "Demo markazda tayyor filial, o'quvchilar, guruhlar va to'lovlar bor. Hamma bo'limni ochib ko'rishingiz mumkin." }}</p>
            <ul class="mt-7 space-y-4">
                @foreach ([['users', "O'quvchi qo'shing, guruhga yozing va to'lov qabul qiling"], ['bar-chart', "Davomad, kassa va hisobotlar qanday ishlashini ko'ring"], ['crown', "Direktor, admin va o'qituvchi sifatida kirib solishtiring"]] as $i => [$ic, $t])
                    <li class="lp-reveal lp-d{{ $i + 2 }} flex items-center gap-4 text-ink-200"><span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-brand-300"><x-lp-icon :name="$ic" class="h-5 w-5" /></span>{{ $t }}</li>
                @endforeach
            </ul>
        </div>
        <div class="lp-reveal lp-zoom lp-d2 mx-auto w-full {{ $demoRoles ? 'max-w-xl' : 'max-w-md' }} rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
            @if ($demoRoles)
                <div class="flex items-center gap-3"><span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-lp-icon name="log-in" class="h-5 w-5" /></span><div><h3 class="text-lg font-extrabold text-ink-900">Rolni tanlang va kiring</h3><p class="text-xs text-ink-400">Login va parol shart emas — bir bosishda ochiladi</p></div></div>
                <div class="mt-5 grid gap-2.5 sm:grid-cols-2">
                    @foreach ($demoRoles as $key => [$label, $desc, $icon])
                        <form method="POST" action="{{ route('demo.enter', $key) }}">
                            @csrf
                            <button class="group flex h-full w-full items-start gap-3 rounded-2xl border border-ink-100 bg-ink-50/60 p-3.5 text-left transition hover:-translate-y-0.5 hover:border-brand-300 hover:bg-white hover:shadow-lg">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white"><x-lp-icon :name="$icon" class="h-5 w-5" /></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-extrabold text-ink-900">{{ $label }} sifatida kirish</span>
                                    <span class="mt-0.5 block text-xs leading-snug text-ink-500">{{ $desc }}</span>
                                </span>
                            </button>
                        </form>
                    @endforeach
                </div>
                <p class="mt-4 text-center text-xs text-ink-400">Namunaviy ma'lumotlar. Demo har tun tiklanadi; parol, SMS va AI o'chirilgan.</p>
            @else
                <div class="flex items-center gap-3"><span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-lp-icon name="play" class="h-5 w-5" /></span><h3 class="text-lg font-extrabold text-ink-900">Demo so'rang</h3></div>
                <p class="mt-4 text-sm leading-relaxed text-ink-500">Demo kirish ma'lumotlarini sizga yuboramiz yoki jonli ko'rsatamiz (15–20 daqiqa).</p>
                <a href="{{ route('landing') }}?demo=1#ariza" class="btn-primary lp-shine mt-6 w-full justify-center py-3">Demo so'rash <x-lp-icon name="arrow-right" class="h-4 w-4" /></a>
            @endif
        </div>
    </div>
</section>

{{-- ===================== VIDEOLAR ===================== --}}
@if ($videos->isNotEmpty())
<section id="video" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:py-28">
    <div class="mx-auto max-w-2xl text-center">
        <span class="lp-reveal badge-red">Video</span>
        <h2 class="lp-reveal lp-d1 mt-4 text-3xl font-black tracking-tight text-ink-900 sm:text-4xl">Video qo'llanmalar</h2>
        <p class="lp-reveal lp-d2 mt-3 text-ink-500">Tizim qanday ishlashini videoda ko'ring.</p>
    </div>
    <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($videos as $i => $v)
            <figure class="lp-reveal lp-d{{ ($i % 3) + 1 }} lp-card overflow-hidden rounded-3xl border border-ink-100 bg-white shadow-sm">
                <div class="aspect-video bg-ink-100">
                    <iframe class="h-full w-full" loading="lazy" src="{{ \App\Support\YouTube::embedUrl($v->youtube_id) }}" title="{{ $v->title }}" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                </div>
                <figcaption class="p-5">
                    <div class="flex items-center gap-2 font-bold text-ink-900"><x-lp-icon name="play" class="h-4 w-4 text-brand-600" /> {{ $v->title }}</div>
                    @if ($v->description)<p class="mt-1.5 text-sm text-ink-500">{{ $v->description }}</p>@endif
                </figcaption>
            </figure>
        @endforeach
    </div>
</section>
@endif

{{-- ===================== YANGILIKLAR ===================== --}}
@if ($news->isNotEmpty())
<section id="yangiliklar" class="bg-ink-50/70 py-20 lg:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="lp-reveal badge-red">Yangiliklar</span>
                <h2 class="lp-reveal lp-d1 mt-4 text-3xl font-black tracking-tight text-ink-900 sm:text-4xl">So'nggi yangiliklar</h2>
            </div>
            <a href="{{ route('landing.news') }}" class="lp-reveal lp-d2 inline-flex items-center gap-1.5 text-sm font-bold text-brand-600 hover:gap-3 transition-all">Hammasi <x-lp-icon name="arrow-right" class="h-4 w-4" /></a>
        </div>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach ($news as $i => $n)
                <a href="{{ route('landing.news.show', $n->slug) }}" class="lp-reveal lp-d{{ $i + 1 }} lp-card group overflow-hidden rounded-3xl border border-ink-100 bg-white shadow-sm">
                    @if ($n->imageUrl())
                        <div class="overflow-hidden"><img src="{{ $n->imageUrl() }}" alt="{{ $n->title }}" loading="lazy" class="h-48 w-full object-cover transition duration-700 group-hover:scale-110"></div>
                    @else
                        <div class="flex h-48 items-center justify-center bg-gradient-to-br from-brand-50 to-brand-100 text-brand-500"><x-lp-icon name="file-text" class="h-14 w-14" /></div>
                    @endif
                    <div class="p-6">
                        <div class="flex items-center gap-1.5 text-xs text-ink-400"><x-lp-icon name="clock" class="h-3.5 w-3.5" /> {{ $n->published_at?->format('d.m.Y') }}</div>
                        <h3 class="mt-2 text-lg font-bold text-ink-900 group-hover:text-brand-600">{{ $n->title }}</h3>
                        @if ($n->excerpt)<p class="mt-2 text-sm leading-relaxed text-ink-500">{{ $n->excerpt }}</p>@endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===================== SAVOLLAR ===================== --}}
@php
    $faq = [
        ["Ma'lumotlarim boshqa markazlarga ko'rinadimi?", "Yo'q. Har bir markazning ma'lumoti alohida ajratilgan: o'quvchi, to'lov, kassa va hisobotlarni faqat o'sha markaz xodimlari ko'radi."],
        ["Bir nechta filialim bor, bitta tizimda yuritsa bo'ladimi?", "Ha. Direktor barcha filiallarni birga yoki alohida ko'radi, xodimlar esa faqat o'z filialida ishlaydi."],
        ["Hozirgi o'quvchilar ro'yxatini ko'chirish mumkinmi?", "Ha, Excel fayldan ommaviy import bor: namuna faylni to'ldirasiz, tizim avval tekshiradi, keyin qo'shadi."],
        ["SMS yuborish bormi?", "Ha. To'lov, qarz eslatmasi va boshqa shablonlar hamda ommaviy yuborish; barcha xabarlar tarixi saqlanadi."],
        ["Qanday qurilmalarda ishlaydi?", "Telefon, planshet va kompyuter brauzerida ishlaydi — alohida dastur o'rnatish shart emas."],
        ["Qanday boshlayman?", "Pastdagi arizani to'ldiring. Biz bog'lanib, demo ko'rsatamiz va markazingizni sozlashga yordam beramiz."],
    ];
@endphp
@push('jsonld')
<script type="application/ld+json">{!! \App\Support\Seo::json(\App\Support\Seo::organization($c)) !!}</script>
<script type="application/ld+json">{!! \App\Support\Seo::json(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => \App\Support\Brand::name(), 'url' => url('/'), 'inLanguage' => 'uz']) !!}</script>
<script type="application/ld+json">{!! \App\Support\Seo::json(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => \App\Support\Brand::name(), 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web, Android, iOS', 'inLanguage' => 'uz', 'url' => url('/'), 'description' => "O'quv markazlari uchun CRM: o'quvchilar, guruhlar, davomad, to'lovlar, kassa, ish haqi, SMS va hisobotlar.", 'publisher' => ['@id' => url('/').'#organization']]) !!}</script>
<script type="application/ld+json">{!! \App\Support\Seo::json(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]])->all()]) !!}</script>
@endpush

<section id="savollar" class="mx-auto max-w-3xl px-4 py-20 sm:px-6 lg:py-28" x-data="{ open: 0 }">
    <div class="text-center">
        <span class="lp-reveal badge-red">Savollar</span>
        <h2 class="lp-reveal lp-d1 mt-4 text-3xl font-black tracking-tight text-ink-900 sm:text-4xl">Ko'p so'raladigan savollar</h2>
    </div>
    <div class="mt-10 space-y-3">
        @foreach ($faq as $i => [$q, $a])
            <div class="lp-reveal lp-d{{ min($i + 1, 6) }} overflow-hidden rounded-2xl border bg-white transition" :class="open === {{ $i }} ? 'border-brand-300 shadow-lg shadow-brand-600/10' : 'border-ink-100'">
                <button type="button" @click="open = open === {{ $i }} ? -1 : {{ $i }}" class="flex w-full items-center justify-between gap-4 p-5 text-left font-bold text-ink-900">
                    {{ $q }}
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ink-100 text-ink-600 transition" :class="open === {{ $i }} ? 'rotate-180 bg-brand-600 text-white' : ''"><x-lp-icon name="chevron-down" class="h-4 w-4" /></span>
                </button>
                <div x-show="open === {{ $i }}" x-cloak x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"><p class="px-5 pb-5 text-sm leading-relaxed text-ink-500">{{ $a }}</p></div>
            </div>
        @endforeach
    </div>
</section>

{{-- ===================== ARIZA ===================== --}}
<section id="ariza" class="relative overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-brand-500 py-20 lg:py-28">
    <div class="lp-blob left-0 top-0 h-80 w-80 bg-white/20"></div>
    <div class="lp-blob b2 bottom-0 right-0 h-96 w-96 bg-brand-900/40"></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-5">
        <div class="lp-reveal lp-left text-white lg:col-span-2">
            <h2 class="text-3xl font-black tracking-tight sm:text-4xl">Ariza qoldiring</h2>
            <p class="mt-4 text-brand-100">Biz sizga bog'lanib, demo ko'rsatamiz va markazingizga mos tarif taklif qilamiz.</p>
            <ul class="mt-8 space-y-4">
                @foreach ([['play', "Tizimni jonli ko'rsatamiz"], ['wallet', 'Tarif tanlashda yordam beramiz'], ['upload', "Ma'lumotlarni Excel'dan ko'chirishda yordam beramiz"]] as [$ic, $t])
                    <li class="flex items-center gap-3"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/15"><x-lp-icon :name="$ic" class="h-5 w-5" /></span>{{ $t }}</li>
                @endforeach
            </ul>
        </div>
        <div class="lp-reveal lp-right lp-d2 lg:col-span-3">
            <div class="rounded-3xl bg-white p-6 shadow-2xl sm:p-10">
                @if ($applied)
                    <div class="lp-pop mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"><x-lp-icon name="check" class="mt-0.5 h-5 w-5 shrink-0" :stroke="2.5" /> <div>Arizangiz qabul qilindi. Tez orada siz bilan bog'lanamiz.@if (session('tg_verify_link'))<div class="mt-2"><a href="{{ session('tg_verify_link') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-2 font-bold text-white hover:bg-emerald-700">Telegram'da tasdiqlash</a><div class="mt-1 text-xs text-emerald-700">Botda «Start» va telefon raqamingizni ulashing — ariza tezroq ko'rib chiqiladi.</div></div>@endif</div></div>
                @endif
                <form method="POST" action="{{ route('landing.apply') }}" class="grid gap-5 sm:grid-cols-2">
                    @csrf
                    <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                    <input type="hidden" name="utm_source" value="{{ request('utm_source') }}">
                    <input type="hidden" name="utm_medium" value="{{ request('utm_medium') }}">
                    <input type="hidden" name="utm_campaign" value="{{ request('utm_campaign') }}">
                    <x-input name="name" label="Ism familiyangiz" required />
                    <x-input name="center_name" label="O'quv markaz nomi" required />
                    <x-input name="phone" phone label="Telefon" required />
                    <x-input name="city" label="Shahar" />
                    <x-input name="branches_count" type="number" min="1" label="Filiallar soni" />
                    <x-input name="students_count" type="number" min="1" label="O'quvchilar soni (taxminan)" />
                    <div>
                        <label for="tariff_slug" class="label">Qiziqqan tarifingiz</label>
                        <select id="tariff_slug" name="tariff_slug" class="input">
                            <option value="">Hali tanlamadim</option>
                            @foreach ($tariffs as $t)<option value="{{ $t->slug }}" @selected(old('tariff_slug', request('tariff')) === $t->slug)>{{ $t->name }}</option>@endforeach
                        </select>
                    </div>
                    <label class="flex items-end gap-2.5 pb-2.5 text-sm text-ink-700"><input type="checkbox" name="wants_demo" value="1" class="rounded border-ink-300 text-brand-600" @checked(old('wants_demo', request('demo')))> Demo ko'rsatishingizni xohlayman</label>
                    <div class="sm:col-span-2">
                        <label for="message" class="label">Izoh</label>
                        <textarea id="message" name="message" rows="3" maxlength="1000" class="input">{{ old('message') }}</textarea>
                        @error('message')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    @if (app(\App\Services\LegalService::class)->consentRequired())
                        <div class="sm:col-span-2"><label class="flex items-start gap-2.5 text-sm text-ink-700"><input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-ink-300 text-brand-600" required @checked(old('consent'))> <span>Shaxsiy ma'lumotlarimni qayta ishlashga <a href="{{ route('legal.privacy') }}" target="_blank" class="font-semibold text-brand-600 underline">maxfiylik siyosatiga</a> muvofiq roziman.</span></label>@error('consent')<p class="error-text">{{ $message }}</p>@enderror</div>
                    @endif
                    <div class="sm:col-span-2"><button class="btn-primary lp-shine w-full justify-center py-3.5 text-base sm:w-auto sm:px-10">Arizani yuborish <x-lp-icon name="send" class="h-5 w-5" /></button></div>
                </form>
            </div>
        </div>
    </div>
</section>
@if ($errors->any())<script>location.hash = '#ariza';</script>@endif
@endsection
