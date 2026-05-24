<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30 p-2 backdrop-blur-sm sm:p-4" @click.self="emit('close')">
            <aside class="flex h-full w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                <header class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-700">{{ eyebrow }}</p>
                        <h2 class="font-display mt-1 text-xl font-black text-slate-950">{{ title }}</h2>
                        <p v-if="subtitle" class="mt-1 text-sm font-semibold text-slate-600">{{ subtitle }}</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black uppercase text-slate-700 hover:bg-slate-50" @click="emit('close')">
                        Close
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <slot />
                </div>
                <footer v-if="$slots.footer" class="border-t border-slate-100 bg-slate-50/80 px-5 py-4">
                    <slot name="footer" />
                </footer>
            </aside>
        </div>
    </Teleport>
</template>

<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    eyebrow: { type: String, default: 'Details' },
});

const emit = defineEmits(['close']);
</script>
