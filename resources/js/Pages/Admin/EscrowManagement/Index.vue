<template>
    <AdminShell
        title="Escrow Management"
        subtitle="Trust through transparency — every kobo tracked, every transaction auditable."
    >
        <div class="space-y-5">
            <!-- Position statement (sticky) -->
            <section class="sticky top-0 z-20 rounded-3xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur-md dark:border-white/10 dark:bg-slate-950/95 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-primary-700">Escrow position statement</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">As of {{ data.position?.as_of_label }}</p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide"
                        :class="healthBadgeClass(data.position?.health_status)"
                    >
                        {{ data.position?.health_label }}
                        <span v-if="data.position?.at_risk_ratio_percent != null"> · {{ data.position.at_risk_ratio_percent }}% at-risk</span>
                    </span>
                </div>

                <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-2xl border border-teal-200 bg-teal-50/60 p-3 dark:border-teal-900/40 dark:bg-teal-950/20">
                        <dt class="text-[10px] font-black uppercase text-teal-800">Total escrow held</dt>
                        <dd class="mt-1 font-display text-xl font-black text-slate-950 dark:text-white">{{ data.position?.total_held_display }}</dd>
                        <dd class="text-[10px] font-semibold text-slate-600">{{ data.position?.held_count }} active</dd>
                    </div>
                    <div class="rounded-2xl border p-3" :class="shell.card">
                        <dt class="text-[10px] font-black uppercase" :class="shell.label">Due for release today</dt>
                        <dd class="mt-1 text-xl font-black" :class="shell.title">{{ data.position?.due_today_display }}</dd>
                        <dd class="text-[10px] font-semibold" :class="shell.cardMuted">{{ data.position?.due_today_count }} job(s)</dd>
                    </div>
                    <div class="rounded-2xl border p-3" :class="shell.card">
                        <dt class="text-[10px] font-black uppercase" :class="shell.label">Due in next 7 days</dt>
                        <dd class="mt-1 text-xl font-black" :class="shell.title">{{ data.position?.due_week_display }}</dd>
                        <dd class="text-[10px] font-semibold" :class="shell.cardMuted">{{ data.position?.due_week_count }} job(s)</dd>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-3 dark:border-rose-900/40">
                        <dt class="text-[10px] font-black uppercase text-rose-800">At risk (disputed)</dt>
                        <dd class="mt-1 text-xl font-black text-rose-950">{{ data.position?.at_risk_display }}</dd>
                        <dd class="text-[10px] font-semibold text-rose-700">{{ data.position?.at_risk_count }} dispute(s)</dd>
                    </div>
                    <div class="rounded-2xl border p-3" :class="shell.card">
                        <dt class="text-[10px] font-black uppercase" :class="shell.label">Clearing (in payment)</dt>
                        <dd class="mt-1 text-xl font-black" :class="shell.title">{{ data.position?.clearing_display }}</dd>
                        <dd class="text-[10px] font-semibold" :class="shell.cardMuted">{{ data.position?.clearing_count }} withdrawal(s)</dd>
                    </div>
                </dl>
            </section>

            <!-- Alerts -->
            <div v-if="data.alerts?.length" class="space-y-2">
                <button
                    v-for="(alert, i) in data.alerts"
                    :key="i"
                    type="button"
                    class="flex w-full items-center justify-between rounded-2xl border px-4 py-3 text-left text-sm font-bold transition hover:opacity-90"
                    :class="alert.severity === 'red' ? 'border-rose-300 bg-rose-50 text-rose-950' : 'border-amber-300 bg-amber-50 text-amber-950'"
                    @click="onAlertClick(alert)"
                >
                    <span>{{ alert.severity === 'red' ? '🔴' : '🟠' }} {{ alert.message }}</span>
                    <span class="text-xs uppercase">View →</span>
                </button>
            </div>

            <!-- Metric cards -->
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Escrow inflow</p>
                    <p class="mt-2 text-xs font-semibold" :class="shell.cardMuted">Today · {{ data.metrics?.inflow?.today_display }}</p>
                    <p class="text-lg font-black" :class="shell.title">Week · {{ data.metrics?.inflow?.week_display }}</p>
                    <p class="mt-1 text-xs font-bold text-emerald-700">{{ data.metrics?.inflow?.trend_label }}</p>
                </div>
                <div class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Escrow outflow</p>
                    <p class="mt-2 text-xs font-semibold" :class="shell.cardMuted">Today · {{ data.metrics?.outflow?.today_display }}</p>
                    <p class="text-lg font-black" :class="shell.title">Week · {{ data.metrics?.outflow?.week_display }}</p>
                    <p class="mt-1 text-xs font-bold text-slate-600">{{ data.metrics?.outflow?.trend_label }}</p>
                </div>
                <div class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Average hold time</p>
                    <p class="mt-2 text-2xl font-black" :class="shell.title">{{ data.metrics?.average_hold?.days }} days</p>
                    <p class="text-xs font-semibold" :class="shell.cardMuted">Benchmark {{ data.metrics?.average_hold?.benchmark_days }} days</p>
                    <p class="mt-1 text-xs font-bold text-emerald-700">✓ {{ data.metrics?.average_hold?.status_label }}</p>
                </div>
                <div class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Dispute rate</p>
                    <p class="mt-2 text-lg font-black" :class="shell.title">{{ data.metrics?.dispute_rate?.count }} ({{ data.metrics?.dispute_rate?.total_display }})</p>
                    <p class="text-xs font-semibold" :class="shell.cardMuted">{{ data.metrics?.dispute_rate?.rate_percent }}% of total</p>
                    <p class="mt-1 text-xs font-bold" :class="data.metrics?.dispute_rate?.rate_percent <= 1 ? 'text-emerald-700' : 'text-amber-700'">{{ data.metrics?.dispute_rate?.trend_label }}</p>
                </div>
            </section>

            <!-- Charts -->
            <div class="grid gap-5 xl:grid-cols-2">
                <AdminPanel title="Escrow ledger balance (30 days)" :description="data.balance_series?.peak_annotation || 'Held balance trend'">
                    <VueApexCharts type="area" height="280" :options="balanceChartOptions" :series="balanceChartSeries" />
                </AdminPanel>
                <AdminPanel title="Release timeline" description="When held funds are expected to flow out. Click a segment to filter the table.">
                    <VueApexCharts type="bar" height="280" :options="waterfallChartOptions" :series="waterfallChartSeries" />
                    <ul class="mt-3 space-y-1 text-xs font-semibold" :class="shell.cardMuted">
                        <li v-for="seg in data.release_waterfall?.segments" :key="seg.key" class="flex justify-between">
                            <button type="button" class="font-bold text-primary-700 underline" @click="applyDueBucket(seg.key)">{{ seg.label }}</button>
                            <span>{{ seg.amount_display }} · {{ seg.count }}</span>
                        </li>
                    </ul>
                </AdminPanel>
            </div>

            <!-- Health gauges -->
            <AdminPanel title="Escrow health dashboard" :description="`Overall: ${data.health?.overall_label}`">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="gauge in data.health?.gauges" :key="gauge.key" class="rounded-2xl border p-4" :class="shell.card">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[10px] font-black uppercase tracking-wide" :class="shell.label">{{ gauge.label }}</p>
                            <span class="text-[10px] font-black uppercase" :class="gaugeStatusClass(gauge.status)">{{ gauge.status }}</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                            <div class="h-full rounded-full transition-all" :class="gaugeBarClass(gauge.status)" :style="{ width: `${gauge.percent}%` }" />
                        </div>
                        <p class="mt-2 text-sm font-black" :class="shell.title">{{ gauge.percent }}%</p>
                        <p class="text-[10px] font-semibold text-slate-500">Ideal: {{ gauge.ideal }}</p>
                    </div>
                </div>
            </AdminPanel>

            <!-- Table -->
            <AdminPanel title="Escrow audit table" description="Sortable, filterable view of all escrow-backed contracts.">
                <div class="mb-3 flex flex-wrap gap-2">
                    <button
                        v-for="qv in quickViews"
                        :key="qv.key"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide transition"
                        :class="filters.quick_view === qv.key ? 'bg-primary-600 text-white' : shell.btnGhost"
                        @click="applyQuickView(qv.key)"
                    >
                        {{ qv.label }}
                    </button>
                </div>

                <div class="mb-4 grid gap-3 md:grid-cols-4">
                    <input v-model="filters.q" type="search" placeholder="Search contract, client, freelancer…" class="rounded-2xl border px-4 py-3 text-sm font-semibold md:col-span-2" :class="shell.input" @input="debouncedApply" />
                    <select v-model="filters.status" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                        <option value="">All statuses</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <select v-model="filters.category_id" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                        <option value="">All categories</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                <th class="px-3 py-3">Contract</th>
                                <th class="px-3 py-3">Client</th>
                                <th class="px-3 py-3">Freelancer</th>
                                <th class="px-3 py-3">Quest</th>
                                <th class="px-3 py-3">Amount</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Due</th>
                                <th class="px-3 py-3">VAT</th>
                                <th class="px-3 py-3">Fee</th>
                                <th class="px-3 py-3">Payout</th>
                                <th class="px-3 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <tr
                                v-for="row in data.listing?.data"
                                :key="row.id"
                                class="cursor-pointer hover:bg-primary-50/50 dark:hover:bg-white/[0.03]"
                                @click="openRecord(row)"
                            >
                                <td class="px-3 py-3 font-mono text-xs font-black">{{ row.contract_reference || row.escrow_reference }}</td>
                                <td class="px-3 py-3 text-xs">{{ row.client_name }}</td>
                                <td class="px-3 py-3 text-xs">{{ row.freelancer_name }}</td>
                                <td class="px-3 py-3 text-xs font-semibold">{{ row.quest_title }}</td>
                                <td class="px-3 py-3 font-black">{{ row.funded_display }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusPillClass(row)">{{ row.status_label }}</span>
                                </td>
                                <td class="px-3 py-3 text-xs font-bold" :class="urgencyClass(row.urgency)">
                                    {{ row.scheduled_release_label || row.due_date_label || '—' }}
                                    <span v-if="row.urgency === 'warning'"> ⚠️</span>
                                    <span v-if="row.urgency === 'urgent'"> 🔴</span>
                                </td>
                                <td class="px-3 py-3 text-xs">{{ row.vat_display }}</td>
                                <td class="px-3 py-3 text-xs">{{ row.platform_fee_display }}</td>
                                <td class="px-3 py-3 text-xs font-black text-emerald-800">{{ row.freelancer_net_display }}</td>
                                <td class="px-3 py-3">
                                    <button type="button" class="text-[10px] font-black uppercase text-primary-700 underline" @click.stop="openRecord(row)">View</button>
                                </td>
                            </tr>
                            <tr v-if="!data.listing?.data?.length">
                                <td colspan="11" class="px-3 py-10 text-center text-sm font-semibold text-slate-500">No escrows match your filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="data.listing?.totals" class="mt-3 flex flex-wrap gap-4 text-xs font-bold" :class="shell.cardMuted">
                    <span>{{ data.listing.totals.count }} records</span>
                    <span>Gross {{ data.listing.totals.gross_display }}</span>
                    <span>Net {{ data.listing.totals.net_display }}</span>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <button type="button" class="rounded-full border px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost" @click="generateReconciliation">
                        Generate reconciliation report
                    </button>
                    <Link :href="route('admin.financial-audit.escrow-ledger.export')" class="text-xs font-black uppercase text-primary-700 underline">Export CSV</Link>
                </div>
            </AdminPanel>
        </div>

        <EscrowManagementDetailSlideOver
            :open="slideOpen"
            :record-id="selectedRecordId"
            @close="slideOpen = false"
            @updated="refreshDashboard"
        />

        <Teleport to="body">
            <div v-if="reconciliationOpen" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/50 p-4" @click.self="reconciliationOpen = false">
                <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-3xl border bg-white p-6 shadow-2xl dark:bg-slate-900">
                    <h3 class="font-display text-lg font-black">Escrow reconciliation snapshot</h3>
                    <p v-if="reconciliationLoading" class="mt-4 text-sm font-semibold">Generating…</p>
                    <dl v-else-if="reconciliation" class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt>Held (system)</dt><dd class="font-black">{{ reconciliation.escrow_position?.total_held_display }}</dd></div>
                        <div class="flex justify-between"><dt>Ledger liability</dt><dd class="font-black">{{ reconciliation.escrow_position?.ledger_liability_display }}</dd></div>
                        <div class="flex justify-between"><dt>Variance</dt><dd :class="reconciliation.escrow_position?.position_matches_ledger ? 'text-emerald-700 font-black' : 'text-rose-700 font-black'">{{ reconciliation.escrow_position?.position_matches_ledger ? '₦0 ✓' : 'Investigate' }}</dd></div>
                        <div class="flex justify-between"><dt>Ledger balanced</dt><dd class="font-black">{{ reconciliation.reconciliation?.ledger_balanced ? 'Yes ✓' : 'No' }}</dd></div>
                    </dl>
                    <div class="mt-6 flex gap-2">
                        <Link :href="route('admin.financial-audit.reconciliation.index')" class="rounded-full bg-primary-600 px-4 py-2 text-xs font-black uppercase text-white">Full report</Link>
                        <button type="button" class="rounded-full border px-4 py-2 text-xs font-black uppercase" @click="reconciliationOpen = false">Close</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import EscrowManagementDetailSlideOver from '@/Components/Admin/EscrowManagementDetailSlideOver.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    dashboard: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const { shell, chartMode, isDark } = useInjectedAdminTheme();
const data = reactive({ ...props.dashboard });
const statuses = props.statuses.length ? props.statuses : (props.dashboard?.statuses ?? []);
const categories = props.categories.length ? props.categories : (props.dashboard?.categories ?? []);

const filters = reactive({
    q: props.dashboard?.listing?.filters?.q ?? '',
    status: props.dashboard?.listing?.filters?.status ?? '',
    category_id: props.dashboard?.listing?.filters?.category_id ?? '',
    quick_view: props.dashboard?.listing?.filters?.quick_view ?? '',
    due_bucket: props.dashboard?.listing?.filters?.due_bucket ?? '',
});

const slideOpen = ref(false);
const selectedRecordId = ref(null);
const reconciliationOpen = ref(false);
const reconciliationLoading = ref(false);
const reconciliation = ref(null);

const quickViews = [
    { key: '', label: 'All' },
    { key: 'due_today', label: 'Due today' },
    { key: 'due_week', label: 'Due this week' },
    { key: 'at_risk', label: 'At risk' },
    { key: 'disputed', label: 'Disputed' },
    { key: 'high_value', label: 'High value' },
    { key: 'pending_review', label: 'Pending review' },
];

let filterTimer;
let pollTimer;

const balanceChartSeries = computed(() => [{
    name: 'Escrow held',
    data: (data.balance_series?.points ?? []).map((p) => p.balance_minor / 100),
}]);

const balanceChartOptions = computed(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', foreColor: isDark.value ? '#94a3b8' : '#64748b' },
    theme: { mode: chartMode.value },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
    colors: ['#0d9488'],
    xaxis: { categories: (data.balance_series?.points ?? []).map((p) => p.label) },
    yaxis: { labels: { formatter: (v) => `₦${Number(v).toLocaleString()}` } },
    tooltip: { theme: chartMode.value, y: { formatter: (v) => `₦${Number(v).toLocaleString()}` } },
}));

const waterfallChartSeries = computed(() => [{
    name: 'Amount',
    data: (data.release_waterfall?.segments ?? []).map((s) => s.amount_minor / 100),
}]);

const waterfallChartOptions = computed(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', events: { dataPointSelection: () => {} } },
    theme: { mode: chartMode.value },
    plotOptions: { bar: { borderRadius: 6, columnWidth: '55%', distributed: true } },
    colors: ['#f59e0b', '#f97316', '#fb923c', '#94a3b8'],
    xaxis: { categories: (data.release_waterfall?.segments ?? []).map((s) => s.label) },
    yaxis: { labels: { formatter: (v) => `₦${Number(v).toLocaleString()}` } },
    legend: { show: false },
    tooltip: { theme: chartMode.value },
}));

function healthBadgeClass(status) {
    if (status === 'critical') return 'bg-rose-100 text-rose-800';
    if (status === 'warning') return 'bg-amber-100 text-amber-900';
    return 'bg-emerald-100 text-emerald-800';
}

function gaugeStatusClass(status) {
    if (status === 'excellent') return 'text-emerald-700';
    if (status === 'good') return 'text-sky-700';
    if (status === 'monitor') return 'text-amber-700';
    return 'text-rose-700';
}

function gaugeBarClass(status) {
    if (status === 'excellent') return 'bg-emerald-500';
    if (status === 'good') return 'bg-sky-500';
    if (status === 'monitor') return 'bg-amber-500';
    return 'bg-rose-500';
}

function statusPillClass(row) {
    if (row.status === 'disputed') return 'bg-rose-100 text-rose-800';
    if (row.status === 'released') return 'bg-slate-100 text-slate-600';
    if (row.status === 'held') return 'bg-emerald-100 text-emerald-800';
    return 'bg-slate-100 text-slate-700';
}

function urgencyClass(urgency) {
    if (urgency === 'urgent') return 'text-rose-700';
    if (urgency === 'warning') return 'text-amber-700';
    return '';
}

function clean(obj) {
    const out = {};
    Object.entries(obj).forEach(([k, v]) => {
        if (v !== '' && v != null) out[k] = v;
    });
    return out;
}

function applyFilters() {
    router.get(route('admin.escrow-management.index'), clean(filters), { preserveScroll: true, preserveState: true });
}

function debouncedApply() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFilters, 300);
}

function applyQuickView(key) {
    filters.quick_view = key;
    filters.due_bucket = '';
    applyFilters();
}

function applyDueBucket(key) {
    filters.due_bucket = key;
    filters.quick_view = '';
    applyFilters();
}

function onAlertClick(alert) {
    if (alert.href) {
        router.visit(alert.href);
        return;
    }
    if (alert.filter) applyQuickView(alert.filter);
}

function openRecord(row) {
    selectedRecordId.value = row.id;
    slideOpen.value = true;
}

async function refreshDashboard() {
    const { data: payload } = await window.axios.get(route('admin.escrow-management.api.dashboard'), { params: clean(filters) });
    Object.assign(data, payload);
}

async function generateReconciliation() {
    reconciliationOpen.value = true;
    reconciliationLoading.value = true;
    try {
        const { data: payload } = await window.axios.get(route('admin.escrow-management.api.reconciliation'));
        reconciliation.value = payload;
    } finally {
        reconciliationLoading.value = false;
    }
}

onMounted(() => {
    pollTimer = setInterval(refreshDashboard, 90000);
});

onUnmounted(() => {
    clearInterval(pollTimer);
});
</script>
