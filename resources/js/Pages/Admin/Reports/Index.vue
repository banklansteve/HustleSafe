<template>
    <AdminShell
        title="Reports & analytics"
        subtitle="Platform health, user behaviour, and financial performance intelligence for super admins."
    >
        <template v-if="mode === 'overview'">
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <AdminKpiTile
                    v-for="tile in quickTiles"
                    :key="tile.label"
                    :label="tile.label"
                    :value="tile.value"
                    :hint="`${tile.change_pct}% vs previous period`"
                    :trend="`${tile.change_pct}%`"
                    :trend-positive="Number(tile.change_pct) >= 0"
                />
            </div>

            <AdminPanel eyebrow="Business intelligence" title="Analytics command centre">
                <template #actions>
                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" @click="openBuilder()">
                        Create new report
                    </button>
                </template>

                <div class="grid gap-3 lg:grid-cols-[1.2fr_0.8fr]">
                    <div>
                        <p class="text-sm font-semibold leading-6" :class="shell.cardMuted">
                            The original platform charts remain available here for quick monitoring. Use Create new report for aggregate-backed summaries, filtered drill-downs, scheduled delivery, and exports.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a :href="route('admin.reports.export')" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost">Export summary CSV</a>
                            <a :href="route('admin.dashboard.export')" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost">Dashboard CSV</a>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="rounded-2xl border p-4" :class="shell.card">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">Saved reports</p>
                            <p class="mt-2 text-3xl font-black" :class="shell.cardTitle">{{ saved_reports.length }}</p>
                        </div>
                        <div class="rounded-2xl border p-4" :class="shell.card">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">Recent exports</p>
                            <p class="mt-2 text-3xl font-black" :class="shell.cardTitle">{{ recent_exports.length }}</p>
                        </div>
                    </div>
                </div>
            </AdminPanel>

            <div class="grid gap-2 xl:grid-cols-[1fr_20rem]">
                <DashboardCharts :charts="charts" :leaderboards="leaderboards" />

                <aside class="space-y-2">
                    <AdminPanel eyebrow="Saved reports" title="Run again">
                        <div class="space-y-2">
                            <button
                                v-for="report in saved_reports"
                                :key="report.id"
                                type="button"
                                class="w-full rounded-xl border p-3 text-left transition hover:-translate-y-0.5"
                                :class="shell.card"
                                @click="runSaved(report)"
                            >
                                <p class="text-sm font-black" :class="shell.cardTitle">{{ report.name }}</p>
                                <p class="mt-1 text-xs" :class="shell.cardMuted">{{ report.schedule_frequency || 'Manual' }} · {{ report.date_preset }}</p>
                            </button>
                            <p v-if="!saved_reports.length" class="text-sm font-semibold" :class="shell.cardMuted">No saved reports yet.</p>
                        </div>
                    </AdminPanel>

                    <AdminPanel eyebrow="Exports" title="Prepared files">
                        <div class="space-y-2">
                            <div v-for="exportRow in recent_exports" :key="exportRow.id" class="rounded-xl border p-3 text-xs" :class="shell.card">
                                <p class="font-bold" :class="shell.cardTitle">{{ exportRow.report_name }}</p>
                                <p class="uppercase" :class="shell.cardMuted">{{ exportRow.format }} · {{ exportRow.status }}</p>
                                <a v-if="exportRow.download_url" :href="exportRow.download_url" class="mt-1 inline-flex font-bold" :class="shell.link">Download</a>
                            </div>
                            <p v-if="!recent_exports.length" class="text-sm font-semibold" :class="shell.cardMuted">No exports prepared yet.</p>
                        </div>
                    </AdminPanel>
                </aside>
            </div>
        </template>

        <template v-else>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <button type="button" class="text-xs font-black uppercase tracking-[0.2em]" :class="shell.link" @click="mode = 'overview'">
                        Back to overview
                    </button>
                    <h2 class="mt-1 font-display text-2xl font-black" :class="shell.title">Create new report</h2>
                    <p class="text-sm font-semibold" :class="shell.cardMuted">Choose a summary template, filter it, inspect the charts, then save or export.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost" @click="saveOpen = true">Save report</button>
                    <button v-for="format in ['pdf', 'xlsx', 'csv']" :key="format" type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost" @click="exportReport(format)">
                        Export {{ format }}
                    </button>
                </div>
            </div>

            <div class="grid gap-2 xl:grid-cols-[20rem_1fr]">
                <aside class="space-y-2">
                    <AdminPanel eyebrow="Templates" title="Summary builder">
                        <div class="space-y-2">
                            <button
                                v-for="template in catalog.templates"
                                :key="template.key"
                                type="button"
                                class="w-full rounded-2xl border p-4 text-left transition hover:-translate-y-0.5"
                                :class="activeTemplate === template.key ? shell.navActive : shell.card"
                                @click="selectTemplate(template.key)"
                            >
                                <p class="text-sm font-black">{{ template.label }}</p>
                                <p class="mt-2 text-xs font-semibold leading-relaxed" :class="activeTemplate === template.key ? '' : shell.cardMuted">{{ template.description }}</p>
                            </button>
                        </div>
                    </AdminPanel>

                    <AdminPanel eyebrow="Filters" title="Scope">
                        <div class="space-y-2">
                            <select v-model="datePreset" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="refreshPreview">
                                <option v-for="preset in catalog.presets" :key="preset.key" :value="preset.key">{{ preset.label }}</option>
                            </select>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="dateFrom" type="date" class="rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="refreshPreview" />
                                <input v-model="dateTo" type="date" class="rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="refreshPreview" />
                            </div>
                            <select v-model="grain" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="refreshPreview">
                                <option v-for="item in catalog.grains" :key="item.key" :value="item.key">{{ item.label }} aggregates</option>
                            </select>
                            <select v-model="filters.state_id" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="filters.local_government_id = ''; refreshPreview()">
                                <option value="">All states</option>
                                <option v-for="state in filter_options.states" :key="state.id" :value="state.id">{{ state.name }}</option>
                            </select>
                            <select v-model="filters.local_government_id" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="refreshPreview">
                                <option value="">All LGAs</option>
                                <option v-for="lga in localGovernmentOptions" :key="lga.id" :value="lga.id">{{ lga.name }}</option>
                            </select>
                            <select v-model="filters.category_id" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="refreshPreview">
                                <option value="">All categories</option>
                                <option v-for="category in filter_options.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                            <select v-model="filters.user_id" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="refreshPreview">
                                <option value="">App-wide / all users</option>
                                <option v-for="user in filter_options.users" :key="user.id" :value="user.id">{{ user.name }} · {{ user.role || 'user' }}</option>
                            </select>
                            <input v-model="filters.user_search" type="search" placeholder="Search user by name or email" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @input="queueRefresh" />
                        </div>
                    </AdminPanel>
                </aside>

                <main class="space-y-2">
                    <div v-if="loading" class="h-96 animate-pulse rounded-3xl" :class="shell.card" />

                    <template v-else-if="preview">
                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                            <div v-for="item in preview.summary" :key="item.label" class="rounded-2xl border p-4" :class="shell.card">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">{{ item.label }}</p>
                                <p class="mt-2 text-2xl font-black" :class="shell.cardTitle">{{ item.value }}</p>
                            </div>
                        </div>

                        <div class="grid gap-2 xl:grid-cols-2">
                            <ChartPanel v-for="chart in preview.charts" :key="chart.title" :chart="chart" :shell="shell" @filter="applyChartFilter" />
                        </div>

                        <AdminPanel :eyebrow="preview.name" title="Drill-down table">
                            <div class="overflow-x-auto rounded-2xl border" :class="shell.card">
                                <table class="min-w-full text-left text-sm">
                                    <thead :class="shell.tableHead">
                                        <tr>
                                            <th v-for="column in preview.columns" :key="column" class="px-4 py-3 text-[10px] font-black uppercase tracking-wide">
                                                <button type="button" @click="sortBy(column)">{{ column.replace(/_/g, ' ') }}</button>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y" :class="shell.tableDivide">
                                        <tr v-for="(row, index) in preview.rows" :key="index">
                                            <td v-for="column in preview.columns" :key="column" class="px-4 py-3 font-semibold">{{ row[column] ?? '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs font-bold" :class="shell.cardMuted">
                                <span>{{ preview.pagination.total }} rows</span>
                                <div class="flex gap-2">
                                    <button class="rounded-lg px-3 py-1.5" :class="shell.btnGhost" :disabled="page <= 1" @click="page--; refreshPreview()">Prev</button>
                                    <button class="rounded-lg px-3 py-1.5" :class="shell.btnGhost" :disabled="page >= preview.pagination.last_page" @click="page++; refreshPreview()">Next</button>
                                </div>
                            </div>
                        </AdminPanel>
                    </template>
                </main>
            </div>
        </template>

        <AdminSlideOver :open="saveOpen" title="Save report" eyebrow="Scheduling" @close="saveOpen = false">
            <form class="space-y-3" @submit.prevent="saveReport">
                <input v-model="saveForm.name" required placeholder="Report name" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                <textarea v-model="saveForm.description" rows="3" placeholder="Description" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                <select v-model="saveForm.schedule_frequency" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input">
                    <option value="">No schedule</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
                <textarea v-model="saveRecipients" rows="3" placeholder="Emails, comma separated" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                <button class="w-full rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary">Save report</button>
            </form>
        </AdminSlideOver>
    </AdminShell>
</template>

<script setup>
import AdminKpiTile from '@/Components/Admin/AdminKpiTile.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { router } from '@inertiajs/vue3';
import { computed, defineComponent, h, reactive, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import DashboardCharts from '../DashboardCharts.vue';

const props = defineProps({
    charts: { type: Object, required: true },
    leaderboards: { type: Object, required: true },
    catalog: { type: Object, required: true },
    quick_stats: { type: Object, required: true },
    saved_reports: { type: Array, default: () => [] },
    recent_exports: { type: Array, default: () => [] },
    filter_options: { type: Object, required: true },
});

const { shell } = useInjectedAdminTheme();
const mode = ref('overview');
const activeTemplate = ref('freelancer_performance');
const datePreset = ref('last_30_days');
const dateFrom = ref('');
const dateTo = ref('');
const grain = ref('daily');
const page = ref(1);
const sort = ref('');
const direction = ref('desc');
const loading = ref(false);
const preview = ref(null);
const saveOpen = ref(false);
const saveRecipients = ref('');
const saveForm = reactive({ name: '', description: '', schedule_frequency: '' });
const filters = reactive({ state_id: '', local_government_id: '', category_id: '', user_id: '', user_search: '' });
let searchTimer;

const quickTiles = computed(() => [
    { label: 'Platform GMV', ...props.quick_stats.gmv },
    { label: 'Active contracts', ...props.quick_stats.active_contracts },
    { label: 'Revenue this month', ...props.quick_stats.platform_revenue_month },
    { label: 'New users this month', ...props.quick_stats.new_users_month },
]);

const localGovernmentOptions = computed(() => {
    if (!filters.state_id) {
        return props.filter_options.local_governments;
    }

    return props.filter_options.local_governments.filter((lga) => Number(lga.state_id) === Number(filters.state_id));
});

function payload(extra = {}) {
    return {
        report_type: activeTemplate.value,
        template: activeTemplate.value,
        filters: { ...filters },
        date_preset: datePreset.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        grain: grain.value,
        page: page.value,
        sort: sort.value,
        direction: direction.value,
        ...extra,
    };
}

function openBuilder(template = activeTemplate.value) {
    mode.value = 'builder';
    selectTemplate(template);
}

function selectTemplate(template) {
    activeTemplate.value = template;
    page.value = 1;
    sort.value = '';
    refreshPreview();
}

async function refreshPreview() {
    if (mode.value !== 'builder') return;

    loading.value = true;
    const { data } = await window.axios.post(route('admin.reports.preview'), payload());
    preview.value = data;
    loading.value = false;
}

function queueRefresh() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(refreshPreview, 350);
}

async function runSaved(report) {
    activeTemplate.value = report.report_type;
    mode.value = 'builder';
    const { data } = await window.axios.post(route('admin.reports.saved.run', report.id));
    preview.value = data;
}

function sortBy(column) {
    direction.value = sort.value === column && direction.value === 'desc' ? 'asc' : 'desc';
    sort.value = column;
    refreshPreview();
}

function applyChartFilter(label) {
    const category = props.filter_options.categories.find((item) => item.name === label);
    const state = props.filter_options.states.find((item) => item.name === label);
    if (category) filters.category_id = category.id;
    if (state) filters.state_id = state.id;
    refreshPreview();
}

function saveReport() {
    router.post(route('admin.reports.saved.store'), {
        name: saveForm.name,
        description: saveForm.description,
        report_type: activeTemplate.value,
        builder_config: { template: activeTemplate.value, grain: grain.value },
        filters: { ...filters },
        date_preset: datePreset.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        schedule_frequency: saveForm.schedule_frequency || null,
        schedule_recipients: saveRecipients.value.split(',').map((email) => email.trim()).filter(Boolean),
    }, { preserveScroll: true, onSuccess: () => { saveOpen.value = false; } });
}

async function exportReport(format) {
    if (!preview.value) {
        await refreshPreview();
    }

    await window.axios.post(route('admin.reports.exports.store'), {
        format,
        report_name: preview.value?.name || 'Advanced report',
        report_type: activeTemplate.value,
        payload: payload(),
    });
}

const ChartPanel = defineComponent({
    props: { chart: { type: Object, required: true }, shell: { type: Object, required: true } },
    emits: ['filter'],
    setup(componentProps, { emit }) {
        return () => {
            const chart = componentProps.chart;

            if (chart.type === 'funnel') {
                return h('section', { class: ['rounded-2xl border p-4', componentProps.shell.card] }, [
                    h('p', { class: 'mb-3 text-sm font-black' }, chart.title),
                    h('div', { class: 'grid gap-2 sm:grid-cols-5' }, (chart.series || []).map((item) => h('button', {
                        type: 'button',
                        class: 'rounded-2xl bg-teal-600 p-3 text-left text-white',
                        onClick: () => emit('filter', item.stage),
                    }, [
                        h('p', { class: 'text-xs font-bold' }, item.stage),
                        h('p', { class: 'text-xl font-black' }, item.count),
                        h('p', { class: 'text-xs' }, `${item.conversion_rate}%`),
                    ]))),
                ]);
            }

            if (chart.type === 'choropleth') {
                const max = Math.max(...(chart.states || []).map((state) => Number(state.jobs_posted || 0)), 1);
                return h('section', { class: ['rounded-2xl border p-4', componentProps.shell.card] }, [
                    h('p', { class: 'mb-3 text-sm font-black' }, chart.title),
                    h('div', { class: 'grid gap-2 sm:grid-cols-3 lg:grid-cols-4' }, (chart.states || []).map((state) => h('button', {
                        type: 'button',
                        class: 'rounded-xl p-3 text-left text-xs font-bold text-white',
                        style: { backgroundColor: `rgba(13, 148, 136, ${0.18 + (Number(state.jobs_posted || 0) / max) * 0.76})` },
                        onClick: () => emit('filter', state.state),
                    }, [h('p', state.state), h('p', { class: 'text-lg font-black' }, state.jobs_posted)]))),
                ]);
            }

            const isDonut = chart.type === 'donut';
            return h('section', { class: ['rounded-2xl border p-4', componentProps.shell.card] }, [
                h('p', { class: 'mb-3 text-sm font-black' }, chart.title),
                h(VueApexCharts, {
                    type: chart.type === 'stacked_bar' ? 'bar' : chart.type,
                    height: 320,
                    options: {
                        chart: {
                            toolbar: { show: false },
                            stacked: chart.type === 'stacked_bar',
                            events: {
                                dataPointSelection: (_, __, config) => emit('filter', chart.labels?.[config.dataPointIndex]),
                            },
                        },
                        labels: chart.labels || [],
                        xaxis: { categories: chart.labels || [] },
                        plotOptions: { bar: { borderRadius: 6, horizontal: chart.type === 'bar' && (chart.labels || []).length > 6 } },
                        legend: { position: 'bottom' },
                    },
                    series: isDonut ? (chart.series || []) : (chart.series || []),
                }),
            ]);
        };
    },
});
</script>
