{{-- Chiqish tugmasi: avval tasdiqlash oynasi chiqadi; tasdiqlansa sotuv sahifasiga (/) o'tadi --}}
<div x-data="{ open: false }" class="inline-block">
    <button type="button" class="btn-ghost p-2" title="Chiqish" aria-label="Chiqish" @click="open = true"><x-icon name="logout" /></button>
    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity @keydown.escape.window="open = false" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="logout-title">
            <div @click.outside="open = false" class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-ink-900">
                <h2 id="logout-title" class="text-lg font-bold text-ink-900 dark:text-white">Tizimdan chiqasizmi?</h2>
                <p class="mt-1 text-sm text-ink-500">Chiqqandan so'ng bosh sahifaga o'tasiz.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="open = false">Bekor qilish</button>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-primary">Ha, chiqish</button></form>
                </div>
            </div>
        </div>
    </template>
</div>
