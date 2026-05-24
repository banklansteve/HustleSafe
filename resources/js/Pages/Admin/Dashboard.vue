<template>
    <AdminShell
        title="Command overview"
        subtitle="Live KPIs on white cards over the teal canvas. Toggle chart mode from the sidebar."
    >
        <AdminPanel eyebrow="Quick actions" title="Data interchange">
            <template #actions>
                <AdminQuickActions
                    :export-actions="[
                        { label: 'Export CSV', href: route('admin.dashboard.export') },
                        { label: 'Reports hub', href: route('admin.reports.index') },
                    ]"
                />
            </template>
        </AdminPanel>

        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            <AdminKpiTile
                v-for="tile in kpiTiles"
                :key="tile.label"
                :label="tile.label"
                :value="tile.value"
                :hint="tile.hint"
                :trend="tile.trend"
                :trend-positive="tile.trendPositive"
            />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(260px,300px)] xl:items-start">
            <Suspense>
                <template #default>
                    <AsyncDashboardCharts :charts="charts" :leaderboards="leaderboards" />
                </template>
                <template #fallback>
                    <div class="grid gap-2 xl:grid-cols-2">
                        <div v-for="i in 6" :key="i" class="h-72 animate-pulse rounded-2xl" :class="shell.card" />
                    </div>
                </template>
            </Suspense>

            <aside v-if="live_activity" class="xl:sticky xl:top-24">
                <LiveActivityWidget
                    :events="live_activity.events"
                    :shell="shell"
                    compact
                    :preview-limit="3"
                />
            </aside>
        </div>

        <details
            v-if="resource_groups?.length"
            class="group mt-8 rounded-2xl border px-4 py-3 sm:px-5"
            :class="shell.card"
        >
            <summary
                class="cursor-pointer list-none text-sm font-black [&::-webkit-details-marker]:hidden"
                :class="shell.cardTitle"
            >
                <span class="inline-flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">Platform registry</span>
                    <span class="text-slate-400 transition group-open:rotate-90" aria-hidden="true">›</span>
                </span>
                <span class="mt-1 block text-xs font-semibold" :class="shell.cardMuted">
                    Manage models — create, edit, delete, and audit (advanced)
                </span>
            </summary>
            <div class="mt-4 space-y-4 border-t border-slate-200/80 pt-4 dark:border-white/10">
                <section v-for="group in resource_groups" :key="group.key">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">{{ group.label }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Link
                            v-for="res in group.resources"
                            :key="res.key"
                            :href="route('admin.management.index', { resource: res.key })"
                            prefetch="false"
                            class="rounded-xl border px-3 py-2 text-xs font-bold transition"
                            :class="shell.btnGhost"
                        >
                            {{ res.label }}
                        </Link>
                    </div>
                </section>
                <Link
                    :href="route('admin.management.index')"
                    prefetch="false"
                    class="inline-flex rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide"
                    :class="shell.btnPrimary"
                >
                    Open management hub
                </Link>
            </div>
        </details>

        <p class="mt-8 text-center text-xs font-semibold" :class="shell.canvasMuted">
            Snapshot {{ generatedAtLabel }} ·
            <Link :href="route('admin.dashboard')" class="font-bold" :class="shell.link">
                {{ publicUrl }}/admin
            </Link>
        </p>
    </AdminShell>
</template>

<script setup>
import AdminKpiTile from '@/Components/Admin/AdminKpiTile.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminQuickActions from '@/Components/Admin/AdminQuickActions.vue';
import LiveActivityWidget from '@/Components/Admin/LiveActivityWidget.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link } from '@inertiajs/vue3';
import { computed, defineAsyncComponent } from 'vue';

const AsyncDashboardCharts = defineAsyncComponent(() => import('./DashboardCharts.vue'));

const props = defineProps({
    kpi: { type: Object, required: true },
    charts: { type: Object, required: true },
    leaderboards: { type: Object, required: true },
    generated_at: { type: String, required: true },
    resource_groups: { type: Array, default: () => [] },
    live_activity: { type: Object, default: null },
});

const { shell } = useInjectedAdminTheme();

function formatNgn(minor) {
    const n = Number(minor) / 100;

    return `₦${n.toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

const publicUrl = computed(() => (typeof window !== 'undefined' ? window.location.origin : ''));

const generatedAtLabel = computed(() => {
    try {
        return new Date(props.generated_at).toLocaleString('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return props.generated_at;
    }
});

const hireRate = computed(() => {
    const total = Number(props.kpi.proposals_total) || 0;
    if (total === 0) {
        return '0%';
    }

    return `${Math.round((Number(props.kpi.proposals_hired) / total) * 100)}%`;
});

const kpiTiles = computed(() => [
    { label: 'Total users', value: props.kpi.users_total, hint: `${props.kpi.users_new_30d} new (30d)` },
    { label: 'Quests posted', value: props.kpi.quests_posted, hint: `${props.kpi.completion_rate_pct}% completed` },
    { label: 'Escrow held', value: formatNgn(props.kpi.escrow_held_minor), hint: 'Funded engagements' },
    { label: 'Paid out', value: formatNgn(props.kpi.paid_out_minor), hint: 'Lifetime releases' },
    { label: 'Open disputes', value: props.kpi.disputes_open, hint: `${props.kpi.dispute_resolution_rate_pct}% resolved overall`, trendPositive: true },
    { label: 'Verification queue', value: props.kpi.verification_queue, hint: 'Pending / in review' },
    { label: 'Open reports', value: props.kpi.open_reports, hint: 'Content moderation flags' },
    { label: 'Hire conversion', value: hireRate.value, hint: `${props.kpi.proposals_hired} hires / ${props.kpi.proposals_total} proposals` },
]);
</script>
