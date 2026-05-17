<template>
    <AdminPanel eyebrow="Live activity" title="Right now">
        <template #actions>
            <Link :href="route('admin.live-activity.index')" class="rounded-xl px-3 py-2 text-xs font-black uppercase" :class="shell.btnGhost">
                View all activity
            </Link>
        </template>

        <LiveActivityFeed
            :events="events"
            :shell="shell"
            :show-controls="false"
            @inspect="inspect"
            @action="handleAction"
        />
    </AdminPanel>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import LiveActivityFeed from '@/Components/Admin/LiveActivityFeed.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    events: { type: Array, default: () => [] },
    shell: { type: Object, required: true },
});

function inspect(part) {
    if (part.href) {
        window.location.href = part.href;
    }
}

function handleAction() {
    window.location.href = route('admin.live-activity.index');
}
</script>
