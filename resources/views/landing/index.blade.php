@extends('landing.layout')

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50 to-white">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
        <div class="max-w-3xl">
            <span class="badge-red">O'quv markazlari uchun</span>
            <h1 class="mt-4 text-4xl font-extrabold leading-tight tracking-tight text-ink-900 sm:text-5xl">O'quv markazingizni bitta tizimdan boshqaring</h1>
            <p class="mt-5 text-lg text-ink-600">O'quvchilar, guruhlar, davomad, to'lovlar, kassa, ish haqi, SMS va hisobotlar — barchasi bir joyda. Bir nechta filial bo'lsa ham, har biri alohida, hammasi sizning nazoratingizda.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="#ariza" class="btn-primary">Bepul maslahat olish</a>
                <a href="#imkoniyatlar" class="btn-secondary">Imkoniyatlarni ko'rish</a>
            </div>
            <p class="mt-4 text-sm text-ink-500">Ariza qoldiring — demo ko'rsatamiz va markazingizga mos tarifni taklif qilamiz.</p>
        </div>
    </div>
</section>

{{-- Imkoniyatlar --}}
<section id="imkoniyatlar" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <h2 class="text-3xl font-extrabold tracking-tight text-ink-900">Nimalarni qila olasiz</h2>
    <p class="mt-2 max-w-2xl text-ink-500">Kundalik ishda kerak bo'ladigan hamma narsa tayyor.</p>
    @php
        $features = [
            ["O'quvchilar va guruhlar", "O'quvchi kartasi, guruhga qo'shish, davom ettirish, arxiv. Excel'dan ommaviy import."],
            ['Davomad', "Dars kalendari, davomatni belgilash, bayramlar va dam olish kunlari."],
            ["To'lovlar va kassa", "To'lov qabul qilish, chegirmalar, qaytarish, kassa yopish va moliya hisoboti. Pul faqat jurnalga yozilib o'zgaradi."],
            ['Ish haqi', "O'qituvchi va xodimlarga hisoblash va to'lash, to'lanmagan qoldiq nazorati."],
            ["Murojaatlar (varonka)", "Saytdan va qo'lda kelgan murojaatlar, eslatmalar, o'quvchiga aylantirish."],
            ['SMS xabarnomalar', "To'lov, qarz, tug'ilgan kun va boshqa shablonlar; ommaviy yuborish va to'liq tarix."],
            ['Hisobotlar va statistika', "Tushum, foyda, qarzdorlar, davomad, filiallar solishtiruvi — bir manbadan hisoblanadi."],
            ['AI yordamchi', "Statistika bo'yicha tahlil va tavsiyalar. Ism va telefonlar tashqariga yuborilmaydi."],
            ['Filiallar va ruxsatlar', "Har bir filial va markaz ma'lumotlari ajratilgan. Xodimga faqat kerakli ruxsatlar beriladi."],
        ];
    @endphp
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($features as [$t, $d])
            <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
                <h3 class="font-bold text-ink-900">{{ $t }}</h3>
                <p class="mt-1.5 text-sm text-ink-500">{{ $d }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Rollar --}}
<section id="rollar" class="bg-ink-50">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-3xl font-extrabold tracking-tight text-ink-900">Har bir rol uchun qulay</h2>
        @php
            $roles = [
                ['Direktor', "Barcha filiallar bo'yicha tushum, foyda va qarzdorlarni bir ekranda ko'radi; xodimlarga ruxsat beradi."],
                ['Admin va menejer', "O'quvchi qabul qiladi, to'lovni kiritadi, guruh ochadi, murojaatlar bilan ishlaydi."],
                ["O'qituvchi", "O'z guruhlari, dars jadvali va davomadni ko'radi va belgilaydi."],
                ['Operator', "Bir nechta filial bo'ylab murojaat va o'quvchilarni ko'rib chiqadi."],
            ];
        @endphp
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($roles as [$t, $d])
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink-100">
                    <h3 class="font-bold text-brand-700">{{ $t }}</h3>
                    <p class="mt-1.5 text-sm text-ink-500">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Tariflar --}}
@php
    $hasYearly = $tariffs->contains(fn ($t) => $t->price_yearly !== null);
    $fmt = fn ($n) => number_format((int) $n, 0, '', ' ');
@endphp
<section id="tariflar" class="mx-auto max-w-6xl px-4 py-16 sm:px-6" x-data="{ yearly: false }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold tracking-tight text-ink-900">Tariflar</h2>
            <p class="mt-2 max-w-2xl text-ink-500">{{ $c['tariff_text'] ?? "Markazingiz hajmiga mos tarifni tanlang. Barcha tariflarda asosiy imkoniyatlar bir xil, farq — filial, o'quvchi, SMS va AI limitida." }}</p>
        </div>
        @if ($hasYearly)
            <div class="inline-flex rounded-xl bg-ink-100 p-1 text-sm font-semibold">
                <button type="button" @click="yearly = false" :class="!yearly ? 'bg-white shadow text-ink-900' : 'text-ink-500'" class="rounded-lg px-4 py-1.5">Oylik</button>
                <button type="button" @click="yearly = true" :class="yearly ? 'bg-white shadow text-ink-900' : 'text-ink-500'" class="rounded-lg px-4 py-1.5">Yillik</button>
            </div>
        @endif
    </div>

    <div class="mt-8 grid gap-5 lg:grid-cols-3">
        @foreach ($tariffs as $t)
            <div class="relative flex flex-col rounded-3xl border {{ $t->is_featured ? 'border-brand-500 shadow-xl ring-2 ring-brand-500/20' : 'border-ink-100 shadow-sm' }} bg-white p-7">
                @if ($t->is_featured)<span class="absolute -top-3 left-7 rounded-full bg-brand-600 px-3 py-1 text-xs font-bold text-white">Mashhur</span>@endif
                <h3 class="text-xl font-extrabold text-ink-900">{{ $t->name }}</h3>
                @if ($t->tagline)<p class="mt-1 text-sm text-ink-500">{{ $t->tagline }}</p>@endif

                <div class="mt-5 min-h-[4.5rem]">
                    @if ($t->hasPrice())
                        <div x-show="!yearly">
                            <span class="text-3xl font-extrabold text-ink-900">{{ $fmt($t->price_monthly) }}</span>
                            <span class="text-sm text-ink-500">so'm / oy</span>
                        </div>
                        <div x-show="yearly" x-cloak>
                            @if ($t->price_yearly !== null)
                                <span class="text-3xl font-extrabold text-ink-900">{{ $fmt($t->price_yearly) }}</span>
                                <span class="text-sm text-ink-500">so'm / yil</span>
                                @if ($t->yearlySaving())<div class="mt-1 text-xs font-semibold text-emerald-600">{{ $fmt($t->yearlySaving()) }} so'm tejaysiz</div>@endif
                            @else
                                <span class="text-sm text-ink-500">Yillik narx so'rov bo'yicha</span>
                            @endif
                        </div>
                    @else
                        <div class="text-2xl font-extrabold text-ink-900">Narxni bilish</div>
                        <div class="text-sm text-ink-500">Ariza qoldiring — taklif yuboramiz</div>
                    @endif
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-3 rounded-2xl bg-ink-50 p-4 text-sm">
                    <div><dt class="text-xs text-ink-500">Filiallar</dt><dd class="font-bold text-ink-900">{{ $t->max_branches ? $t->max_branches.' tagacha' : 'Cheksiz' }}</dd></div>
                    <div><dt class="text-xs text-ink-500">O'quvchilar</dt><dd class="font-bold text-ink-900">{{ $t->max_students ? $fmt($t->max_students).' tagacha' : 'Cheksiz' }}</dd></div>
                    <div><dt class="text-xs text-ink-500">SMS (oyiga)</dt><dd class="font-bold text-ink-900">{{ $t->sms_monthly ? $fmt($t->sms_monthly) : '—' }}</dd></div>
                    <div><dt class="text-xs text-ink-500">AI so'rov (oyiga)</dt><dd class="font-bold text-ink-900">{{ $t->ai_monthly ? $fmt($t->ai_monthly) : '—' }}</dd></div>
                </dl>

                <ul class="mt-5 flex-1 space-y-2.5 text-sm text-ink-700">
                    @foreach ($t->featureList() as $f)
                        <li class="flex gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m5 12 5 5 9-10"/></svg><span>{{ $f }}</span></li>
                    @endforeach
                </ul>

                <a href="{{ url('/') }}?tariff={{ $t->slug }}#ariza" class="{{ $t->is_featured ? 'btn-primary' : 'btn-secondary' }} mt-6 w-full justify-center">Shu tarifni tanlash</a>
            </div>
        @endforeach
    </div>
    <p class="mt-5 text-xs text-ink-400">Qo'shimcha SMS paketi alohida sotib olinadi. AI limiti tarifga kiradi va faqat tarif yangilanganda tiklanadi. Narxlar so'mda.</p>
</section>

{{-- Demo --}}
<section id="demo" class="bg-ink-900">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center">
        <div>
            <span class="badge-red">Demo</span>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-white">Sotib olishdan oldin o'zingiz sinab ko'ring</h2>
            <p class="mt-3 text-ink-300">{{ $c['demo_text'] ?? "Demo markazda tayyor filial, o'quvchilar, guruhlar va to'lovlar bor. Hamma bo'limni ochib ko'rishingiz mumkin. Demo ma'lumotlari har kuni yangilanadi, o'zgartirishlaringiz saqlanmaydi." }}</p>
            <ul class="mt-5 space-y-2 text-sm text-ink-200">
                <li>• O'quvchi qo'shish, guruhga yozish va to'lov qabul qilishni sinang</li>
                <li>• Davomad, kassa va hisobotlar qanday ishlashini ko'ring</li>
                <li>• Direktor, admin va o'qituvchi sifatida kirib solishtiring</li>
            </ul>
        </div>
        <div class="rounded-3xl bg-white p-7 shadow-xl">
            @if (($c['demo_login'] ?? null) && ($c['demo_password'] ?? null))
                <h3 class="text-lg font-bold text-ink-900">Demo hisobi</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between rounded-xl bg-ink-50 px-4 py-3"><dt class="text-ink-500">Login</dt><dd class="font-mono font-bold text-ink-900">{{ $c['demo_login'] }}</dd></div>
                    <div class="flex justify-between rounded-xl bg-ink-50 px-4 py-3"><dt class="text-ink-500">Parol</dt><dd class="font-mono font-bold text-ink-900">{{ $c['demo_password'] }}</dd></div>
                </dl>
                <a href="{{ route('login') }}" class="btn-primary mt-5 w-full justify-center">Demoga kirish</a>
                <p class="mt-3 text-xs text-ink-400">Bu ochiq demo hisob: haqiqiy ma'lumot kiritmang.</p>
            @else
                <h3 class="text-lg font-bold text-ink-900">Demo so'rang</h3>
                <p class="mt-2 text-sm text-ink-500">Demo kirish ma'lumotlarini sizga yuboramiz yoki jonli ko'rsatamiz (15–20 daqiqa).</p>
                <a href="{{ url('/') }}?demo=1#ariza" class="btn-primary mt-5 w-full justify-center">Demo so'rash</a>
            @endif
        </div>
    </div>
</section>

{{-- Videolar --}}
@if ($videos->isNotEmpty())
<section id="video" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <h2 class="text-3xl font-extrabold tracking-tight text-ink-900">Video qo'llanmalar</h2>
    <p class="mt-2 text-ink-500">Tizim qanday ishlashini videoda ko'ring.</p>
    <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($videos as $v)
            <figure class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm">
                <div class="aspect-video bg-ink-100">
                    <iframe class="h-full w-full" loading="lazy" src="{{ \App\Support\YouTube::embedUrl($v->youtube_id) }}" title="{{ $v->title }}" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                </div>
                <figcaption class="p-4">
                    <div class="font-bold text-ink-900">{{ $v->title }}</div>
                    @if ($v->description)<p class="mt-1 text-sm text-ink-500">{{ $v->description }}</p>@endif
                </figcaption>
            </figure>
        @endforeach
    </div>
</section>
@endif

{{-- Yangiliklar --}}
@if ($news->isNotEmpty())
<section id="yangiliklar" class="bg-ink-50">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="flex items-end justify-between">
            <h2 class="text-3xl font-extrabold tracking-tight text-ink-900">Yangiliklar</h2>
            <a href="{{ route('landing.news') }}" class="text-sm font-semibold text-brand-600 hover:underline">Hammasi</a>
        </div>
        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach ($news as $n)
                <a href="{{ route('landing.news.show', $n->slug) }}" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-ink-100 transition hover:ring-brand-300">
                    @if ($n->imageUrl())<img src="{{ $n->imageUrl() }}" alt="{{ $n->title }}" loading="lazy" class="h-44 w-full object-cover">@endif
                    <div class="p-5"><div class="text-xs text-ink-400">{{ $n->published_at?->format('d.m.Y') }}</div>
                    <h3 class="mt-1 font-bold text-ink-900">{{ $n->title }}</h3>
                    @if ($n->excerpt)<p class="mt-1.5 text-sm text-ink-500">{{ $n->excerpt }}</p>@endif</div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Savollar --}}
<section id="savollar" class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
    <h2 class="text-3xl font-extrabold tracking-tight text-ink-900">Ko'p so'raladigan savollar</h2>
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
    <div class="mt-6 divide-y divide-ink-100 rounded-2xl border border-ink-100">
        @foreach ($faq as [$q, $a])
            <details class="group p-5">
                <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-ink-900">{{ $q }}<span class="ml-4 text-brand-600 group-open:rotate-45 transition">+</span></summary>
                <p class="mt-2 text-sm text-ink-500">{{ $a }}</p>
            </details>
        @endforeach
    </div>
</section>

{{-- Ariza --}}
<section id="ariza" class="bg-brand-600">
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <div class="rounded-3xl bg-white p-6 shadow-xl sm:p-10">
            <h2 class="text-2xl font-extrabold tracking-tight text-ink-900">Ariza qoldiring</h2>
            <p class="mt-1 text-sm text-ink-500">Biz sizga bog'lanib, demo ko'rsatamiz va tarif taklif qilamiz.</p>

            @if ($applied)
                <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">Arizangiz qabul qilindi. Tez orada siz bilan bog'lanamiz.</div>
            @endif

            <form method="POST" action="{{ route('landing.apply') }}" class="mt-6 grid gap-5 sm:grid-cols-2">
                @csrf
                {{-- Botlar uchun tuzoq: odamga ko'rinmaydi --}}
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
                <label class="flex items-end gap-2 pb-2.5 text-sm text-ink-700"><input type="checkbox" name="wants_demo" value="1" @checked(old('wants_demo', request('demo')))> Demo ko'rsatishingizni xohlayman</label>
                <div class="sm:col-span-2">
                    <label for="message" class="label">Izoh</label>
                    <textarea id="message" name="message" rows="3" maxlength="1000" class="input">{{ old('message') }}</textarea>
                    @error('message')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2"><button class="btn-primary w-full sm:w-auto">Arizani yuborish</button></div>
            </form>
        </div>
    </div>
</section>
@endsection
