<template>
    <article class="rounded-2xl border p-4 transition hover:shadow-lg" :class="shell.card">
        <div class="flex items-start justify-between gap-2">
            <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">
                {{ label }}
            </p>
            <span
                v-if="trend"
                class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wide"
                :class="trendPositive ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/15 text-rose-700 dark:text-rose-300'"
            >
                {{ trend }}
            </span>
        </div>
        <p class="mt-3 font-display text-2xl font-black tracking-tight sm:text-3xl" :class="shell.cardTitle">
            {{ value }}
        </p>
        <p v-if="hint" class="mt-2 text-xs font-semibold leading-relaxed" :class="shell.cardMuted">
            {{ hint }}
        </p>
    </article>
</template>

<script setup>
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    trend: { type: String, default: '' },
    trendPositive: { type: Boolean, default: true },
});

const { shell } = useInjectedAdminTheme();
</script>
