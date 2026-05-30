<template>
    <div class="space-y-4">
        <ol class="relative border-s border-slate-200 pl-6">
            <li v-for="stage in stages" :key="stage.key" class="mb-6 last:mb-0">
                <span
                    class="absolute -left-[9px] flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-white"
                    :class="dotClass(stage.status)"
                    aria-hidden="true"
                />
                <div class="rounded-xl border px-3 py-3" :class="cardClass(stage.status)">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="text-sm font-black text-slate-900">{{ stage.label }}</p>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="badgeClass(stage.status)">{{ statusLabel(stage.status) }}</span>
                    </div>
                    <p v-if="stage.at" class="mt-1 text-xs font-semibold text-slate-600">{{ formatAt(stage.at) }}</p>
                    <p v-if="stage.actor" class="mt-1 text-xs text-slate-500">{{ stage.actor_role }} · {{ stage.actor }}</p>
                    <p v-if="stage.hint && stage.status !== 'completed'" class="mt-2 text-xs leading-5 text-slate-600">{{ stage.hint }}</p>
                </div>
            </li>
        </ol>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatHumanDateTime } from '@/utils/formatHumanDateTime';

const props = defineProps({
    timeline: { type: Object, default: null },
});

const stages = computed(() => props.timeline?.stages ?? []);

function formatAt(iso) {
    return formatHumanDateTime(iso);
}

function statusLabel(status) {
    if (status === 'completed') return 'Done';
    if (status === 'current') return 'Now';
    return 'Pending';
}

function dotClass(status) {
    if (status === 'completed') return 'bg-emerald-500';
    if (status === 'current') return 'bg-primary-600 animate-pulse';
    return 'bg-slate-300';
}

function cardClass(status) {
    if (status === 'completed') return 'border-emerald-100 bg-emerald-50/50';
    if (status === 'current') return 'border-primary-200 bg-primary-50/60';
    return 'border-slate-100 bg-slate-50/50';
}

function badgeClass(status) {
    if (status === 'completed') return 'bg-emerald-100 text-emerald-900';
    if (status === 'current') return 'bg-primary-100 text-primary-900';
    return 'bg-slate-100 text-slate-600';
}
</script>
