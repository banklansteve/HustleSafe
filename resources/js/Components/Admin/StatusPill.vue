<template>
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-black capitalize"
        :class="pillClass"
    >
        {{ displayStatus }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const displayStatus = computed(() => String(props.status ?? '').replace(/_/g, ' '));

const pillClass = computed(() => {
    const s = String(props.status ?? '').toLowerCase();
    if (['funded', 'released', 'completed', 'active', 'approved'].includes(s)) {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200';
    }
    if (['pending', 'processing', 'awaiting_funding', 'held'].includes(s)) {
        return 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200';
    }
    if (['refunded', 'failed', 'cancelled', 'locked', 'disputed'].includes(s)) {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200';
    }

    return 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200';
});
</script>
