{{-- PWA: ilovani o'rnatish taklifi (Android/Chrome/Edge) va iOS yo'riqnomasi + service worker ro'yxatdan o'tkazish --}}
<div x-data="pwaInstall()" x-init="init()" x-cloak>
    <div x-show="show" x-transition class="fixed inset-x-3 bottom-20 z-40 mx-auto max-w-md rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl dark:border-ink-700 dark:bg-ink-900 lg:bottom-6 lg:left-auto lg:right-6">
        <div class="flex items-start gap-3">
            <x-logo class="h-10 w-10 shrink-0" />
            <div class="min-w-0 flex-1 text-sm">
                <div class="font-bold text-ink-900 dark:text-white">{{ \App\Support\Brand::name() }} ni o'rnating</div>
                <p class="mt-0.5 text-ink-500" x-show="!ios">Telefon yoki kompyuteringizga ilova sifatida qo'shing — tezroq ochiladi.</p>
                <p class="mt-0.5 text-ink-500" x-show="ios">Safari'da <b>Ulashish</b> tugmasini bosing, so'ng <b>«Bosh ekranga qo'shish»</b> ni tanlang.</p>
                <div class="mt-3 flex gap-2">
                    <button x-show="!ios" @click="install()" class="btn-primary btn-sm">O'rnatish</button>
                    <button @click="dismiss()" class="btn-ghost btn-sm">Keyinroq</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' }).catch(() => {}));
    }
    function pwaInstall() {
        return {
            show: false, ios: false, prompt: null,
            init() {
                const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
                let dismissed = false;
                try { dismissed = localStorage.getItem('pwa_dismissed') === '1'; } catch (e) {}
                if (standalone || dismissed) return;
                this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
                if (this.ios) { setTimeout(() => this.show = true, 4000); }
                window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.prompt = e; this.show = true; });
                window.addEventListener('appinstalled', () => this.show = false);
            },
            async install() {
                if (!this.prompt) return;
                this.prompt.prompt();
                await this.prompt.userChoice;
                this.prompt = null; this.show = false;
            },
            dismiss() { this.show = false; try { localStorage.setItem('pwa_dismissed', '1'); } catch (e) {} },
        };
    }
</script>
