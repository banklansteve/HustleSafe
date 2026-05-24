<template>
    <AdminShell title="Team chat" subtitle="Real-time coordination with the operations team.">
        <TeamChatWorkspace :room="room" route-namespace="admin" :is-super-admin="true" :chat-bootstrap="chatBootstrap" />
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import TeamChatWorkspace from '@/Pages/Operations/Components/TeamChatWorkspace.vue';

defineProps({
    room: { type: Object, required: true },
    chatBootstrap: { type: Object, default: null },
});
</script>
