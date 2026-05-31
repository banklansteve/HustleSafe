<template>
    <AdminShell title="Conversation monitoring" subtitle="Scanner rules, systematic escalations, and full-text review for Super Admins.">
        <ConversationMonitoringWorkspace :summary="summary" :is-super-admin="true" route-prefix="admin" :open-review-id="openReviewId" />
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import ConversationMonitoringWorkspace from '@/Components/ConversationMonitoring/ConversationMonitoringWorkspace.vue';

defineProps({
    summary: { type: Object, default: () => ({}) },
    openReviewId: { type: Number, default: null },
});
</script>
