<template>
    <AdminPanel eyebrow="Support operations" title="Support ticket analytics">
        <template #actions>
            <Link :href="route('admin.support-tickets.index')" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost">Open tickets</Link>
        </template>

        <p class="text-sm font-semibold leading-6" :class="shell.cardMuted">
            Managed customer support tickets across all staff admins. Metrics refresh once daily.
        </p>
        <p class="mt-2 text-xs font-bold" :class="shell.cardMuted">
            Last refreshed {{ refreshedLabel }} · Next refresh {{ nextRefreshLabel }}
        </p>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <AdminKpiTile
                label="Open tickets"
                :value="analytics.open_tickets ?? 0"
                :hint="(analytics.open_tickets ?? 0) === 0 ? '0 / No open managed tickets right now.' : 'Open, in progress, or awaiting customer.'"
            />
            <AdminKpiTile
                label="Avg resolution time"
                :value="analytics.average_resolution_label || '—'"
                :hint="analytics.average_resolution_hours ? `${analytics.average_resolution_hours} hours average on closed tickets.` : 'No resolved tickets with close dates yet.'"
            />
            <AdminKpiTile
                label="SLA breach rate"
                :value="`${analytics.sla_breach_rate ?? 0}%`"
                :hint="(analytics.sla_breached_count ?? 0) > 0 ? `${analytics.sla_breached_count} ticket(s) currently overdue.` : 'No active SLA breaches.'"
                :trend="`${analytics.sla_breach_rate ?? 0}%`"
                :trend-positive="Number(analytics.sla_breach_rate ?? 0) === 0"
            />
        </div>

        <section class="mt-6 rounded-2xl border p-4" :class="shell.card">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-black" :class="shell.cardTitle">Ticket volume trends</h3>
                    <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Created vs resolved tickets over time.</p>
                </div>
                <div class="flex gap-2">
                    <button
                        v-for="option in trendOptions"
                        :key="option.key"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-black transition"
                        :class="trendGrain === option.key ? shell.navActive : shell.btnGhost"
                        @click="trendGrain = option.key"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div v-if="!trendRows.length" class="mt-6 rounded-2xl px-4 py-10 text-center text-sm font-semibold" :class="shell.cardMuted">
                No ticket trend data yet.
            </div>
            <div v-else class="mt-6">
                <svg viewBox="0 0 400 220" class="h-64 w-full overflow-visible">
                    <defs>
                        <linearGradient id="ticketCreatedGradient" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="rgb(14 165 233)" stop-opacity=".25" />
                            <stop offset="100%" stop-color="rgb(14 165 233)" stop-opacity=".02" />
                        </linearGradient>
                    </defs>
                    <polygon :points="createdArea" fill="url(#ticketCreatedGradient)" />
                    <polyline :points="createdLine" fill="none" stroke="rgb(14 165 233)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    <polyline :points="resolvedLine" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="6 4" />
                    <circle
                        v-for="(point, index) in chartPoints"
                        :key="`created-${index}`"
                        :cx="point.x"
                        :cy="point.createdY"
                        r="3.5"
                        fill="rgb(14 165 233)"
                        stroke="white"
                        stroke-width="2"
                    />
                    <circle
                        v-for="(point, index) in chartPoints"
                        :key="`resolved-${index}`"
                        :cx="point.x"
                        :cy="point.resolvedY"
                        r="3.5"
                        fill="#10b981"
                        stroke="white"
                        stroke-width="2"
                    />
                </svg>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-4 text-xs font-bold" :class="shell.cardMuted">
                        <span class="inline-flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-full bg-sky-500" /> Created</span>
                        <span class="inline-flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-full bg-emerald-500" /> Resolved</span>
                    </div>
                    <p class="text-xs font-bold" :class="shell.cardMuted">
                        Peak created: {{ peakCreated }} · Peak resolved: {{ peakResolved }}
                    </p>
                </div>
                <div class="mt-3 flex justify-between gap-1 text-[10px] font-black uppercase tracking-wide" :class="shell.cardMuted">
                    <span v-for="point in labelPoints" :key="point.label">{{ point.label }}</span>
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-4 xl:grid-cols-2">
            <section class="rounded-2xl border p-4" :class="shell.card">
                <h3 class="text-sm font-black" :class="shell.cardTitle">Tickets by category</h3>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">All managed tickets grouped by issue type.</p>
                <ul class="mt-4 space-y-3">
                    <li v-for="row in categoryRows" :key="row.key" class="space-y-1">
                        <div class="flex items-center justify-between gap-3 text-xs font-bold">
                            <span :class="shell.cardTitle">{{ row.label }}</span>
                            <span :class="shell.cardMuted">{{ row.total }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-primary-600 transition-all" :style="{ width: `${barWidth(row.total, analytics.category_max)}%` }" />
                        </div>
                    </li>
                    <li v-if="!categoryRows.length" class="text-sm font-semibold" :class="shell.cardMuted">No categorized tickets yet.</li>
                </ul>
            </section>

            <section class="rounded-2xl border p-4" :class="shell.card">
                <h3 class="text-sm font-black" :class="shell.cardTitle">Per-admin workload</h3>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Open ticket count by assignee.</p>
                <ul class="mt-4 space-y-3">
                    <li v-for="row in workloadRows" :key="row.admin" class="space-y-1">
                        <div class="flex items-center justify-between gap-3 text-xs font-bold">
                            <span :class="shell.cardTitle">{{ row.admin }}</span>
                            <span :class="shell.cardMuted">{{ row.total }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-sky-600 transition-all" :style="{ width: `${barWidth(row.total, analytics.workload_max)}%` }" />
                        </div>
                    </li>
                    <li v-if="!workloadRows.length" class="text-sm font-semibold" :class="shell.cardMuted">No open assignments yet.</li>
                </ul>
            </section>
        </div>
    </AdminPanel>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminKpiTile from '@/Components/Admin/AdminKpiTile.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { formatLeaveDateTime } from '@/utils/formatHumanDateTime';

const props = defineProps({
    analytics: { type: Object, default: () => ({}) },
});

const { shell } = useInjectedAdminTheme();

const trendGrain = ref('daily');
const trendOptions = [
    { key: 'daily', label: 'Daily' },
    { key: 'weekly', label: 'Weekly' },
    { key: 'monthly', label: 'Monthly' },
];

const categoryRows = computed(() => props.analytics.tickets_by_category ?? []);
const workloadRows = computed(() => props.analytics.workload_by_admin ?? []);
const trendRows = computed(() => props.analytics.ticket_trends?.[trendGrain.value] ?? []);

const refreshedLabel = computed(() => formatTimestamp(props.analytics.refreshed_at));
const nextRefreshLabel = computed(() => formatTimestamp(props.analytics.next_refresh_at));

const chartPoints = computed(() => {
    const rows = trendRows.value;
    if (!rows.length) {
        return [];
    }

    const max = Math.max(1, ...rows.flatMap((row) => [Number(row.created || 0), Number(row.resolved || 0)]));
    const step = rows.length > 1 ? 344 / (rows.length - 1) : 0;

    return rows.map((row, index) => {
        const x = 28 + index * step;
        const createdY = 190 - (Number(row.created || 0) / max) * 150;
        const resolvedY = 190 - (Number(row.resolved || 0) / max) * 150;

        return { ...row, x, createdY, resolvedY };
    });
});

const createdLine = computed(() => chartPoints.value.map((point) => `${point.x},${point.createdY}`).join(' '));
const resolvedLine = computed(() => chartPoints.value.map((point) => `${point.x},${point.resolvedY}`).join(' '));
const createdArea = computed(() => {
    if (!chartPoints.value.length) {
        return '';
    }

    return `28,190 ${chartPoints.value.map((point) => `${point.x},${point.createdY}`).join(' ')} 372,190`;
});

const labelPoints = computed(() => {
    const rows = trendRows.value;
    if (rows.length <= 6) {
        return rows;
    }

    const step = Math.ceil(rows.length / 6);

    return rows.filter((_, index) => index % step === 0 || index === rows.length - 1);
});

const peakCreated = computed(() => Math.max(0, ...trendRows.value.map((row) => Number(row.created || 0))));
const peakResolved = computed(() => Math.max(0, ...trendRows.value.map((row) => Number(row.resolved || 0))));

function formatTimestamp(value) {
    return value ? formatLeaveDateTime(value) : '—';
}

function barWidth(value, max) {
    const total = Number(max) || 1;
    return Math.max(4, Math.round((Number(value || 0) / total) * 100));
}
</script>
