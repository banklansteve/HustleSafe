<template>
    <OperationsShell title="Conversation monitoring" subtitle="Automated scanning surfaces flagged messages only — privacy-first redacted review.">
        <ConversationMonitoringWorkspace :summary="summary" :is-super-admin="false" route-prefix="operations" />
    </OperationsShell>
</template>

<script setup>
import OperationsShell from '@/Layouts/OperationsShell.vue';
import ConversationMonitoringWorkspace from '@/Components/ConversationMonitoring/ConversationMonitoringWorkspace.vue';

defineProps({
    summary: { type: Object, default: () => ({}) },
});
</script>
