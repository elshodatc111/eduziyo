{{-- Rasm (skrinshot) biriktirish: fayl tanlash yoki matn maydonida Ctrl+V bilan qo'yish. Eng ko'pi 3 ta, har biri 5 MB gacha. --}}
<div x-data="attachPicker()" x-init="bind()" class="space-y-2">
    <input type="file" name="files[]" multiple accept="image/png,image/jpeg,image/webp" class="hidden" x-ref="input" @change="sync()">
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn-secondary btn-sm" @click="$refs.input.click()"><x-icon name="image" class="h-4 w-4" /> Rasm biriktirish</button>
        <span class="text-xs text-ink-500">Skrinshotni shu yerga Ctrl+V bilan ham qo'yishingiz mumkin (3 tagacha, 5 MB gacha)</span>
    </div>
    <div class="flex flex-wrap gap-2" x-show="previews.length" x-cloak>
        <template x-for="(p, i) in previews" :key="i">
            <div class="relative"><img :src="p" class="h-16 w-16 rounded-lg border border-ink-200 object-cover dark:border-ink-700" alt="">
                <button type="button" class="absolute -right-1.5 -top-1.5 rounded-full bg-red-600 px-1.5 text-xs font-bold text-white" @click="remove(i)" aria-label="Olib tashlash">×</button></div>
        </template>
    </div>
    @error('files')<p class="error-text">{{ $message }}</p>@enderror
    @error('files.*')<p class="error-text">{{ $message }}</p>@enderror
</div>
<script>
    function attachPicker() {
        return {
            files: [], previews: [],
            bind() {
                const form = this.$el.closest('form');
                const ta = form ? form.querySelector('textarea') : null;
                if (ta) {
                    ta.addEventListener('paste', (e) => {
                        const items = (e.clipboardData && e.clipboardData.files) ? Array.from(e.clipboardData.files).filter(f => f.type.startsWith('image/')) : [];
                        if (items.length) { e.preventDefault(); this.add(items); }
                    });
                }
            },
            add(list) { this.files = this.files.concat(list).slice(0, 3); this.push(); },
            sync() { this.add(Array.from(this.$refs.input.files)); },
            remove(i) { this.files.splice(i, 1); this.push(); },
            push() {
                const dt = new DataTransfer();
                this.files.forEach(f => dt.items.add(f));
                this.$refs.input.files = dt.files;
                this.previews = this.files.map(f => URL.createObjectURL(f));
            },
        };
    }
</script>
