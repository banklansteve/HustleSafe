<template>
    <section class="grid gap-2 xl:grid-cols-2">
        <template v-if="mode === 'financial'">
            <AdminPanel eyebrow="Treasury" title="Escrow held (daily)">
                <VueApexCharts type="area" height="280" :options="escrowChartOptions" :series="escrowSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Revenue" title="Escrow funded (daily inflows)">
                <VueApexCharts type="area" height="280" :options="inflowChartOptions" :series="inflowSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Revenue" title="Platform fee recognised (daily)">
                <VueApexCharts type="area" height="280" :options="feeChartOptions" :series="feeSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Tax" title="VAT accrued on platform fees (daily)">
                <VueApexCharts type="area" height="280" :options="vatChartOptions" :series="vatSeries" />
            </AdminPanel>
        </template>

        <template v-else>
            <AdminPanel eyebrow="Acquisition" title="User growth (14 days)">
                <VueApexCharts type="area" height="280" :options="signupChartOptions" :series="signupSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Mix" title="Quests by status">
                <VueApexCharts type="donut" height="300" :options="statusDonutOptions" :series="statusDonutSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Funnel" title="Proposal → hire → complete">
                <VueApexCharts type="bar" height="300" :options="funnelOptions" :series="funnelSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Demand" title="Jobs per category">
                <VueApexCharts type="bar" height="320" :options="categoryBarOptions" :series="categoryBarSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Revenue" title="Paid out by category">
                <VueApexCharts type="bar" height="320" :options="revenueBarOptions" :series="revenueBarSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Geography" title="Jobs by Nigerian state">
                <VueApexCharts type="bar" height="320" :options="geoOptions" :series="geoSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Retention" title="Cohort return rate">
                <VueApexCharts type="line" height="280" :options="cohortOptions" :series="cohortSeries" />
            </AdminPanel>

            <AdminPanel eyebrow="Leaderboard" title="Top freelancers (trust)">
                <ul class="space-y-2">
                    <li
                        v-for="row in leaderboards.freelancers"
                        :key="row.id"
                        class="flex items-center justify-between rounded-xl border px-3 py-2 text-sm"
                        :class="shell.card"
                    >
                        <span class="font-semibold" :class="shell.cardTitle">{{ row.name }}</span>
                        <span class="text-xs font-bold text-teal-600">{{ row.metric }}</span>
                    </li>
                </ul>
            </AdminPanel>

            <AdminPanel eyebrow="Leaderboard" title="Top spending clients">
                <ul class="space-y-2">
                    <li
                        v-for="row in leaderboards.clients"
                        :key="row.id"
                        class="flex items-center justify-between rounded-xl border px-3 py-2 text-sm"
                        :class="shell.card"
                    >
                        <span class="font-semibold" :class="shell.title">{{ row.name }}</span>
                        <span class="text-xs font-bold text-teal-600">₦{{ (row.metric / 100).toLocaleString() }}</span>
                    </li>
                </ul>
            </AdminPanel>
        </template>
    </section>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    charts: { type: Object, required: true },
    leaderboards: { type: Object, default: () => ({ freelancers: [], clients: [] }) },
    mode: { type: String, default: 'financial' },
});

const { shell, chartMode, isDark } = useInjectedAdminTheme();
const isLg = useMediaQuery('(min-width: 1024px)');

const chartBase = computed(() => ({
    chart: {
        toolbar: { show: isLg.value },
        fontFamily: 'inherit',
        foreColor: isDark.value ? '#94a3b8' : '#64748b',
    },
    grid: { borderColor: isDark.value ? 'rgba(148,163,184,0.15)' : 'rgba(15,118,110,0.12)' },
    theme: { mode: chartMode.value },
    tooltip: { theme: chartMode.value },
}));

const moneyYAxis = {
    labels: { formatter: (v) => `₦${Math.round(v / 100).toLocaleString()}` },
};

function dailySeries(key, name) {
    return computed(() => [{ name, data: (props.charts[key] || []).map((d) => d.minor) }]);
}

function dailyOptions(key) {
    return computed(() => ({
        ...chartBase.value,
        colors: ['#0d9488'],
        stroke: { curve: 'smooth', width: 2 },
        xaxis: { categories: (props.charts[key] || []).map((d) => d.date.slice(5)) },
        yaxis: moneyYAxis,
    }));
}

const escrowSeries = dailySeries('escrow_daily', 'Escrow held');
const escrowChartOptions = dailyOptions('escrow_daily');
const inflowSeries = dailySeries('escrow_inflow_daily', 'Escrow funded');
const inflowChartOptions = dailyOptions('escrow_inflow_daily');
const feeSeries = dailySeries('platform_fee_daily', 'Platform fees');
const feeChartOptions = computed(() => ({
    ...dailyOptions('platform_fee_daily').value,
    colors: ['#f59e0b'],
}));
const vatSeries = dailySeries('vat_daily', 'VAT accrued');
const vatChartOptions = computed(() => ({
    ...dailyOptions('vat_daily').value,
    colors: ['#6366f1'],
}));

const signupSeries = computed(() => [{ name: 'Signups', data: (props.charts.signups || []).map((d) => d.count) }]);
const signupChartOptions = computed(() => ({
    ...chartBase.value,
    colors: ['#14b8a6'],
    stroke: { curve: 'smooth', width: 2 },
    xaxis: { categories: (props.charts.signups || []).map((d) => d.date.slice(5)) },
}));

const statusDonutSeries = computed(() => (props.charts.quest_mix || []).map((r) => r.count));
const statusDonutOptions = computed(() => ({
    ...chartBase.value,
    labels: (props.charts.quest_mix || []).map((r) => String(r.status).replace(/_/g, ' ')),
    legend: { position: 'bottom' },
}));

const funnelSeries = computed(() => [{ name: 'Volume', data: (props.charts.funnel || []).map((s) => s.count) }]);
const funnelOptions = computed(() => ({
    ...chartBase.value,
    colors: ['#2dd4bf'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '50%' } },
    xaxis: { categories: (props.charts.funnel || []).map((s) => s.step) },
}));

const categoryBarSeries = computed(() => [{ name: 'Quests', data: (props.charts.category_heatmap || []).map((r) => r.count) }]);
const categoryBarOptions = computed(() => ({
    ...chartBase.value,
    colors: ['#14b8a6'],
    plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
    xaxis: { categories: (props.charts.category_heatmap || []).map((r) => r.category) },
}));

const revenueBarSeries = computed(() => [{ name: 'Paid out (minor)', data: (props.charts.category_heatmap || []).map((r) => r.revenue_minor || 0) }]);
const revenueBarOptions = computed(() => ({
    ...chartBase.value,
    colors: ['#f59e0b'],
    plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
    xaxis: { categories: (props.charts.category_heatmap || []).map((r) => r.category) },
}));

const geoSeries = computed(() => [{ name: 'Quests', data: (props.charts.geo || []).map((r) => r.count) }]);
const geoOptions = computed(() => ({
    ...chartBase.value,
    colors: ['#0d9488'],
    plotOptions: { bar: { borderRadius: 6 } },
    xaxis: { categories: (props.charts.geo || []).map((r) => r.state) },
}));

const cohortSeries = computed(() => [{ name: 'Retention %', data: (props.charts.cohort || []).map((r) => r.retained_pct) }]);
const cohortOptions = computed(() => ({
    ...chartBase.value,
    colors: ['#14b8a6'],
    stroke: { curve: 'smooth', width: 2 },
    xaxis: { categories: (props.charts.cohort || []).map((r) => r.month) },
    yaxis: { max: 100, labels: { formatter: (v) => `${Math.round(v)}%` } },
}));
</script>
