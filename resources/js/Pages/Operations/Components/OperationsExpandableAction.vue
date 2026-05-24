<template>
    <section class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100">
        <button
            type="button"
            class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-slate-50/80"
            :class="expanded ? 'bg-primary-50/60' : ''"
            @click="expanded = !expanded"
        >
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg"
                :class="toneClasses"
                aria-hidden="true"
            >
                {{ icon }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-black text-slate-950">{{ title }}</span>
                    <span v-if="badge" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-600">{{ badge }}</span>
                </span>
                <span class="mt-0.5 block text-xs font-semibold leading-relaxed text-slate-600">{{ hint }}</span>
            </span>
            <span class="shrink-0 text-xs font-black text-slate-400">{{ expanded ? '▲' : '▼' }}</span>
        </button>

        <div v-show="expanded" class="border-t border-slate-100 bg-slate-50/50 px-4 py-4">
            <slot />
            <div v-if="submitLabel" class="mt-4 flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-black text-white shadow-md hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="busy"
                    @click="$emit('submit')"
                >
                    <span v-if="busy" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                    {{ busy ? busyLabel : submitLabel }}
                </button>
                <button v-if="secondaryLabel" type="button" class="text-xs font-bold text-slate-500 hover:text-slate-800" @click="$emit('secondary')">
                    {{ secondaryLabel }}
                </button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: '' },
    icon: { type: String, default: '⚙' },
    tone: { type: String, default: 'primary' },
    badge: { type: String, default: '' },
    submitLabel: { type: String, default: '' },
    secondaryLabel: { type: String, default: '' },
    busy: { type: Boolean, default: false },
    busyLabel: { type: String, default: 'Working…' },
    defaultOpen: { type: Boolean, default: false },
});

defineEmits(['submit', 'secondary']);

const expanded = ref(props.defaultOpen);

const toneClasses = computed(() => {
    const map = {
        primary: 'bg-primary-100 text-primary-800',
        amber: 'bg-amber-100 text-amber-900',
        rose: 'bg-rose-100 text-rose-800',
        slate: 'bg-slate-100 text-slate-700',
        sky: 'bg-sky-100 text-sky-800',
    };

    return map[props.tone] ?? map.primary;
});
</script>
