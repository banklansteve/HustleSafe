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
                        <button type="button" class="rounded-lg border px-3 py-1.5 text-[10px] font-black uppercase" :class="shell.btnGhost" @click="openDismiss(alert)">Dismiss</button>
                        <button type="button" class="rounded-lg bg-amber-600 px-3 py-1.5 text-[10px] font-black uppercase text-white" @click="reviewAlert(alert)">Investigate</button>
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
                                <th class="px-4 py-3">Trust</th>
                                <th class="px-4 py-3">Risk</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in premium_users.data"
                                :key="row.user_id"
                                class="border-b cursor-pointer hover:bg-slate-50/80 dark:hover:bg-slate-900/40"
                                :class="[shell.tableDivide, row.has_open_flags ? 'bg-orange-50/60 dark:bg-orange-950/20' : '']"
                                @click="openPremiumDetail(row.user_id)"
                            >
                                <td class="px-4 py-3 font-bold" :class="shell.cardTitle">{{ row.fullname }}</td>
                                <td class="px-4 py-3 text-xs" :class="shell.cardMuted">{{ formatWhen(row.signup_date) }}</td>
                                <td class="px-4 py-3">L{{ row.verification_tier }}</td>
                                <td class="px-4 py-3">{{ row.subscription_type }}</td>
                                <td class="px-4 py-3 font-black">{{ row.cost_paid_display }}</td>
                                <td class="px-4 py-3 text-xs font-bold text-emerald-700">{{ row.trust_score ?? '—' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[10px] font-black" :class="riskClass(row.risk_score)">{{ row.risk_score }}</span></td>
                                <td class="px-4 py-3 text-xs font-black uppercase">{{ row.patrol_status }}</td>
                                <td class="px-4 py-3">
                                    <button type="button" class="text-xs font-black text-primary-700" @click.stop="openPremiumDetail(row.user_id)">View</button>
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

        <PremiumUserReviewPanel
            :open="reviewOpen"
            :user-id="reviewUserId"
            :reason-codes="reason_codes"
            @close="reviewOpen = false"
            @action-complete="onActionComplete"
        />

        <PremiumPatrolGrantPremiumModal
            :open="showGrantPremium"
            @close="showGrantPremium = false"
            @upgraded="onPremiumUpgraded"
        />

        <div v-if="dismissAlert" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4" @click.self="dismissAlert = null">
            <form class="w-full max-w-md rounded-2xl border p-5" :class="shell.card" @submit.prevent="submitDismiss">
                <h3 class="text-base font-black" :class="shell.cardTitle">Dismiss anomaly</h3>
                <p class="mt-2 text-sm" :class="shell.cardMuted">{{ dismissAlert.message }}</p>
                <textarea v-model="dismissReason" required rows="3" class="mt-4 w-full rounded-xl border px-3 py-2 text-sm" :class="shell.input" placeholder="Dismissal reason…" />
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white dark:bg-white dark:text-slate-900" :disabled="dismissSubmitting">
                        <span v-if="dismissSubmitting" class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                        Dismiss
                    </button>
                    <button type="button" class="rounded-xl border px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost" @click="dismissAlert = null">Cancel</button>
                </div>
            </form>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminKpiTile from '@/Components/Admin/AdminKpiTile.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import PremiumPatrolGrantPremiumModal from '@/Pages/Admin/PremiumPatrol/Components/PremiumPatrolGrantPremiumModal.vue';
import PremiumUserReviewPanel from '@/Pages/Admin/PremiumPatrol/Components/PremiumUserReviewPanel.vue';
import { useFlashToastWatcher } from '@/composables/useFlashToast';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { useOperationsToast } from '@/composables/useOperationsToast';
import AdminShell from '@/Layouts/AdminShell.vue';
import { router } from '@inertiajs/vue3';
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
const { toast } = useOperationsToast();
useFlashToastWatcher(toast);

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

const reviewOpen = ref(false);
const reviewUserId = ref(null);
const showGrantPremium = ref(false);

const dismissAlert = ref(null);
const dismissReason = ref('');
const dismissSubmitting = ref(false);

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

function openPremiumDetail(userId) {
    reviewUserId.value = userId;
    reviewOpen.value = true;
}

function openBoostDetail() {
    toast('Boost detail panel coming next — use Quest Boosts admin for now.', 'info');
}

function reviewAlert(alert) {
    if (alert.subject_type === 'premium_user' && alert.subject_id) {
        openPremiumDetail(alert.subject_id);
    }
}

function openDismiss(alert) {
    dismissAlert.value = alert;
    dismissReason.value = '';
}

function submitDismiss() {
    if (!dismissAlert.value || dismissSubmitting.value) return;
    dismissSubmitting.value = true;
    router.post(route('admin.api.premium-patrol.flags.dismiss', dismissAlert.value.id), { reason: dismissReason.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast('Anomaly dismissed.');
            dismissAlert.value = null;
        },
        onFinish: () => {
            dismissSubmitting.value = false;
        },
    });
}

function onPremiumUpgraded(name) {
    toast(`${name} upgraded to premium successfully.`);
    router.reload({ only: ['premium_users', 'metrics', 'anomaly_alerts'], preserveScroll: true });
}

function onActionComplete(action) {
    const labels = {
        suspend: 'Premium subscription suspended.',
        refund: 'Premium charge refunded.',
        investigate: 'Investigation case opened.',
        watchlist: 'User added to watchlist.',
    };
    toast(labels[action] || 'Action completed.');
    router.reload({ only: ['premium_users', 'anomaly_alerts'], preserveScroll: true });
}
</script>
