<template>
    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                    {{ title }}
                </h3>
                <p v-if="subtitle" class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">
                    {{ subtitle }}
                </p>
            </div>
            <div class="flex rounded-xl bg-slate-100 p-1 text-xs font-bold">
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 transition"
                    :class="range === 'six' ? 'bg-white text-primary-800 shadow-sm' : 'text-slate-600'"
                    @click="range = 'six'"
                >
                    6 mo
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 transition"
                    :class="range === 'twelve' ? 'bg-white text-primary-800 shadow-sm' : 'text-slate-600'"
                    @click="range = 'twelve'"
                >
                    12 mo
                </button>
            </div>
        </div>

        <div class="mt-8 flex h-44 items-end gap-1.5 sm:gap-2" role="img" :aria-label="title">
            <div
                v-for="(v, i) in active.values"
                :key="i"
                class="group flex min-w-0 flex-1 flex-col items-center justify-end"
            >
                <div
                    class="w-full max-w-[2.5rem] rounded-t-md bg-gradient-to-t from-primary-700 to-teal-500 transition group-hover:from-primary-800 group-hover:to-teal-600 sm:max-w-none"
                    :style="{ height: barHeight(v) }"
                />
                <p class="mt-2 truncate text-center text-[10px] font-bold uppercase tracking-wide text-slate-500 sm:text-xs">
                    {{ active.labels[i] }}
                </p>
                <p class="mt-0.5 text-center text-[10px] font-semibold text-slate-700 sm:text-xs">
                    {{ formatMinor(v) }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    six: {
        type: Object,
        required: true,
    },
    twelve: {
        type: Object,
        required: true,
    },
});

const range = ref('six');

const active = computed(() => (range.value === 'twelve' ? props.twelve : props.six));

function barHeight(minor) {
    const chart = range.value === 'twelve' ? props.twelve : props.six;
    const peak = chart.peak > 0 ? chart.peak : 1;
    const pct = Math.max(6, Math.round((Number(minor) / peak) * 100));

    return `${pct}%`;
}

function formatMinor(minor) {
    const n = Number(minor) / 100;

    if (n >= 1_000_000) {
        return `₦${(n / 1_000_000).toFixed(1)}m`;
    }
    if (n >= 1000) {
        return `₦${(n / 1000).toFixed(0)}k`;
    }

    return `₦${Math.round(n)}`;
}
</script>
