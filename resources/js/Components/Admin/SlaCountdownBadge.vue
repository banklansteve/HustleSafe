<template>
    <div
        v-if="clock"
        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[11px] font-black uppercase tracking-wide"
        :class="clock.is_overdue ? 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200' : 'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-100'"
    >
        <span>{{ clock.label }}</span>
        <span>{{ clock.is_overdue ? 'Overdue' : countdownLabel }}</span>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    clock: { type: Object, default: null },
});

const now = ref(Date.now());
let timer;

const countdownLabel = computed(() => {
    if (!props.clock?.due_at) {
        return '—';
    }

    const due = new Date(props.clock.due_at).getTime();
    const diff = Math.max(0, due - now.value);
    const hours = Math.floor(diff / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);

    if (hours >= 48) {
        return `${Math.ceil(hours / 24)}d left`;
    }

    if (hours >= 1) {
        return `${hours}h ${minutes}m left`;
    }

    return `${minutes}m left`;
});

onMounted(() => {
    timer = setInterval(() => {
        now.value = Date.now();
    }, 30000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
});
</script>
