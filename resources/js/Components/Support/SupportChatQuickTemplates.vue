<template>
    <div class="mb-2 rounded-xl border border-slate-200 bg-slate-50/80">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-600">Quick replies</span>
            <span class="text-[10px] font-bold text-primary-700">{{ expanded ? 'Hide' : 'Show' }}</span>
        </button>

        <div v-show="expanded" class="space-y-2 border-t border-slate-200 px-2 pb-2.5 pt-2">
            <div>
                <p class="mb-1.5 px-1 text-[9px] font-black uppercase tracking-wide text-emerald-800">Opening</p>
                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5 [scrollbar-width:thin]">
                    <button
                        v-for="item in templates.opening"
                        :key="item.id"
                        type="button"
                        class="shrink-0 rounded-full border border-emerald-200 bg-white px-3 py-2 text-xs font-bold text-emerald-900 shadow-sm transition hover:bg-emerald-50 disabled:opacity-50"
                        :disabled="disabled"
                        @click="$emit('send-opening', item)"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <div>
                <p class="mb-1.5 px-1 text-[9px] font-black uppercase tracking-wide text-rose-800">Closing · ends session</p>
                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5 [scrollbar-width:thin]">
                    <button
                        v-for="item in templates.closing"
                        :key="item.id"
                        type="button"
                        class="shrink-0 rounded-full border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-900 shadow-sm transition hover:bg-rose-50 disabled:opacity-50"
                        :disabled="disabled"
                        @click="$emit('send-closing', item)"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

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

defineEmits(['send-opening', 'send-closing']);

const expanded = ref(true);
</script>
