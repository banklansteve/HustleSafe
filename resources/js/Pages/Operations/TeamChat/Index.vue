<template>
    <OperationsShell title="Team chat" subtitle="Real-time coordination with Staff Admins and Super Admins.">
        <TeamChatWorkspace :room="room" route-namespace="operations" :is-super-admin="isSuperAdmin" :chat-bootstrap="chatBootstrap" />
    </OperationsShell>
</template>

<script setup>
import OperationsShell from '@/Layouts/OperationsShell.vue';
import TeamChatWorkspace from '@/Pages/Operations/Components/TeamChatWorkspace.vue';

defineProps({
    room: { type: Object, required: true },
    isSuperAdmin: { type: Boolean, default: false },
    chatBootstrap: { type: Object, default: null },
});
</script>
