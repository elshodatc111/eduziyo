@if (app(\App\Services\DemoService::class)->isDemoUser(auth()->user()))
    <div class="sticky top-0 z-40 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-ink-900 px-4 py-2 text-center text-xs font-medium text-white">
        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span> Demo rejim · ma'lumotlar har tun tiklanadi · parol, SMS va AI o'chirilgan</span>
        <form method="POST" action="{{ route('demo.leave') }}" class="inline">@csrf
            <button class="rounded-full bg-brand-600 px-3 py-1 font-bold text-white hover:bg-brand-500">Ariza qoldirish</button>
        </form>
        <form method="POST" action="{{ route('demo.leave') }}" class="inline">@csrf
            <button class="text-ink-300 underline hover:text-white">Demo'dan chiqish</button>
        </form>
    </div>
@endif
