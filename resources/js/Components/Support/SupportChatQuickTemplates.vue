<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between gap-2 px-1">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-600">Quick replies</p>
            <p class="text-[9px] font-semibold text-slate-500">Tap to load into composer</p>
        </div>

        <div class="space-y-2 xl:space-y-3">
            <div>
                <p class="mb-1.5 px-1 text-[9px] font-black uppercase tracking-wide text-emerald-800">Opening</p>
                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5 [scrollbar-width:thin] xl:mx-0 xl:flex-col xl:overflow-visible xl:px-0">
                    <button
                        v-for="item in templates.opening"
                        :key="item.id"
                        type="button"
                        class="shrink-0 rounded-full border border-emerald-200 bg-white px-3 py-2 text-xs font-bold text-emerald-900 shadow-sm transition hover:bg-emerald-50 disabled:opacity-50 xl:w-full xl:rounded-xl xl:text-left"
                        :disabled="disabled"
                        @click="$emit('select-opening', item)"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <div>
                <p class="mb-1.5 px-1 text-[9px] font-black uppercase tracking-wide text-rose-800">Closing</p>
                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5 [scrollbar-width:thin] xl:mx-0 xl:flex-col xl:overflow-visible xl:px-0">
                    <button
                        v-for="item in templates.closing"
                        :key="item.id"
                        type="button"
                        class="shrink-0 rounded-full border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-900 shadow-sm transition hover:bg-rose-50 disabled:opacity-50 xl:w-full xl:rounded-xl xl:text-left"
                        :disabled="disabled"
                        @click="$emit('select-closing', item)"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    templates: {
        type: Object,
        default: () => ({ opening: [], closing: [] }),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['select-opening', 'select-closing']);
</script>
