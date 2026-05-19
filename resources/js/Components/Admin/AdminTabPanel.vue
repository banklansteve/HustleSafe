<template>
    <section
        v-show="modelValue === value"
        :id="`${idPrefix}-${value}-panel`"
        role="tabpanel"
        :aria-labelledby="`${idPrefix}-${value}`"
    >
        <slot />
    </section>
</template>

<script setup>
// All admin tab content must use AdminTabPanel so panels stay mounted and tab switches never remount or navigate.
defineProps({
    value: { type: String, required: true },
    modelValue: { type: String, required: true },
    idPrefix: { type: String, default: 'admin-tab' },
});

defineEmits(['update:modelValue']);
</script>
