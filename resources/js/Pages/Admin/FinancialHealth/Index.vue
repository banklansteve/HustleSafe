<template>
    <AdminShell
        title="Financial health"
        subtitle="Real-time escrow, revenue, VAT, and payment oversight — alert-driven with drill-down into issues."
    >
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-end gap-2">
                    <button
                        v-for="preset in period_presets"
                        :key="preset.key"
                        type="button"
                        class="rounded-xl px-3 py-2 text-xs font-black uppercase transition"
                        :class="period === preset.key ? shell.btnPrimary : shell.btnGhost"
                        @click="setPeriod(preset.key)"
                    >
                        {{ preset.label }}
                    </button>
                    <template v-if="period === 'custom'">
                        <AdminDateInput v-model="customFrom" wrapper-class="" @change="applyCustomRange" />
                        <span class="text-xs font-bold" :class="shell.cardMuted">to</span>
                        <AdminDateInput v-model="customTo" wrapper-class="" @change="applyCustomRange" />
                    </template>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold" :class="shell.cardMuted">
                    <span>Updated {{ formatWhen(generated_at) }}</span>
                    <button type="button" class="rounded-lg border px-3 py-1.5 font-black uppercase" :class="shell.btnGhost" @click="refreshSnapshot">
                        Refresh
                    </button>
                    <a :href="links.export_csv" class="rounded-lg border px-3 py-1.5 font-black uppercase" :class="shell.btnGhost">Export CSV</a>
                    <a :href="links.export_pdf" class="rounded-lg border px-3 py-1.5 font-black uppercase" :class="shell.btnGhost">Export PDF</a>
                </div>
            </div>

            <section v-if="liveAlerts.items?.length" class="rounded-2xl border p-4" :class="alertsPanelClass">
                <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="liveAlerts.has_critical ? 'text-rose-800' : 'text-amber-900'">
                    {{ liveAlerts.has_critical ? 'Critical alerts' : 'Alerts & flags' }}
                </p>
                <ul class="mt-3 space-y-2">
                    <li
                        v-for="alert in liveAlerts.items"
                        :key="alert.key + alert.severity"
                        class="flex flex-wrap items-center justify-between gap-2 text-sm font-semibold"
                    >
                        <span>{{ alert.message }}</span>
                        <Link
                            v-if="alert.action_url"
                            :href="alert.action_url"
                            class="text-xs font-black uppercase underline"
                        >
                            {{ alert.action_label }}
                        </Link>
                    </li>
                </ul>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link :href="links.reconcile" class="rounded-full bg-slate-900 px-3 py-1.5 text-[10px] font-black uppercase text-white">Reconcile now</Link>
                    <Link :href="links.vat_report" class="rounded-full border px-3 py-1.5 text-[10px] font-black uppercase">Schedule payment</Link>
                    <Link :href="links.exceptions" class="rounded-full border px-3 py-1.5 text-[10px] font-black uppercase">View details</Link>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <AdminKpiTile
                    label="Escrow funded"
                    :value="kpis.escrow_funded.total_display"
                    :hint="`${kpis.escrow_funded.count ?? 0} ${kpis.escrow_funded.count_label ?? 'contracts'}`"
                    :trend="trendLabel(kpis.escrow_funded)"
                    :trend-positive="kpis.escrow_funded.trend_direction !== 'down'"
                />
                <AdminKpiTile
                    label="Platform fee generated"
                    :value="kpis.platform_fee.total_display"
                    :hint="`${kpis.platform_fee.count ?? 0} ${kpis.platform_fee.count_label ?? 'transactions'}`"
                    :trend="trendLabel(kpis.platform_fee)"
                    :trend-positive="kpis.platform_fee.trend_direction !== 'down'"
                />
                <AdminKpiTile
                    label="VAT collected"
                    :value="kpis.vat_collected.total_display"
                    :hint="`Remittable: ${kpis.vat_collected.remittable_display} · ${kpis.vat_collected.remittance_status}`"
                    :trend="trendLabel(kpis.vat_collected)"
                    :trend-positive="kpis.vat_collected.trend_direction !== 'down'"
                />
                <AdminKpiTile
                    label="Net revenue"
                    :value="kpis.net_revenue.total_display"
                    :hint="budgetHint"
                    :trend="trendLabel(kpis.net_revenue)"
                    :trend-positive="kpis.net_revenue.trend_direction !== 'down'"
                />
            </section>

            <section>
                <p class="mb-3 text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.label">Payment obligations</p>
                <div class="grid gap-3 md:grid-cols-3">
                    <div
                        v-for="card in paymentCards"
                        :key="card.key"
                        class="rounded-2xl border p-4"
                        :class="shell.card"
                    >
                        <p class="text-[10px] font-black uppercase tracking-wide" :class="shell.label">{{ card.title }}</p>
                        <p class="mt-2 text-xl font-black" :class="shell.title">{{ card.amount }}</p>
                        <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">{{ card.count }} payments</p>
                        <p class="mt-2 text-xs font-bold" :class="shell.cardTitle">{{ card.status }}</p>
                        <p v-if="card.extra" class="mt-1 text-xs font-semibold text-amber-700">{{ card.extra }}</p>
                    </div>
                </div>
            </section>

            <section class="space-y-4 rounded-2xl border p-4 sm:p-5" :class="shell.card">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.label">Charts & trends</p>
                        <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">
                            {{ charts.meta?.range_label }} · {{ charts.meta?.grain_label }}
                            <span v-if="charts.meta?.state_label"> · {{ charts.meta.state_label }}</span>
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="grain in charts.granularity_presets"
                                :key="grain.key"
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 text-[10px] font-black uppercase"
                                :class="chartGrain === grain.key ? shell.btnPrimary : shell.btnGhost"
                                @click="setChartGrain(grain.key)"
                            >
                                {{ grain.label }}
                            </button>
                        </div>
                        <select
                            v-model="stateFilter"
                            class="min-w-[10rem] rounded-xl border px-3 py-2 text-xs font-bold"
                            :class="shell.input"
                            @change="applyChartFilters"
                        >
                            <option v-for="state in states" :key="state.key" :value="state.key">{{ state.label }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <AdminPanel eyebrow="Escrow" :title="`Escrow funding · ${charts.escrow_funding?.total_display ?? '—'}`">
                <VueApexCharts type="area" :height="chartHeight" :options="lineChartOptions(charts.escrow_funding)" :series="charts.escrow_funding?.series ?? []" />
            </AdminPanel>

            <AdminPanel eyebrow="Revenue stream" :title="`Platform fees · ${charts.platform_fee?.total_display ?? '—'}`">
                <VueApexCharts type="area" :height="chartHeight" :options="lineChartOptions(charts.platform_fee, ['#059669'])" :series="charts.platform_fee?.series ?? []" />
            </AdminPanel>

            <AdminPanel eyebrow="Revenue stream" :title="`Quest boosts · ${charts.quest_boost?.total_display ?? '—'}`">
                <VueApexCharts type="area" :height="chartHeight" :options="lineChartOptions(charts.quest_boost, ['#7c3aed'])" :series="charts.quest_boost?.series ?? []" />
            </AdminPanel>

            <AdminPanel eyebrow="Revenue stream" :title="`Premium subscriptions · ${charts.premium_subscription?.total_display ?? '—'}`">
                <VueApexCharts type="area" :height="chartHeight" :options="lineChartOptions(charts.premium_subscription, ['#2563eb'])" :series="charts.premium_subscription?.series ?? []" />
            </AdminPanel>

            <AdminPanel eyebrow="Overall" :title="`Total platform revenue · ${charts.overall_revenue?.total_display ?? '—'}`">
                <p class="-mt-1 mb-3 text-xs font-semibold" :class="shell.cardMuted">Compare quest boosts, premium subscriptions, and platform fees in one view.</p>
                <VueApexCharts type="line" :height="chartHeight" :options="overallRevenueOptions" :series="charts.overall_revenue?.series ?? []" />
            </AdminPanel>

            <AdminPanel eyebrow="Tax" :title="`VAT collected · ${charts.vat_collected?.total_display ?? '—'}`">
                <VueApexCharts type="area" :height="chartHeight" :options="lineChartOptions(charts.vat_collected, ['#0ea5e9'])" :series="charts.vat_collected?.series ?? []" />
            </AdminPanel>

            <AdminPanel eyebrow="Releases" title="Payment release status">
                <div class="grid gap-4 md:grid-cols-[12rem_1fr]">
                    <div class="text-center md:text-left">
                        <p class="text-[10px] font-black uppercase text-slate-500">{{ charts.release_status?.center_label }}</p>
                        <p class="mt-1 text-2xl font-black text-emerald-700">{{ charts.release_status?.center_display }}</p>
                    </div>
                    <VueApexCharts
                        type="donut"
                        :height="donutHeight"
                        :options="releaseStatusOptions"
                        :series="charts.release_status?.series ?? []"
                    />
                </div>
            </AdminPanel>

            <AdminPanel eyebrow="Transactions" title="Detailed ledger">
                <div class="mb-4 flex flex-wrap gap-2">
                    <select v-model="txnStatus" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="applyTxnFilters">
                        <option value="all">All statuses</option>
                        <option value="funded">Funded</option>
                        <option value="released">Released</option>
                        <option value="awaiting">Awaiting</option>
                        <option value="overdue">Overdue</option>
                        <option value="on_hold">On hold</option>
                    </select>
                    <select v-model="txnType" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="applyTxnFilters">
                        <option value="all">All types</option>
                        <option value="escrow">Escrow</option>
                        <option value="vat">VAT</option>
                    </select>
                    <select v-model="txnDirection" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="applyTxnFilters">
                        <option value="all">All directions</option>
                        <option value="inflow">Inflow</option>
                        <option value="outflow">Outflow</option>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b text-[10px] font-black uppercase" :class="shell.tableDivide">
                                <th class="px-3 py-2">Transaction ID</th>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Amount</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Due date</th>
                                <th class="px-3 py-2">Days overdue</th>
                                <th class="px-3 py-2">Direction</th>
                                <th class="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in transactions.data" :key="row.id + row.type" class="border-b" :class="shell.tableDivide">
                                <td class="px-3 py-2 font-mono text-xs font-bold">{{ row.id }}</td>
                                <td class="px-3 py-2">{{ row.type_label }}</td>
                                <td class="px-3 py-2 font-black">{{ row.amount_display }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.status_tone)">
                                        {{ row.status_label }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ row.due_date || '—' }}</td>
                                <td class="px-3 py-2">{{ row.days_overdue ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs font-bold uppercase">{{ row.direction }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        <Link v-if="row.action_url" :href="row.action_url" class="text-[10px] font-black uppercase text-primary-700 underline">
                                            {{ row.action_label }}
                                        </Link>
                                        <button
                                            v-if="row.can_hold"
                                            type="button"
                                            class="text-[10px] font-black uppercase text-amber-800 underline"
                                            @click="openAction('hold', row)"
                                        >
                                            Hold
                                        </button>
                                        <button
                                            v-if="row.can_lift_hold"
                                            type="button"
                                            class="text-[10px] font-black uppercase text-emerald-800 underline"
                                            @click="openAction('lift_hold', row)"
                                        >
                                            Unhold
                                        </button>
                                        <button
                                            v-if="row.can_investigate"
                                            type="button"
                                            class="text-[10px] font-black uppercase text-rose-700 underline"
                                            @click="openAction('investigate', row)"
                                        >
                                            Investigate
                                        </button>
                                        <button
                                            v-if="row.routes?.note"
                                            type="button"
                                            class="text-[10px] font-black uppercase text-slate-600 underline"
                                            @click="openAction('note', row)"
                                        >
                                            Note
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs font-semibold" :class="shell.cardMuted">
                    Page {{ transactions.meta.current_page }} of {{ transactions.meta.last_page }} · {{ transactions.meta.total }} rows
                </p>
                <div v-if="transactions.meta.last_page > 1" class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-xs font-black uppercase disabled:opacity-40"
                        :class="shell.btnGhost"
                        :disabled="transactions.meta.current_page <= 1"
                        @click="goToPage(transactions.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-xs font-black uppercase disabled:opacity-40"
                        :class="shell.btnGhost"
                        :disabled="transactions.meta.current_page >= transactions.meta.last_page"
                        @click="goToPage(transactions.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </AdminPanel>

            <details class="rounded-2xl border p-4" :class="shell.card">
                <summary class="cursor-pointer text-sm font-black uppercase tracking-wide">Financial reconciliation</summary>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-xl border p-4 font-mono text-xs" :class="shell.card">
                        <p class="font-black uppercase">Escrow reconciliation</p>
                        <p class="mt-2">System escrow held: {{ reconciliation.escrow.system_held_display }}</p>
                        <p>Bank / ledger balance: {{ reconciliation.escrow.bank_balance_display }}</p>
                        <p :class="reconciliation.escrow.balanced ? 'text-emerald-700' : 'text-rose-700'">
                            Variance: {{ reconciliation.escrow.variance_display }}
                            {{ reconciliation.escrow.balanced ? '✅ BALANCED' : '⚠️ REVIEW' }}
                        </p>
                        <p class="mt-2 text-slate-500">Last reconciled: {{ reconciliation.escrow.last_reconciled_at || '—' }}</p>
                        <div class="mt-3 flex gap-2">
                            <Link :href="links.reconcile" class="rounded-lg bg-slate-900 px-3 py-1.5 text-[10px] font-black uppercase text-white">Reconcile now</Link>
                            <Link :href="links.ledger" class="rounded-lg border px-3 py-1.5 text-[10px] font-black uppercase">View ledger</Link>
                        </div>
                    </div>
                    <div class="rounded-xl border p-4 font-mono text-xs" :class="shell.card">
                        <p class="font-black uppercase">VAT liability</p>
                        <p class="mt-2">VAT collected ({{ reconciliation.vat.period_label }}): {{ reconciliation.vat.collected_display }}</p>
                        <p>VAT remitted to NRS: {{ reconciliation.vat.remitted_display }}</p>
                        <p>Outstanding VAT: {{ reconciliation.vat.outstanding_display }}</p>
                        <p>Remittance deadline: {{ reconciliation.vat.deadline_label || '—' }}</p>
                        <div class="mt-3 flex gap-2">
                            <Link :href="links.vat_report" class="rounded-lg bg-slate-900 px-3 py-1.5 text-[10px] font-black uppercase text-white">Schedule remittance</Link>
                        </div>
                    </div>
                </div>
            </details>
        </div>

        <div
            v-if="actionModal.open"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
            @click.self="closeActionModal"
        >
            <div class="w-full max-w-md rounded-2xl border bg-white p-5 shadow-xl dark:bg-slate-900" :class="shell.card">
                <p class="text-sm font-black uppercase">{{ actionModal.title }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-500">{{ actionModal.row?.id }}</p>
                <textarea
                    v-model="actionModal.text"
                    rows="4"
                    class="mt-3 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
                    :class="shell.input"
                    :placeholder="actionModal.placeholder"
                />
                <p v-if="actionError" class="mt-2 text-xs font-bold text-rose-600">{{ actionError }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-3 py-2 text-xs font-black uppercase" :class="shell.btnGhost" @click="closeActionModal">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-black uppercase text-white disabled:opacity-50"
                        :disabled="actionSubmitting"
                        @click="submitAction"
                    >
                        {{ actionSubmitting ? 'Saving…' : 'Confirm' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminKpiTile from '@/Components/Admin/AdminKpiTile.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    period: { type: String, required: true },
    period_label: { type: String, required: true },
    date_from: { type: String, required: true },
    date_to: { type: String, required: true },
    period_presets: { type: Array, required: true },
    generated_at: { type: String, required: true },
    cache: { type: Object, required: true },
    kpis: { type: Object, required: true },
    alerts: { type: Object, required: true },
    payment_status: { type: Object, required: true },
    charts: { type: Object, required: true },
    chart_grain: { type: String, required: true },
    state_id: { type: String, required: true },
    states: { type: Array, required: true },
    transactions: { type: Object, required: true },
    reconciliation: { type: Object, required: true },
    links: { type: Object, required: true },
});

const { shell } = useInjectedAdminTheme();
const liveAlerts = reactive({ ...props.alerts });
const txnStatus = ref(props.transactions.filters?.status ?? 'all');
const txnType = ref(props.transactions.filters?.type ?? 'all');
const txnDirection = ref(props.transactions.filters?.direction ?? 'all');
const customFrom = ref(props.date_from);
const customTo = ref(props.date_to);
const chartGrain = ref(props.chart_grain);
const stateFilter = ref(props.state_id);
const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const actionModal = reactive({ open: false, kind: null, row: null, text: '', title: '', placeholder: '' });
const actionSubmitting = ref(false);
const actionError = ref('');
let pollTimer = null;

function handleResize() {
    viewportWidth.value = window.innerWidth;
}

const chartHeight = computed(() => (viewportWidth.value < 640 ? 260 : 320));
const donutHeight = computed(() => (viewportWidth.value < 640 ? 260 : 300));

const budgetHint = computed(() => {
    const n = props.kpis.net_revenue;
    if (n.budget_variance_minor == null) {
        return `After processor costs (${n.processor_costs_display})`;
    }

    const sign = n.budget_on_track ? '+' : '−';

    return `vs budget: ${sign}${n.budget_variance_display}`;
});

const paymentCards = computed(() => {
    const map = [
        { key: 'today', title: 'Payment due today' },
        { key: 'next_7_days', title: 'Payment due next 7 days' },
        { key: 'month', title: 'Payment due this month' },
    ];

    return map.map(({ key, title }) => {
        const p = props.payment_status[key] || {};

        return {
            key,
            title,
            amount: p.amount_display ?? '—',
            count: p.count ?? 0,
            status: p.status_summary ?? '—',
            extra: p.at_risk_count > 0 ? `${p.at_risk_count} at risk (auto-release pending)` : null,
        };
    });
});

function lineChartOptions(chart, colors = ['#059669', '#2563eb', '#94a3b8']) {
    return {
        chart: { toolbar: { show: false }, fontFamily: 'inherit', zoom: { enabled: false } },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
        xaxis: {
            categories: chart?.labels ?? [],
            labels: {
                rotate: -45,
                rotateAlways: viewportWidth.value < 640,
                style: { fontSize: '10px' },
            },
        },
        yaxis: { labels: { formatter: (v) => `₦${Number(v).toLocaleString()}` } },
        colors,
        legend: { position: 'top', horizontalAlign: 'left', fontSize: '11px' },
        dataLabels: { enabled: false },
        grid: { padding: { left: 8, right: 8 } },
    };
}

const overallRevenueOptions = computed(() => lineChartOptions(props.charts.overall_revenue, ['#7c3aed', '#2563eb', '#059669', '#0f172a']));

const alertsPanelClass = computed(() => (
    liveAlerts.has_critical
        ? 'border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30'
        : 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/20'
));

const releaseStatusOptions = computed(() => ({
    chart: {
        fontFamily: 'inherit',
        events: {
            dataPointSelection: (_event, _chartContext, config) => {
                onReleaseSegmentClick(_event, _chartContext, config);
            },
        },
    },
    labels: props.charts.release_status?.labels ?? [],
    colors: props.charts.release_status?.colors ?? [],
    legend: { position: 'bottom', fontSize: '11px' },
}));

const releaseSegmentFilters = ['released', 'awaiting', 'on_hold', 'overdue'];

function queryParams(page = 1) {
    const params = {
        period: props.period,
        page,
        chart_grain: chartGrain.value,
        state_id: stateFilter.value,
        txn_status: txnStatus.value,
        txn_type: txnType.value,
        txn_direction: txnDirection.value,
    };

    if (props.period === 'custom') {
        params.date_from = customFrom.value;
        params.date_to = customTo.value;
    }

    return params;
}

function onReleaseSegmentClick(_event, _chartContext, config) {
    const index = config?.dataPointIndex ?? config?.seriesIndex;
    const status = releaseSegmentFilters[index];
    if (!status) {
        return;
    }

    txnStatus.value = status;
    applyTxnFilters();
}

function goToPage(page) {
    router.get(route('admin.financial-health.index'), queryParams(page), { preserveState: true, replace: true });
}

function trendLabel(kpi) {
    if (!kpi?.trend_delta_minor) {
        return '';
    }

    const arrow = kpi.trend_direction === 'up' ? '↑' : kpi.trend_direction === 'down' ? '↓' : '→';

    return `${arrow} ${kpi.trend_delta_display}`;
}

function statusClass(tone) {
    const map = {
        emerald: 'bg-emerald-100 text-emerald-800',
        amber: 'bg-amber-100 text-amber-900',
        rose: 'bg-rose-100 text-rose-800',
        orange: 'bg-orange-100 text-orange-900',
    };

    return map[tone] || 'bg-slate-100 text-slate-800';
}

function setPeriod(key) {
    const params = {
        period: key,
        chart_grain: chartGrain.value,
        state_id: stateFilter.value,
        txn_status: txnStatus.value,
        txn_type: txnType.value,
        txn_direction: txnDirection.value,
    };

    if (key === 'custom') {
        params.date_from = customFrom.value;
        params.date_to = customTo.value;
    }

    router.get(route('admin.financial-health.index'), params, { preserveState: true, replace: true });
}

function applyChartFilters() {
    router.get(route('admin.financial-health.index'), queryParams(1), { preserveState: true, replace: true });
}

function setChartGrain(key) {
    chartGrain.value = key;
    applyChartFilters();
}

function applyCustomRange() {
    if (props.period !== 'custom' || !customFrom.value || !customTo.value) {
        return;
    }

    router.get(route('admin.financial-health.index'), queryParams(1), { preserveState: true, replace: true });
}

function applyTxnFilters(page = 1) {
    router.get(route('admin.financial-health.index'), queryParams(page), { preserveState: true, replace: true });
}

function openAction(kind, row) {
    actionModal.open = true;
    actionModal.kind = kind;
    actionModal.row = row;
    actionModal.text = '';
    actionError.value = '';

    const titles = {
        hold: 'Hold payment',
        lift_hold: 'Lift hold',
        investigate: 'Investigate transaction',
        note: 'Add note',
    };

    const placeholders = {
        hold: 'Reason for hold (min 10 characters)…',
        lift_hold: 'Reason for lifting hold (min 10 characters)…',
        investigate: 'What should be investigated? (min 10 characters)…',
        note: 'Internal note for audit trail…',
    };

    actionModal.title = titles[kind] ?? 'Action';
    actionModal.placeholder = placeholders[kind] ?? '';
}

function closeActionModal() {
    actionModal.open = false;
    actionModal.kind = null;
    actionModal.row = null;
    actionModal.text = '';
    actionError.value = '';
}

async function submitAction() {
    const row = actionModal.row;
    if (!row?.routes) {
        return;
    }

    actionSubmitting.value = true;
    actionError.value = '';

    const routes = {
        hold: row.routes.hold,
        lift_hold: row.routes.lift_hold,
        investigate: row.routes.investigate,
        note: row.routes.note,
    };

    const payloads = {
        hold: { reason: actionModal.text, hold_until: null },
        lift_hold: { reason: actionModal.text },
        investigate: { reason: actionModal.text },
        note: { note: actionModal.text },
    };

    try {
        await window.axios.post(routes[actionModal.kind], payloads[actionModal.kind]);
        closeActionModal();
        router.reload({ only: ['transactions', 'alerts', 'payment_status'] });
    } catch (error) {
        actionError.value = error?.response?.data?.message
            ?? Object.values(error?.response?.data?.errors ?? {})?.[0]?.[0]
            ?? 'Action failed. Please try again.';
    } finally {
        actionSubmitting.value = false;
    }
}

async function refreshSnapshot() {
    try {
        const params = { period: props.period };
        if (props.period === 'custom') {
            params.date_from = props.date_from;
            params.date_to = props.date_to;
        }
        const { data } = await window.axios.get(route('admin.financial-health.api.snapshot'), { params });
        Object.assign(liveAlerts, data.alerts);
    } catch {
        /* ignore */
    }
}

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString('en-NG', { timeZone: 'Africa/Lagos' });
    } catch {
        return '';
    }
}

onMounted(() => {
    const ms = (props.cache?.metrics_ttl_seconds ?? 300) * 1000;
    pollTimer = window.setInterval(refreshSnapshot, ms);
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
    window.removeEventListener('resize', handleResize);
});
</script>
