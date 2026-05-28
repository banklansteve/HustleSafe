<template>
    <div>
        <div class="mb-2 flex items-end justify-between gap-2">
            <div>
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Composite risk</p>
                <p class="font-display text-2xl font-black" :class="tierTextClass">{{ score }}<span class="text-sm text-slate-400">/100</span></p>
            </div>
            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase" :class="tierBadgeClass">{{ tier }}</span>
        </div>
        <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/80">
            <div
                v-for="seg in segments"
                :key="seg.key"
                class="h-full transition-all"
                :style="{ width: `${seg.width}%`, backgroundColor: seg.color }"
                :title="`${seg.label}: ${seg.contribution}`"
            />
        </div>
        <ul class="mt-3 grid gap-1.5 sm:grid-cols-2">
            <li v-for="seg in segments" :key="seg.key" class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: seg.color }" />
                <span class="truncate">{{ seg.label }}</span>
                <span class="ml-auto font-black text-slate-800">{{ seg.contribution }}</span>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    score: { type: Number, default: 0 },
    tier: { type: String, default: 'low' },
    breakdown: { type: Object, default: () => ({}) },
});

const palette = {
    kyc: '#0ea5e9',
    account_activity: '#6366f1',
    disputes: '#e11d48',
    flagged_conversations: '#f97316',
    review_authenticity: '#a855f7',
    payment_behaviour: '#14b8a6',
    device_ip: '#64748b',
    velocity: '#ca8a04',
};

const segments = computed(() => {
    const entries = Object.entries(props.breakdown || {});
    const total = entries.reduce((s, [, v]) => s + (Number(v?.contribution) || 0), 0) || 1;

    return entries.map(([key, v]) => ({
        key,
        label: v?.label || key,
        contribution: Math.round(Number(v?.contribution) || 0),
        width: Math.max(0, ((Number(v?.contribution) || 0) / total) * 100),
        color: palette[key] || '#94a3b8',
    }));
});

const tierBadgeClass = computed(() => ({
    low: 'bg-emerald-100 text-emerald-800',
    medium: 'bg-amber-100 text-amber-900',
    high: 'bg-orange-100 text-orange-900',
    critical: 'bg-rose-100 text-rose-900',
}[props.tier] || 'bg-slate-100 text-slate-700'));

const tierTextClass = computed(() => ({
    low: 'text-emerald-700',
    medium: 'text-amber-700',
    high: 'text-orange-700',
    critical: 'text-rose-700',
}[props.tier] || 'text-slate-800'));
</script>
