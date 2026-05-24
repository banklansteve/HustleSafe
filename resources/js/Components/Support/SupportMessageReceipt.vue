<template>
    <span class="inline-flex items-center" :title="title" aria-hidden="true">
        <svg v-if="status === 'read'" class="h-3.5 w-4 text-sky-300" viewBox="0 0 16 11" fill="none">
            <path d="M1 5.5L4.5 9L10 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M5 5.5L8.5 9L15 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <svg v-else-if="status === 'delivered'" class="h-3.5 w-4 opacity-70" viewBox="0 0 16 11" fill="none">
            <path d="M1 5.5L4.5 9L10 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M5 5.5L8.5 9L15 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <svg v-else class="h-3.5 w-3.5 opacity-60" viewBox="0 0 12 12" fill="none">
            <path d="M2 6L5 9L10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: 'sent' },
});

const title = computed(() => {
    if (props.status === 'read') {
        return 'Read';
    }
    if (props.status === 'delivered') {
        return 'Delivered';
    }

    return 'Sent';
});
</script>
