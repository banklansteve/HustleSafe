<template>
    <div class="flex flex-col items-center">
        <svg
            class="w-full max-w-[220px] text-slate-200"
            viewBox="0 0 200 118"
            role="img"
            :aria-label="ariaLabel"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#0f766e" />
                    <stop offset="50%" stop-color="#14b8a6" />
                    <stop offset="100%" stop-color="#f59e0b" />
                </linearGradient>
            </defs>
            <!-- background arc -->
            <path
                pathLength="100"
                d="M 16 100 A 84 84 0 0 1 184 100"
                fill="none"
                stroke="currentColor"
                stroke-width="14"
                stroke-linecap="round"
                class="text-slate-200"
            />
            <!-- value arc -->
            <path
                pathLength="100"
                d="M 16 100 A 84 84 0 0 1 184 100"
                fill="none"
                :stroke="`url(#${gradientId})`"
                stroke-width="14"
                stroke-linecap="round"
                :stroke-dasharray="100"
                :stroke-dashoffset="dashOffset"
                class="transition-[stroke-dashoffset] duration-700 ease-out"
            />
        </svg>
        <div class="-mt-2 text-center">
            <p class="font-display text-4xl font-black tracking-tight text-slate-900 tabular-nums">
                {{ clamped }}<span class="text-lg font-bold text-slate-400">/100</span>
            </p>
            <p class="mt-1 text-xs font-semibold uppercase tracking-wider text-slate-500">
                {{ label }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    score: {
        type: [Number, String],
        default: 0,
    },
    label: {
        type: String,
        default: 'Trust score',
    },
    variant: {
        type: String,
        default: 'freelancer',
    },
});

const clamped = computed(() => {
    const n = Number(props.score ?? 0);

    return Math.min(100, Math.max(0, Number.isFinite(n) ? Math.round(n) : 0));
});

const dashOffset = computed(() => 100 - clamped.value);

const gradientId = computed(() => `trust-arc-${props.variant}`);

const ariaLabel = computed(() => `${props.label}: ${clamped.value} out of 100`);
</script>
