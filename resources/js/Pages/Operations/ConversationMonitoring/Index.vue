<template>
    <OperationsShell title="Trust review" subtitle="Flagged quest and Q&A messages — warn users, review history, and escalate suspensions to Super Admin.">
        <ConversationMonitoringWorkspace :summary="summary" :is-super-admin="false" route-prefix="operations" :open-review-id="openReviewId" />
    </OperationsShell>
</template>

<script setup>
import OperationsShell from '@/Layouts/OperationsShell.vue';
import ConversationMonitoringWorkspace from '@/Components/ConversationMonitoring/ConversationMonitoringWorkspace.vue';

defineProps({
    summary: { type: Object, default: () => ({}) },
    openReviewId: { type: Number, default: null },
});
</script>
