<template>
    <AdminPanel eyebrow="Live activity" :title="compact ? 'Recent signals' : 'Right now'">
        <template #actions>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="compact"
                    :href="route('admin.insights.index')"
                    prefetch="false"
                    class="rounded-xl px-3 py-2 text-xs font-black uppercase"
                    :class="shell.btnGhost"
                    @click.prevent="goInsights"
                >
                    Insights
                </Link>
                <Link
                    :href="route('admin.live-activity.index')"
                    prefetch="false"
                    class="rounded-xl px-3 py-2 text-xs font-black uppercase"
                    :class="shell.btnGhost"
                    @click.prevent="goAllActivity"
                >
                    View all activity
                </Link>
            </div>
        </template>

        <div v-if="compact" class="space-y-3">
            <p v-if="!previewEvents.length" class="text-sm font-semibold" :class="shell.cardMuted">
                No recent platform events.
            </p>
            <ul v-else class="space-y-2">
                <li
                    v-for="event in previewEvents"
                    :key="event.uuid || event.id"
                    class="rounded-xl border px-3 py-2.5"
                    :class="shell.card"
                >
                    <p class="text-xs font-black uppercase tracking-wide" :class="shell.cardMuted">
                        {{ event.category }}
                    </p>
                    <p class="mt-0.5 text-sm font-bold leading-snug" :class="shell.cardTitle">
                        {{ event.title }}
                    </p>
                    <p class="mt-1 text-[11px] font-semibold" :class="shell.cardMuted">
                        {{ event.occurred_at_label }}
                    </p>
                </li>
            </ul>
            <p class="text-[11px] font-semibold" :class="shell.cardMuted">
                Full audit stream lives on the activity page — this panel stays out of your KPI view.
            </p>
        </div>

        <LiveActivityFeed
            v-else
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
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    events: { type: Array, default: () => [] },
    shell: { type: Object, required: true },
    compact: { type: Boolean, default: false },
    previewLimit: { type: Number, default: 3 },
});

const previewEvents = computed(() => props.events.slice(0, props.previewLimit));

function goAllActivity() {
    router.visit(route('admin.live-activity.index'), { preserveScroll: true, preserveState: true });
}

function goInsights() {
    router.visit(route('admin.insights.index'), { preserveScroll: true, preserveState: true });
}

function inspect(part) {
    if (!part.href) {
        return;
    }
    router.visit(part.href, { preserveScroll: true, preserveState: true });
}

function handleAction() {
    router.visit(route('admin.live-activity.index'), { preserveScroll: true, preserveState: true });
}
</script>
