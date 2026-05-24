<template>
    <ErrorPageLayout
        code="403"
        title="Wrong workshop door"
        :subtitle="message || 'You do not have permission to open this area. If you are staff, use the Operations console — super-admin tools live on a separate floor.'"
        detail="Need access? Ask a super admin to check your role assignment."
    />
</template>

<script setup>
import ErrorPageLayout from '@/Pages/Errors/ErrorPageLayout.vue';

defineProps({
    message: { type: String, default: '' },
});
</script>
