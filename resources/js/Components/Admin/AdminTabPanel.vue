<template>
    <section
        v-if="isVisible"
        :id="`${idPrefix}-${value}-panel`"
        role="tabpanel"
        :aria-labelledby="`${idPrefix}-${value}`"
    >
        <slot />
    </section>
</template>

<script setup>
import { computed, unref } from 'vue';

const props = defineProps({
    currentTab: { type: [String, Object], required: true },
    value: { type: String, required: true },
    idPrefix: { type: String, default: 'admin-tab' },
});

const tabKey = computed(() => String(unref(props.currentTab) ?? ''));

const isVisible = computed(() => tabKey.value === props.value);
</script>
