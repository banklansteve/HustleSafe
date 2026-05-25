<script setup>
import { computed } from 'vue';

const props = defineProps({
    message: {
        type: [String, Array],
        default: '',
    },
});

const display = computed(() => {
    if (Array.isArray(props.message)) {
        return props.message.filter(Boolean).join(' ');
    }

    return props.message || '';
});
</script>

<template>
    <div v-show="display">
        <p class="text-sm text-red-600 dark:text-red-400">
            {{ display }}
        </p>
    </div>
</template>
