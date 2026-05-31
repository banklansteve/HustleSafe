<template>
    <p
        class="whitespace-pre-wrap leading-relaxed"
        :class="isRedacted ? 'font-black uppercase tracking-wide text-rose-600' : ''"
    >
        {{ displayText }}
    </p>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    body: { type: String, default: '' },
    isRedacted: { type: Boolean, default: false },
    redactionLabel: { type: String, default: null },
});

const displayText = computed(() => {
    if (props.isRedacted) {
        return props.redactionLabel || props.body || 'REDACTED — POLICY VIOLATION';
    }

    return props.body;
});
</script>
