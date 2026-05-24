<template>
    <ErrorPageLayout
        code="404"
        title="This hustle went off-map"
        :subtitle="message || 'We searched the whole toolbox — that page is not here. It may have moved, expired, or never existed.'"
        detail="Double-check the link or head home to find your next quest."
    />
</template>

<script setup>
import ErrorPageLayout from '@/Pages/Errors/ErrorPageLayout.vue';

defineProps({
    message: { type: String, default: '' },
});
</script>
