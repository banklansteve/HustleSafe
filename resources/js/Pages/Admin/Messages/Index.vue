<template>
    <AdminShell
        title="Direct messages"
        subtitle="Internal admin-to-admin chat. Open Activity on a thread to review what that teammate has done today, this week, or this month."
    >
        <AdminMessengerWorkspace
            :initial-conversation-id="initialConversationId"
            @unread-changed="onUnreadChanged"
            @open-activity="activityStaff = $event"
        />

        <AdminMessengerPanel :open="activityOpen" :staff="activityStaff" @close="activityStaff = null" />
    </AdminShell>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminMessengerPanel from '@/Components/Admin/AdminMessengerPanel.vue';
import AdminMessengerWorkspace from '@/Components/Admin/AdminMessengerWorkspace.vue';
import AdminShell from '@/Layouts/AdminShell.vue';

const props = defineProps({
    conversation: { type: [String, Number], default: null },
});

const page = usePage();
const activityStaff = ref(null);

const initialConversationId = computed(() => props.conversation || new URLSearchParams(page.url.split('?')[1] || '').get('conversation'));

const activityOpen = computed(() => Boolean(activityStaff.value));

function onUnreadChanged(count) {
    window.dispatchEvent(new CustomEvent('admin:messenger-changed', { detail: { count } }));
}
</script>
