<template>
    <AdminShell
        title="Revenue monitor"
        subtitle="Unified view of quest boost, premium membership, and platform fee revenue — trends, breakdowns, and transaction-level audit."
    >
        <div class="space-y-6">
            <div class="flex flex-wrap items-end gap-2">
                <button
                    v-for="preset in presets"
                    :key="preset.key"
                    type="button"
                    class="rounded-xl px-3 py-2 text-xs font-black uppercase transition"
                    :class="period.preset === preset.key ? shell.btnPrimary : shell.btnGhost"
                    @click="setPreset(preset.key)"
                >
                    {{ preset.label }}
                </button>
                <template v-if="period.preset === 'custom'">
                    <AdminDateInput v-model="customFrom" wrapper-class="" @change="applyCustom" />
                    <span class="text-xs font-bold" :class="shell.cardMuted">to</span>
                    <AdminDateInput v-model="customTo" wrapper-class="" @change="applyCustom" />
                </template>
                <div class="ml-auto flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-xl border px-4 py-2 text-xs font-black uppercase"
                    :class="shell.btnGhost"
                    @click="exportAllChartsPng"
                >
                    Export charts PNG
                </button>
                <a
                    :href="exportCsvUrl"
                    class="rounded-xl border px-4 py-2 text-xs font-black uppercase"
                    :class="shell.btnGhost"
                >
                    Export CSV
                </a>
                <a
                    :href="exportPdfUrl"
                    class="rounded-xl border px-4 py-2 text-xs font-black uppercase"
                    :class="shell.btnGhost"
                >
                    Export PDF
                </a>
                </div>
            </div>

            <section class="rounded-[2rem] border p-5 sm:p-6" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.label">
                    Revenue dashboard — {{ period.label }}
                </p>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500">Total revenue</p>
                        <p class="mt-1 text-3xl font-black" :class="shell.title">{{ overview.total_gross_display }}</p>
                        <p class="mt-1 text-sm font-bold" :class="growthClass(overview.growth_percent)">
                            {{ formatGrowth(overview.growth_percent) }} vs prior period
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500">Net revenue</p>
                        <p class="mt-1 text-3xl font-black text-emerald-700">{{ overview.total_net_display }}</p>
                        <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">After processor fees & refunds</p>
                    </div>
                </div>
                <div class="mt-5 space-y-2 border-t pt-4" :class="shell.tableDivide">
                    <div v-for="stream in overview.streams" :key="stream.key" class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="font-bold" :class="shell.cardTitle">{{ stream.label }}</span>
                        <span class="font-black">{{ stream.amount_display }} <span class="text-xs font-bold text-slate-500">({{ stream.percent }}%)</span></span>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-4 border-t pt-4 text-xs font-bold uppercase" :class="shell.tableDivide">
                    <span>Active premium users: <strong>{{ overview.active_premium_users }}</strong></span>
                    <span>Active boosted quests: <strong>{{ overview.active_boosted_quests }}</strong></span>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_18rem]">
                <div class="space-y-5">
                    <AdminPanel eyebrow="Chart 1" title="Revenue trends">
                        <div class="mb-3 flex flex-wrap gap-3 text-xs font-bold" :class="shell.cardMuted">
                            <span>Daily avg: {{ trend_insights.daily_average_display }}</span>
                            <span v-if="trend_insights.peak_day">Peak: {{ trend_insights.peak_amount_display }} on {{ trend_insights.peak_day }}</span>
                            <span>Trend: {{ trendDirectionLabel }}</span>
                        </div>
                        <VueApexCharts ref="trendChartRef" type="line" height="320" :options="trendChartOptions" :series="trendChartSeries" />
                    </AdminPanel>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <AdminPanel eyebrow="Chart 2" title="Revenue breakdown">
                            <VueApexCharts ref="breakdownChartRef" type="donut" height="300" :options="breakdownChartOptions" :series="breakdownChartSeries" />
                        </AdminPanel>
                        <AdminPanel eyebrow="Chart 3" title="Premium cohort">
                            <VueApexCharts ref="cohortChartRef" type="bar" height="300" :options="cohortChartOptions" :series="cohortChartSeries" />
                        </AdminPanel>
                    </div>

                    <AdminPanel eyebrow="Transactions" title="Transaction-level details">
                        <div class="mb-4 flex flex-wrap gap-2">
                            <select v-model="filters.revenue_type" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="reloadTable">
                                <option value="">All types</option>
                                <option value="premium">Premium</option>
                                <option value="quest_boost">Boost</option>
                                <option value="platform_fee">Platform fee</option>
                            </select>
                            <select v-model="filters.status" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="reloadTable">
                                <option value="">All statuses</option>
                                <option value="paid">Paid</option>
                                <option value="earned">Earned</option>
                                <option value="refunded">Refunded</option>
                                <option value="pending">Pending</option>
                            </select>
                            <input v-model="filters.q" type="search" placeholder="Search user or reference…" class="min-w-[12rem] flex-1 rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="reloadTable" />
                            <select v-model="filters.sort" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="reloadTable">
                                <option value="date">Date</option>
                                <option value="amount">Amount</option>
                                <option value="type">Type</option>
                            </select>
                        </div>

                        <div class="overflow-x-auto rounded-2xl border" :class="shell.card">
                            <table class="min-w-full text-left text-sm">
                                <thead class="border-b text-[10px] font-black uppercase tracking-wider" :class="shell.tableDivide">
                                    <tr>
                                        <th class="px-3 py-3">Type</th>
                                        <th class="px-3 py-3">Txn ID</th>
                                        <th class="px-3 py-3">Party</th>
                                        <th class="px-3 py-3">Date</th>
                                        <th class="px-3 py-3">Amount</th>
                                        <th class="px-3 py-3">Net</th>
                                        <th class="px-3 py-3">Status</th>
                                        <th class="px-3 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="row in transactions.items" :key="`${row.revenue_type}-${row.id}`">
                                        <tr class="border-b" :class="shell.tableDivide">
                                            <td class="px-3 py-3 text-xs font-black uppercase">{{ row.revenue_type_label }}</td>
                                            <td class="px-3 py-3 font-mono text-xs">{{ row.transaction_id }}</td>
                                            <td class="px-3 py-3 text-xs">{{ row.party_label }}</td>
                                            <td class="px-3 py-3 text-xs" :class="shell.cardMuted">{{ row.date }}</td>
                                            <td class="px-3 py-3 font-black">{{ row.amount_display }}</td>
                                            <td class="px-3 py-3 font-black text-emerald-700">{{ row.net_display }}</td>
                                            <td class="px-3 py-3 text-xs font-black uppercase">{{ row.status_label }}</td>
                                            <td class="px-3 py-3">
                                                <button type="button" class="text-xs font-black text-primary-700" @click="toggleDetail(row)">
                                                    {{ expandedKey === detailKey(row) ? 'Hide' : 'View' }}
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="expandedKey === detailKey(row) && rowDetail" class="bg-slate-50/80 dark:bg-slate-900/30">
                                            <td colspan="8" class="px-4 py-4">
                                                <div v-if="detailLoading" class="text-sm font-semibold text-slate-500">Loading detail…</div>
                                                <div v-else class="grid gap-2 text-sm">
                                                    <template v-if="rowDetail.type === 'premium'">
                                                        <p><strong>Subscriber:</strong> @{{ rowDetail.subscriber?.username }} (Tier {{ rowDetail.subscriber?.verification_tier }})</p>
                                                        <p><strong>Plan:</strong> {{ rowDetail.plan }}</p>
                                                        <p><strong>Charge:</strong> {{ rowDetail.charge_display }}</p>
                                                        <p><strong>Processor fee:</strong> {{ rowDetail.processor_fee_display }}</p>
                                                        <p><strong>Net:</strong> {{ rowDetail.net_display }}</p>
                                                        <p><strong>Renewal:</strong> {{ rowDetail.renewal_date || '—' }}</p>
                                                    </template>
                                                    <template v-else-if="rowDetail.type === 'boost'">
                                                        <p><strong>Quest:</strong> {{ rowDetail.quest?.title }} ({{ rowDetail.quest?.reference_code }})</p>
                                                        <p><strong>Client:</strong> @{{ rowDetail.client?.username }}</p>
                                                        <p><strong>Tier:</strong> {{ rowDetail.tier }}</p>
                                                        <p><strong>Period:</strong> {{ rowDetail.boost_period || '—' }}</p>
                                                        <p><strong>Proposals:</strong> {{ rowDetail.proposals_received }}</p>
                                                        <p><strong>Net:</strong> {{ rowDetail.net_display }}</p>
                                                    </template>
                                                    <template v-else-if="rowDetail.type === 'platform_fee'">
                                                        <p><strong>Quest:</strong> {{ rowDetail.quest?.title }}</p>
                                                        <p><strong>Contract value:</strong> {{ rowDetail.contract_value_display }}</p>
                                                        <p><strong>Platform fee:</strong> {{ rowDetail.platform_fee_display }} ({{ rowDetail.fee_percent }}%)</p>
                                                        <p><strong>Earned:</strong> {{ formatWhen(rowDetail.earned_at) }}</p>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-xs font-semibold" :class="shell.cardMuted">
                            Showing {{ transactions.items.length }} of {{ transactions.meta.total }} transactions
                        </p>
                    </AdminPanel>
                </div>

                <aside class="space-y-4">
                    <AdminPanel eyebrow="Key metrics" title="This period">
                        <div class="space-y-4 text-sm">
                            <div>
                                <p class="text-[10px] font-black uppercase text-slate-500">Average transaction</p>
                                <p class="mt-1 font-bold">Premium: {{ sidebar.avg_transaction.premium_display }}</p>
                                <p class="font-bold">Boosts: {{ sidebar.avg_transaction.boost_display }}</p>
                                <p class="font-bold">Platform fee: {{ sidebar.avg_transaction.platform_fee_display }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase text-slate-500">Growth rate</p>
                                <p class="mt-1 font-bold" :class="growthClass(sidebar.growth.premium_percent)">Premium: {{ formatGrowth(sidebar.growth.premium_percent) }}</p>
                                <p class="font-bold" :class="growthClass(sidebar.growth.boost_percent)">Boosts: {{ formatGrowth(sidebar.growth.boost_percent) }}</p>
                                <p class="font-bold" :class="growthClass(sidebar.growth.platform_percent)">Platform: {{ formatGrowth(sidebar.growth.platform_percent) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase text-slate-500">Churn & refunds</p>
                                <p class="mt-1 font-bold">Premium churn: {{ sidebar.churn_rate_percent }}%</p>
                                <p class="font-bold">Premium refunds: {{ sidebar.refund_rates.premium_percent }}%</p>
                                <p class="font-bold">Boost refunds: {{ sidebar.refund_rates.boost_percent }}%</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase text-slate-500">Boost duration</p>
                                <p class="mt-1 font-bold">Mean: {{ sidebar.avg_boost_duration_days }} days</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase text-slate-500">Premium LTV estimate</p>
                                <p class="mt-1 text-lg font-black text-emerald-700">{{ sidebar.estimated_ltv_display }}</p>
                                <p class="text-xs font-semibold text-slate-500">{{ sidebar.new_premium_users }} new payers this period</p>
                            </div>
                        </div>
                    </AdminPanel>
                </aside>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    period: { type: Object, required: true },
    presets: { type: Array, required: true },
    overview: { type: Object, required: true },
    trend: { type: Object, required: true },
    breakdown: { type: Object, required: true },
    cohort: { type: Object, required: true },
    sidebar: { type: Object, required: true },
    transactions: { type: Object, required: true },
    trend_insights: { type: Object, required: true },
});

const { shell, chartMode, isDark } = useInjectedAdminTheme();

const customFrom = ref(props.period.from);
const customTo = ref(props.period.to);
const expandedKey = ref('');
const rowDetail = ref(null);
const detailLoading = ref(false);
const trendChartRef = ref(null);
const breakdownChartRef = ref(null);
const cohortChartRef = ref(null);

const filters = reactive({
    revenue_type: '',
    status: '',
    q: '',
    sort: 'date',
});

const exportCsvUrl = computed(() =>
    route('admin.revenue-monitor.export.csv', {
        preset: props.period.preset,
        from: props.period.from,
        to: props.period.to,
        ...filters,
    }),
);

const exportPdfUrl = computed(() =>
    route('admin.revenue-monitor.export.pdf', {
        preset: props.period.preset,
        from: props.period.from,
        to: props.period.to,
        ...filters,
    }),
);

const trendDirectionLabel = computed(() => {
    const dir = props.trend_insights.trend_direction;
    if (dir === 'up') return '↑ Growing';
    if (dir === 'down') return '↓ Declining';
    return '→ Flat';
});

const trendChartSeries = computed(() =>
    (props.trend.series || []).map((s) => ({
        name: s.name,
        data: (s.data || []).map((v) => Math.round(v / 100)),
    })),
);

const trendChartOptions = computed(() => ({
    chart: { toolbar: { show: true }, background: 'transparent', foreColor: isDark.value ? '#cbd5e1' : '#64748b' },
    theme: { mode: chartMode.value },
    stroke: { width: 2, curve: 'smooth' },
    colors: ['#2563eb', '#059669', '#ea580c'],
    xaxis: { categories: props.trend.categories || [] },
    yaxis: { labels: { formatter: (v) => `₦${Number(v).toLocaleString()}` } },
    tooltip: { y: { formatter: (v) => `₦${Number(v).toLocaleString()}` } },
    legend: { position: 'top' },
}));

const breakdownChartSeries = computed(() => (props.breakdown.series || []).map((s) => s.amount_minor));
const breakdownChartOptions = computed(() => ({
    chart: { background: 'transparent', foreColor: isDark.value ? '#cbd5e1' : '#64748b' },
    theme: { mode: chartMode.value },
    labels: (props.breakdown.series || []).map((s) => s.label),
    colors: ['#2563eb', '#059669', '#ea580c'],
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '62%' } } },
    dataLabels: { enabled: true },
}));

const cohortChartSeries = computed(() => (props.cohort.series || []).map((s) => ({ name: s.name, data: s.data })));
const cohortChartOptions = computed(() => ({
    chart: { stacked: true, toolbar: { show: false }, background: 'transparent', foreColor: isDark.value ? '#cbd5e1' : '#64748b' },
    theme: { mode: chartMode.value },
    xaxis: { categories: props.cohort.categories || [] },
    colors: ['#059669', '#6366f1'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
    legend: { position: 'top' },
}));

function setPreset(key) {
    router.get(route('admin.revenue-monitor.index'), key === 'custom' ? { preset: key, from: customFrom.value, to: customTo.value } : { preset: key }, { preserveState: true, preserveScroll: true });
}

function applyCustom() {
    router.get(route('admin.revenue-monitor.index'), { preset: 'custom', from: customFrom.value, to: customTo.value }, { preserveState: true, preserveScroll: true });
}

function reloadTable() {
    router.get(
        route('admin.revenue-monitor.index'),
        { preset: props.period.preset, from: props.period.from, to: props.period.to, ...filters },
        { preserveState: true, preserveScroll: true, only: ['transactions'] },
    );
}

function detailKey(row) {
    return `${row.revenue_type}-${row.id}`;
}

async function toggleDetail(row) {
    const key = detailKey(row);
    if (expandedKey.value === key) {
        expandedKey.value = '';
        rowDetail.value = null;
        return;
    }
    expandedKey.value = key;
    detailLoading.value = true;
    rowDetail.value = null;
    try {
        const { data } = await window.axios.get(route('admin.revenue-monitor.api.transactions.detail', { type: row.revenue_type, id: row.id }));
        rowDetail.value = data;
    } finally {
        detailLoading.value = false;
    }
}

function formatGrowth(value) {
    const n = Number(value || 0);
    return `${n >= 0 ? '+' : ''}${n}%`;
}

function growthClass(value) {
    const n = Number(value || 0);
    if (n > 0) return 'text-emerald-700';
    if (n < 0) return 'text-rose-700';
    return 'text-slate-500';
}

function formatWhen(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Lagos' });
    } catch {
        return value;
    }
}

async function downloadChartPng(chartRef, filename) {
    const chart = chartRef.value?.chart;
    if (!chart) return;

    const { imgURI } = await chart.dataURI({ scale: 2 });
    const link = document.createElement('a');
    link.href = imgURI;
    link.download = filename;
    link.click();
}

async function exportAllChartsPng() {
    const stamp = props.period.label?.replace(/\s+/g, '-').toLowerCase() || 'revenue';
    await downloadChartPng(trendChartRef, `revenue-trends-${stamp}.png`);
    await downloadChartPng(breakdownChartRef, `revenue-breakdown-${stamp}.png`);
    await downloadChartPng(cohortChartRef, `premium-cohort-${stamp}.png`);
}
</script>
