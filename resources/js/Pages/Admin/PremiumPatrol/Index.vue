<template>
    <AdminShell
        title="Premium & Boost Patrol"
        subtitle="Automatic activation with flag-based monitoring — review anomalies, patrol tables, and take enforcement actions."
    >
        <div class="space-y-6">
            <div class="flex flex-wrap items-end gap-2">
                <button
                    v-for="preset in rangePresets"
                    :key="preset.value"
                    type="button"
                    class="rounded-xl px-3 py-2 text-xs font-black uppercase transition"
                    :class="range.preset === preset.value ? shell.btnPrimary : shell.btnGhost"
                    @click="setRange(preset.value)"
                >
                    {{ preset.label }}
                </button>
                <template v-if="range.preset === 'custom'">
                    <AdminDateInput v-model="customFrom" wrapper-class="" @change="applyCustomRange" />
                    <span class="text-xs font-bold" :class="shell.cardMuted">to</span>
                    <AdminDateInput v-model="customTo" wrapper-class="" @change="applyCustomRange" />
                </template>
            </div>

            <div class="flex flex-wrap gap-2 border-b pb-2" :class="shell.tableDivide">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    class="rounded-xl px-4 py-2 text-xs font-black uppercase"
                    :class="activeTab === t.key ? shell.btnPrimary : shell.btnGhost"
                    @click="switchTab(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>

            <section v-if="anomaly_alerts.length" class="space-y-2">
                <div
                    v-for="alert in anomaly_alerts"
                    :key="alert.id"
                    class="flex flex-col gap-3 rounded-2xl border border-amber-300 bg-amber-50/80 p-4 dark:border-amber-700 dark:bg-amber-950/30 sm:flex-row sm:items-center"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-black uppercase text-amber-800 dark:text-amber-300">Anomaly detected</p>
                        <p class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">{{ alert.message }}</p>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ alert.label }} · {{ formatWhen(alert.detected_at) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg bg-slate-900 px-3 py-1.5 text-[10px] font-black uppercase text-white dark:bg-white dark:text-slate-900" @click="reviewAlert(alert)">Review</button>
                        <button type="button" class="rounded-lg border px-3 py-1.5 text-[10px] font-black uppercase" :class="shell.btnGhost" @click="dismissAlert(alert)">Dismiss</button>
                        <button type="button" class="rounded-lg bg-amber-600 px-3 py-1.5 text-[10px] font-black uppercase text-white" @click="investigateAlert(alert)">Investigate</button>
                    </div>
                </div>
            </section>

            <template v-if="activeTab === 'overview'">
                <div class="grid gap-4 lg:grid-cols-2">
                    <AdminPanel eyebrow="Premium subscriptions" title="Freelancer Pro metrics">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <AdminKpiTile label="Active premium users" :value="metrics.premium.active_this_month" />
                            <AdminKpiTile label="New signups (7d)" :value="metrics.premium.new_signups_7d" />
                            <AdminKpiTile label="Churn rate (30d)" :value="`${metrics.premium.churn_rate_30d}%`" />
                            <AdminKpiTile label="MRR (premium)" :value="formatNgn(metrics.premium.mrr_minor)" />
                        </div>
                        <div class="mt-4">
                            <p class="mb-2 text-xs font-black uppercase" :class="shell.label">Premium growth</p>
                            <VueApexCharts type="line" height="260" :options="premiumChartOptions" :series="premiumChartSeries" />
                        </div>
                    </AdminPanel>

                    <AdminPanel eyebrow="Quest boosts" title="Boost activity metrics">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <AdminKpiTile label="Active boosted quests" :value="metrics.boosts.active_live" />
                            <AdminKpiTile label="New boosts (7d)" :value="metrics.boosts.new_7d" />
                            <AdminKpiTile label="Avg boost duration" :value="`${metrics.boosts.avg_duration_days}d`" />
                            <AdminKpiTile label="Boost revenue (30d)" :value="formatNgn(metrics.boosts.revenue_30d_minor)" />
                        </div>
                        <div class="mt-4">
                            <p class="mb-2 text-xs font-black uppercase" :class="shell.label">Boost activity</p>
                            <VueApexCharts type="line" height="260" :options="boostChartOptions" :series="boostChartSeries" />
                        </div>
                    </AdminPanel>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="button" class="rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black uppercase text-white" @click="showGrantPremium = true">Manual premium upgrade</button>
                    <button type="button" class="rounded-xl bg-amber-600 px-4 py-2.5 text-xs font-black uppercase text-white" @click="showGrantBoost = true">Manual quest boost</button>
                </div>
            </template>

            <template v-if="activeTab === 'premium'">
                <div class="mb-4 flex flex-wrap gap-3">
                    <select v-model="premiumFilters.signup_range" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="reloadPremium">
                        <option value="">All signups</option>
                        <option value="24h">Last 24h</option>
                        <option value="7d">Last 7d</option>
                        <option value="30d">Last 30d</option>
                    </select>
                    <select v-model="premiumFilters.patrol_status" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="reloadPremium">
                        <option value="">All statuses</option>
                        <option v-for="s in filter_options.patrol_statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <select v-model="premiumFilters.billing_cycle" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="reloadPremium">
                        <option value="">All billing</option>
                        <option v-for="b in filter_options.billing_cycles" :key="b.value" :value="b.value">{{ b.label }}</option>
                    </select>
                </div>

                <div class="overflow-x-auto rounded-2xl border" :class="shell.card">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b text-[10px] font-black uppercase tracking-wider" :class="shell.tableDivide">
                            <tr>
                                <th class="px-4 py-3">User</th>
                                <th class="px-4 py-3">Signup</th>
                                <th class="px-4 py-3">Tier</th>
                                <th class="px-4 py-3">Plan</th>
                                <th class="px-4 py-3">Paid</th>
                                <th class="px-4 py-3">Acct age</th>
                                <th class="px-4 py-3">Risk</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in premium_users.data"
                                :key="row.user_id"
                                class="border-b"
                                :class="[shell.tableDivide, row.has_open_flags ? 'bg-orange-50/60 dark:bg-orange-950/20' : '']"
                            >
                                <td class="px-4 py-3 font-bold" :class="shell.cardTitle">{{ row.fullname }}</td>
                                <td class="px-4 py-3 text-xs" :class="shell.cardMuted">{{ formatWhen(row.signup_date) }}</td>
                                <td class="px-4 py-3">L{{ row.verification_tier }}</td>
                                <td class="px-4 py-3">{{ row.subscription_type }}</td>
                                <td class="px-4 py-3 font-black">{{ row.cost_paid_display }}</td>
                                <td class="px-4 py-3 text-xs" :class="row.account_age_flag ? 'font-black text-orange-600' : shell.cardMuted">{{ row.account_age_at_purchase }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[10px] font-black" :class="riskClass(row.risk_score)">{{ row.risk_score }}</span></td>
                                <td class="px-4 py-3 text-xs font-black uppercase">{{ row.patrol_status }}</td>
                                <td class="px-4 py-3">
                                    <button type="button" class="text-xs font-black text-primary-700" @click="openPremiumDetail(row.user_id)">View</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <template v-if="activeTab === 'boosts'">
                <div class="mb-4 flex flex-wrap gap-3">
                    <select v-model="boostFilters.boost_status" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="reloadBoosts">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                    </select>
                    <select v-model="boostFilters.duration" class="rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="reloadBoosts">
                        <option value="">All durations</option>
                        <option v-for="d in filter_options.boost_durations" :key="d.value" :value="d.value">{{ d.label }}</option>
                    </select>
                </div>

                <div class="overflow-x-auto rounded-2xl border" :class="shell.card">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b text-[10px] font-black uppercase tracking-wider" :class="shell.tableDivide">
                            <tr>
                                <th class="px-4 py-3">Quest</th>
                                <th class="px-4 py-3">Client</th>
                                <th class="px-4 py-3">Start</th>
                                <th class="px-4 py-3">Duration</th>
                                <th class="px-4 py-3">Cost</th>
                                <th class="px-4 py-3">Job value</th>
                                <th class="px-4 py-3">Proposals</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in boosted_quests.data"
                                :key="row.id"
                                class="border-b"
                                :class="[shell.tableDivide, row.has_open_flags ? 'bg-orange-50/60 dark:bg-orange-950/20' : '']"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-bold" :class="shell.cardTitle">#{{ row.quest_id }}</p>
                                    <p class="text-xs" :class="shell.cardMuted">{{ row.quest_title }}</p>
                                </td>
                                <td class="px-4 py-3">{{ row.client_name }}</td>
                                <td class="px-4 py-3 text-xs" :class="shell.cardMuted">{{ formatWhen(row.boost_start) }}</td>
                                <td class="px-4 py-3">{{ row.duration_label }}</td>
                                <td class="px-4 py-3 font-black">{{ row.cost_display }}</td>
                                <td class="px-4 py-3" :class="budgetOutlierClass(row.budget_deviation_percent)">{{ row.job_value_display }}</td>
                                <td class="px-4 py-3">{{ row.proposals_count }}</td>
                                <td class="px-4 py-3 text-xs font-black uppercase">{{ row.status }}</td>
                                <td class="px-4 py-3">
                                    <button type="button" class="text-xs font-black text-primary-700" @click="openBoostDetail(row.id)">View</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <AdminSlideOver :open="detailOpen" :title="detailTitle" eyebrow="Patrol detail" width-class="max-w-full sm:max-w-xl" @close="detailOpen = false">
            <div v-if="detailLoading" class="py-12 text-center text-sm font-semibold" :class="shell.cardMuted">Loading…</div>
            <div v-else-if="premiumDetail" class="space-y-4">
                <p class="text-sm font-bold" :class="shell.cardTitle">{{ premiumDetail.user.fullname }}</p>
                <p class="text-xs" :class="shell.cardMuted">Risk score: {{ premiumDetail.user.risk_score }} · {{ premiumDetail.user.location }}</p>
                <div v-if="premiumDetail.flags.length" class="space-y-1">
                    <p v-for="f in premiumDetail.flags" :key="f.id" class="rounded-lg bg-orange-50 px-3 py-2 text-xs font-bold text-orange-800 dark:bg-orange-950/40">{{ f.label }} ({{ f.severity }})</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg bg-red-700 px-3 py-1.5 text-[10px] font-black uppercase text-white" @click="runPremiumAction('suspend')">Suspend</button>
                    <button type="button" class="rounded-lg bg-amber-600 px-3 py-1.5 text-[10px] font-black uppercase text-white" @click="runPremiumAction('refund')">Refund</button>
                    <button type="button" class="rounded-lg border px-3 py-1.5 text-[10px] font-black uppercase" :class="shell.btnGhost" @click="runPremiumAction('investigate')">Investigate</button>
                </div>
            </div>
            <div v-else-if="boostDetail" class="space-y-4">
                <p class="text-sm font-bold" :class="shell.cardTitle">{{ boostDetail.boost.quest_title }}</p>
                <p class="text-xs" :class="shell.cardMuted">Budget {{ boostDetail.budget_vs_market.budget_display }} vs market {{ boostDetail.budget_vs_market.market_median_display }} ({{ boostDetail.budget_vs_market.deviation_percent ?? '—' }}%)</p>
                <p class="text-xs" :class="shell.cardMuted">Client account age: {{ boostDetail.client_account_age_days ?? '—' }} days</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg bg-red-700 px-3 py-1.5 text-[10px] font-black uppercase text-white" @click="runBoostAction('demote')">Demote</button>
                    <button type="button" class="rounded-lg bg-amber-600 px-3 py-1.5 text-[10px] font-black uppercase text-white" @click="runBoostAction('refund')">Refund</button>
                    <button type="button" class="rounded-lg border px-3 py-1.5 text-[10px] font-black uppercase" :class="shell.btnGhost" @click="runBoostAction('investigate')">Investigate</button>
                </div>
            </div>
        </AdminSlideOver>

        <div v-if="showGrantPremium" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-4" @click.self="showGrantPremium = false">
            <form class="w-full max-w-lg rounded-2xl border p-6" :class="shell.card" @submit.prevent="submitGrantPremium">
                <h3 class="text-lg font-black" :class="shell.cardTitle">Manual premium upgrade</h3>
                <input v-model="grantPremiumSearch" type="search" placeholder="Search user…" class="mt-4 w-full rounded-xl border px-3 py-2 text-sm" :class="shell.input" @input="searchGrantUsers" />
                <ul v-if="grantUserResults.length" class="mt-2 max-h-40 overflow-auto rounded-xl border">
                    <li v-for="u in grantUserResults" :key="u.id">
                        <button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50" @click="grantPremiumForm.user_id = u.id">{{ u.name }} · {{ u.email }}</button>
                    </li>
                </ul>
                <select v-model="grantPremiumForm.billing_cycle" class="mt-4 w-full rounded-xl border px-3 py-2 text-sm" :class="shell.input">
                    <option value="month">Monthly</option>
                    <option value="year">Annual</option>
                </select>
                <textarea v-model="grantPremiumForm.reason_notes" required rows="3" class="mt-4 w-full rounded-xl border px-3 py-2 text-sm" :class="shell.input" placeholder="Reason (audit trail)…" />
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="!grantPremiumForm.user_id">Upgrade</button>
                    <button type="button" class="rounded-xl border px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost" @click="showGrantPremium = false">Cancel</button>
                </div>
            </form>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminKpiTile from '@/Components/Admin/AdminKpiTile.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    tab: { type: String, default: 'overview' },
    range: { type: Object, required: true },
    metrics: { type: Object, required: true },
    anomaly_alerts: { type: Array, default: () => [] },
    premium_users: { type: Object, required: true },
    boosted_quests: { type: Object, required: true },
    filter_options: { type: Object, required: true },
    reason_codes: { type: Object, default: () => ({}) },
});

const { shell, chartMode, isDark } = useInjectedAdminTheme();

const activeTab = ref(props.tab);
const rangePresets = [
    { value: '7d', label: 'Last 7 days' },
    { value: '30d', label: 'Last 30 days' },
    { value: '90d', label: 'Last 90 days' },
    { value: 'custom', label: 'Custom range' },
];
const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'premium', label: 'Premium users' },
    { key: 'boosts', label: 'Boosted quests' },
];

const customFrom = ref(props.range.from);
const customTo = ref(props.range.to);
const premiumFilters = reactive({ signup_range: '', patrol_status: '', billing_cycle: '' });
const boostFilters = reactive({ boost_status: '', duration: '' });

const detailOpen = ref(false);
const detailLoading = ref(false);
const premiumDetail = ref(null);
const boostDetail = ref(null);
const selectedUserId = ref(null);
const selectedBoostId = ref(null);

const showGrantPremium = ref(false);
const showGrantBoost = ref(false);
const grantPremiumSearch = ref('');
const grantUserResults = ref([]);
const grantPremiumForm = reactive({ user_id: null, billing_cycle: 'month', reason_code: 'manual_grant', reason_notes: '' });

const detailTitle = computed(() => premiumDetail.value?.user?.fullname ?? boostDetail.value?.boost?.quest_title ?? 'Detail');

const chartBase = computed(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', foreColor: isDark.value ? '#94a3b8' : '#64748b' },
    theme: { mode: chartMode.value },
    stroke: { curve: 'smooth', width: 2 },
}));

const premiumChartSeries = computed(() => [{ name: 'New premium', data: (props.metrics.premium.growth_chart || []).map((d) => d.count) }]);
const premiumChartOptions = computed(() => ({
    ...chartBase.value,
    colors: ['#0d9488'],
    xaxis: { categories: (props.metrics.premium.growth_chart || []).map((d) => d.date.slice(5)) },
}));

const boostChartSeries = computed(() => [
    { name: 'Boosts', data: (props.metrics.boosts.activity_chart || []).map((d) => d.count) },
    { name: 'Revenue', data: (props.metrics.boosts.activity_chart || []).map((d) => d.minor) },
]);
const boostChartOptions = computed(() => ({
    ...chartBase.value,
    colors: ['#f59e0b', '#6366f1'],
    xaxis: { categories: (props.metrics.boosts.activity_chart || []).map((d) => d.date.slice(5)) },
    yaxis: [{ title: { text: 'Count' } }, { opposite: true, labels: { formatter: (v) => `₦${Math.round(v / 100).toLocaleString()}` } }],
}));

function formatNgn(minor) {
    return `₦${((minor || 0) / 100).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function riskClass(score) {
    if (score >= 60) return 'bg-red-100 text-red-800';
    if (score >= 30) return 'bg-orange-100 text-orange-800';
    return 'bg-slate-100 text-slate-700';
}

function budgetOutlierClass(pct) {
    if (pct == null) return '';
    if (Math.abs(pct) > 50) return 'font-black text-orange-600';
    return '';
}

function visit(params = {}) {
    router.get(route('admin.premium-patrol.index'), { tab: activeTab.value, range: props.range.preset, ...params }, { preserveState: true, replace: true });
}

function setRange(preset) {
    visit({ range: preset, from: preset === 'custom' ? customFrom.value : undefined, to: preset === 'custom' ? customTo.value : undefined });
}

function applyCustomRange() {
    visit({ range: 'custom', from: customFrom.value, to: customTo.value });
}

function switchTab(key) {
    activeTab.value = key;
    visit({ tab: key });
}

function reloadPremium() {
    visit({ ...premiumFilters });
}

function reloadBoosts() {
    visit({ ...boostFilters });
}

async function openPremiumDetail(userId) {
    selectedUserId.value = userId;
    detailOpen.value = true;
    detailLoading.value = true;
    premiumDetail.value = null;
    boostDetail.value = null;
    const { data } = await axios.get(route('admin.api.premium-patrol.premium-user', userId));
    premiumDetail.value = data;
    detailLoading.value = false;
}

async function openBoostDetail(boostId) {
    selectedBoostId.value = boostId;
    detailOpen.value = true;
    detailLoading.value = true;
    boostDetail.value = null;
    premiumDetail.value = null;
    const { data } = await axios.get(route('admin.api.premium-patrol.boost', boostId));
    boostDetail.value = data;
    detailLoading.value = false;
}

function runPremiumAction(action) {
    const userId = selectedUserId.value;
    if (!userId) return;
    const reason = prompt('Reason code (fraud/suspicious/terms/investigation):', 'investigation');
    const notes = prompt('Notes for audit trail:', '');
    if (!reason) return;
    const routes = {
        suspend: 'admin.premium-patrol.premium-users.suspend',
        refund: 'admin.premium-patrol.premium-users.refund',
        investigate: 'admin.premium-patrol.premium-users.investigate',
    };
    router.post(route(routes[action], userId), { reason_code: reason, reason_notes: notes }, { preserveScroll: true, onSuccess: () => { detailOpen.value = false; } });
}

function runBoostAction(action) {
    const boostId = selectedBoostId.value;
    if (!boostId) return;
    const reason = prompt('Reason code:', 'investigation');
    const notes = prompt('Notes:', '');
    if (!reason) return;
    const routes = {
        demote: 'admin.premium-patrol.boosts.demote',
        refund: 'admin.premium-patrol.boosts.refund',
        investigate: 'admin.premium-patrol.boosts.investigate',
    };
    router.post(route(routes[action], boostId), { reason_code: reason, reason_notes: notes }, { preserveScroll: true, onSuccess: () => { detailOpen.value = false; } });
}

function dismissAlert(alert) {
    const reason = prompt('Dismissal reason:', '');
    if (!reason) return;
    router.post(route('admin.api.premium-patrol.flags.dismiss', alert.id), { reason }, { preserveScroll: true });
}

function reviewAlert(alert) {
    if (alert.subject_type === 'premium_user' && alert.subject_id) openPremiumDetail(alert.subject_id);
    else if (alert.subject_type === 'boosted_quest' && alert.subject_id) openBoostDetail(alert.subject_id);
}

function investigateAlert(alert) {
    reviewAlert(alert);
}

async function searchGrantUsers() {
    const { data } = await axios.get(route('admin.api.premium-patrol.users.search'), { params: { q: grantPremiumSearch.value } });
    grantUserResults.value = data.data || [];
}

function submitGrantPremium() {
    router.post(route('admin.premium-patrol.premium-users.grant', grantPremiumForm.user_id), grantPremiumForm, {
        preserveScroll: true,
        onSuccess: () => { showGrantPremium.value = false; },
    });
}
</script>
