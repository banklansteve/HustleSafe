<template>
    <AdminShell
        title="Financial audit"
        subtitle="Every naira traceable — escrow held, fees recognised, VAT accrued, and the double-entry ledger that proves it."
    >
        <div class="space-y-6">
            <FinancialAuditNav active="overview" :open-exceptions="data.reconciliation?.open_exceptions ?? 0" />

            <!-- Critical alerts -->
            <div v-if="!data.ledger_balanced" class="rounded-2xl border border-rose-300 bg-rose-50 p-4 text-sm font-bold text-rose-900 dark:bg-rose-950/40 dark:text-rose-100">
                Critical: ledger debits and credits do not balance (variance {{ money(data.ledger_balance_check?.variance_minor) }}). All reconciliation is halted until resolved.
            </div>
            <div v-else-if="!data.escrow_position?.position_matches_ledger" class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm font-bold text-amber-950">
                Escrow position mismatch: held escrows {{ data.escrow_position.total_held_display }} vs ledger liability {{ data.escrow_position.ledger_liability_display }} (variance {{ data.escrow_position.variance_display }}).
            </div>

            <!-- Period filter -->
            <section class="rounded-3xl border p-4 shadow-sm sm:p-5" :class="shell.card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.label">Reporting period</p>
                        <p class="mt-1 text-sm font-semibold" :class="shell.cardMuted">{{ data.period?.label }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in presets"
                            :key="preset.key"
                            type="button"
                            class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide transition"
                            :class="activePreset === preset.key ? 'bg-primary-600 text-white' : shell.btnGhost"
                            @click="applyPreset(preset.key)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="text-[10px] font-black uppercase text-slate-400">From</label>
                            <input v-model="periodFrom" type="date" class="mt-1 block rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase text-slate-400">To</label>
                            <input v-model="periodTo" type="date" class="mt-1 block rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                        </div>
                        <button type="button" class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black uppercase text-white dark:bg-white dark:text-slate-900" @click="applyCustomPeriod">
                            Apply
                        </button>
                    </div>
                </div>
            </section>

            <!-- Period totals -->
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div v-for="tile in periodTiles" :key="tile.label" class="rounded-2xl border p-4 shadow-sm" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                    <p class="mt-2 text-xl font-black sm:text-2xl" :class="shell.title">{{ tile.value }}</p>
                    <p v-if="tile.hint" class="mt-1 text-xs font-semibold" :class="shell.cardMuted">{{ tile.hint }}</p>
                </div>
            </section>

            <!-- Live position + reconciliation -->
            <section class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-3xl border border-teal-200 bg-gradient-to-br from-teal-50/90 to-white p-5 dark:border-teal-900/40 dark:from-teal-950/30 dark:to-transparent lg:col-span-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-teal-800 dark:text-teal-200">Escrow held right now</p>
                    <p class="mt-2 font-display text-3xl font-black text-slate-950 dark:text-white">{{ data.escrow_position?.total_held_display }}</p>
                    <ul class="mt-4 space-y-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                        <li>{{ data.escrow_position?.active_count }} active · oldest {{ data.escrow_position?.oldest_active_days }} days</li>
                        <li>{{ data.escrow_position?.disputed_count }} disputed · {{ data.escrow_position?.disputed_total_display }}</li>
                        <li>Ledger liability: {{ data.escrow_position?.ledger_liability_display }}</li>
                    </ul>
                    <Link :href="ledgerHeldUrl" class="mt-4 inline-flex text-xs font-black uppercase text-teal-800 underline dark:text-teal-200">View all held escrows →</Link>
                </div>

                <div
                    class="rounded-3xl border p-5 shadow-sm lg:col-span-1"
                    :class="[
                        shell.card,
                        reconciliationAlert ? 'border-amber-400 bg-amber-50/40' : '',
                        data.reconciliation?.last_run_status === 'failed' ? 'border-rose-400 bg-rose-50/30' : '',
                    ]"
                >
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Reconciliation</p>
                    <p class="mt-2 text-lg font-black capitalize" :class="shell.title">{{ data.reconciliation?.last_run_status || '—' }}</p>
                    <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Last run: {{ lastRunLabel }}</p>
                    <p class="text-xs font-semibold" :class="shell.cardMuted">{{ data.reconciliation?.open_exceptions ?? 0 }} open exception(s)</p>
                    <Link :href="route('admin.financial-audit.exceptions.index')" class="mt-4 inline-flex rounded-full bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white dark:bg-white dark:text-slate-900">
                        Exceptions queue
                    </Link>
                </div>

                <div class="rounded-3xl border p-5 shadow-sm lg:col-span-1" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Freelancer wallets</p>
                    <p class="mt-2 text-2xl font-black" :class="shell.title">{{ data.freelancer_wallets?.total_balance_display }}</p>
                    <ul class="mt-3 space-y-1 text-xs font-semibold" :class="shell.cardMuted">
                        <li>{{ data.freelancer_wallets?.pending_withdrawal_count }} pending ({{ data.freelancer_wallets?.pending_withdrawal_display }})</li>
                        <li>Withdrawn today: {{ data.freelancer_wallets?.withdrawn_today_display }}</li>
                        <li>Withdrawn this month: {{ data.freelancer_wallets?.withdrawn_month_display }}</li>
                    </ul>
                </div>
            </section>

            <!-- Ledger accounts -->
            <AdminPanel title="Double-entry ledger accounts" description="Live balances from immutable journal entries. Gateway suspense should net to zero after reconciliation.">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    <div
                        v-for="account in data.ledger_accounts"
                        :key="account.key"
                        class="rounded-2xl border p-3"
                        :class="[shell.card, account.warning ? 'border-amber-400 bg-amber-50/50' : '']"
                    >
                        <p class="text-[9px] font-black uppercase leading-tight tracking-wide text-slate-500">{{ account.label }}</p>
                        <p class="mt-2 text-sm font-black" :class="shell.title">{{ account.balance_display }}</p>
                        <p v-if="account.warning" class="mt-1 text-[10px] font-bold text-amber-800">Investigate</p>
                    </div>
                </div>
            </AdminPanel>

            <!-- Cash flow chart -->
            <AdminPanel title="Cash flow" :description="`Daily inflows, outflows, platform fees and VAT for ${data.period?.label}.`">
                <VueApexCharts type="line" height="320" :options="cashFlowOptions" :series="cashFlowSeries" />
            </AdminPanel>

            <!-- Active escrows -->
            <AdminPanel
                title="Active escrow funds"
                description="Every naira currently held — who paid, who earns, projected fee, VAT, and freelancer net on release."
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                <th class="px-3 py-2">Escrow ref</th>
                                <th class="px-3 py-2">Client (paid)</th>
                                <th class="px-3 py-2">Freelancer (earns)</th>
                                <th class="px-3 py-2">Quest</th>
                                <th class="px-3 py-2">Funded</th>
                                <th class="px-3 py-2">Due</th>
                                <th class="px-3 py-2">Gross</th>
                                <th class="px-3 py-2">Fee</th>
                                <th class="px-3 py-2">VAT</th>
                                <th class="px-3 py-2">Freelancer net</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <tr
                                v-for="row in data.active_escrows"
                                :key="row.id"
                                class="cursor-pointer hover:bg-primary-50/40 dark:hover:bg-white/[0.03]"
                                @click="openRecord(row.id)"
                            >
                                <td class="px-3 py-3 font-black">{{ row.escrow_reference }}</td>
                                <td class="px-3 py-3">{{ row.client_name }}</td>
                                <td class="px-3 py-3">{{ row.freelancer_name }}</td>
                                <td class="max-w-[10rem] truncate px-3 py-3 font-semibold">{{ row.quest_title }}</td>
                                <td class="px-3 py-3 text-xs whitespace-nowrap">{{ dateLabel(row.funded_at) }}</td>
                                <td class="px-3 py-3 text-xs whitespace-nowrap">
                                    <span :class="row.is_overdue ? 'font-black text-rose-600' : ''">{{ row.due_date_label || '—' }}</span>
                                </td>
                                <td class="px-3 py-3 font-black">{{ row.funded_display }}</td>
                                <td class="px-3 py-3 text-xs">{{ row.platform_fee_display }} <span class="text-slate-400">({{ row.platform_fee_percent }}%)</span></td>
                                <td class="px-3 py-3 text-xs">{{ row.vat_display }}</td>
                                <td class="px-3 py-3 font-black text-emerald-700 dark:text-emerald-300">{{ row.freelancer_net_display }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.status)">{{ row.status_label }}</span>
                                </td>
                            </tr>
                            <tr v-if="!data.active_escrows?.length">
                                <td colspan="11" class="px-3 py-8 text-center text-sm font-semibold text-slate-500">No active escrow funds at the moment.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AdminPanel>

            <!-- Period fundings -->
            <AdminPanel
                :title="`Escrow funded in period`"
                :description="`${data.period?.escrow_funding_count} funding event(s) between ${data.period?.label}.`"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                <th class="px-3 py-2">Escrow ref</th>
                                <th class="px-3 py-2">Paystack ref</th>
                                <th class="px-3 py-2">Client</th>
                                <th class="px-3 py-2">Freelancer</th>
                                <th class="px-3 py-2">Funded</th>
                                <th class="px-3 py-2">Amount</th>
                                <th class="px-3 py-2">Proj. fee</th>
                                <th class="px-3 py-2">Proj. VAT</th>
                                <th class="px-3 py-2">Proj. net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <tr
                                v-for="row in data.period_fundings"
                                :key="row.id"
                                class="cursor-pointer hover:bg-primary-50/40"
                                @click="openRecord(row.id)"
                            >
                                <td class="px-3 py-3 font-black">{{ row.escrow_reference }}</td>
                                <td class="px-3 py-3 font-mono text-xs">{{ row.paystack_reference || '—' }}</td>
                                <td class="px-3 py-3">{{ row.client_name }}</td>
                                <td class="px-3 py-3">{{ row.freelancer_name }}</td>
                                <td class="px-3 py-3 text-xs">{{ dateLabel(row.funded_at) }}</td>
                                <td class="px-3 py-3 font-black">{{ row.funded_display }}</td>
                                <td class="px-3 py-3">{{ row.platform_fee_display }}</td>
                                <td class="px-3 py-3">{{ row.vat_display }}</td>
                                <td class="px-3 py-3">{{ row.freelancer_net_display }}</td>
                            </tr>
                            <tr v-if="!data.period_fundings?.length">
                                <td colspan="9" class="px-3 py-8 text-center text-sm font-semibold text-slate-500">No escrow funding in this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AdminPanel>

            <!-- Revenue + VAT side panels -->
            <section class="grid gap-4 lg:grid-cols-2">
                <AdminPanel title="Platform fee revenue (calendar)" description="Recognised when escrow releases — independent of the period filter above.">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div><dt class="text-xs font-bold text-slate-500">Today</dt><dd class="text-lg font-black">{{ data.revenue?.today_display }}</dd></div>
                        <div><dt class="text-xs font-bold text-slate-500">This week</dt><dd class="text-lg font-black">{{ data.revenue?.week_display }}</dd></div>
                        <div><dt class="text-xs font-bold text-slate-500">This month</dt><dd class="text-lg font-black">{{ data.revenue?.month_display }} <span class="text-xs" :class="changeClass(data.revenue?.mom_change_percent)">MoM {{ formatChange(data.revenue?.mom_change_percent) }}</span></dd></div>
                        <div><dt class="text-xs font-bold text-slate-500">This year</dt><dd class="text-lg font-black">{{ data.revenue?.year_display }} <span class="text-xs" :class="changeClass(data.revenue?.yoy_change_percent)">YoY {{ formatChange(data.revenue?.yoy_change_percent) }}</span></dd></div>
                    </dl>
                </AdminPanel>

                <AdminPanel title="VAT payable to FIRS" description="7.5% of platform fee on each release — never commingled with platform revenue.">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div><dt class="text-xs font-bold text-slate-500">Today accrued</dt><dd class="text-lg font-black">{{ data.vat?.today_display }}</dd></div>
                        <div><dt class="text-xs font-bold text-slate-500">This month</dt><dd class="text-lg font-black">{{ data.vat?.month_display }}</dd></div>
                        <div><dt class="text-xs font-bold text-slate-500">{{ data.vat?.current_quarter_label }}</dt><dd class="text-lg font-black">{{ data.vat?.current_quarter_payable_display }}</dd></div>
                        <div><dt class="text-xs font-bold text-slate-500">{{ data.vat?.previous_quarter_label }}</dt><dd class="text-lg font-black">{{ data.vat?.previous_quarter_payable_display }}</dd></div>
                    </dl>
                    <form class="mt-4 grid gap-2 border-t pt-4 sm:grid-cols-2" @submit.prevent="submitVatRemittance">
                        <p class="sm:col-span-2 text-[10px] font-black uppercase tracking-wide text-slate-500">Mark quarter as remitted</p>
                        <input v-model="vatForm.quarter_label" type="text" placeholder="Q1 2026" class="rounded-xl border px-3 py-2 text-xs font-semibold" :class="shell.input" required />
                        <input v-model="vatForm.remittance_reference" type="text" placeholder="FIRS reference" class="rounded-xl border px-3 py-2 text-xs font-semibold" :class="shell.input" required />
                        <input v-model="vatForm.amount_major" type="number" step="0.01" min="0.01" placeholder="Amount ₦" class="rounded-xl border px-3 py-2 text-xs font-semibold" :class="shell.input" required />
                        <button type="submit" class="rounded-xl bg-primary-600 px-3 py-2 text-xs font-black uppercase text-white" :disabled="vatForm.processing">Record</button>
                    </form>
                </AdminPanel>
            </section>

            <!-- Recent reconciliation runs -->
            <AdminPanel title="Reconciliation history" description="Hourly automated checks — gateway match, ledger balance, escrow position.">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                <th class="px-3 py-2">Started</th>
                                <th class="px-3 py-2">Duration</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Processed</th>
                                <th class="px-3 py-2">Exceptions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <tr v-for="run in data.recent_reconciliation_runs" :key="run.id">
                                <td class="px-3 py-3 text-xs whitespace-nowrap">{{ dateLabel(run.started_at) }}</td>
                                <td class="px-3 py-3 text-xs">{{ run.duration_seconds }}s</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="run.passed ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">{{ run.passed ? 'Passed' : 'Failed' }}</span>
                                </td>
                                <td class="px-3 py-3 text-xs">{{ run.records_processed }}</td>
                                <td class="px-3 py-3 text-xs font-bold" :class="run.exceptions_found > 0 ? 'text-amber-700' : ''">{{ run.exceptions_found }}</td>
                            </tr>
                            <tr v-if="!data.recent_reconciliation_runs?.length">
                                <td colspan="5" class="px-3 py-8 text-center text-sm font-semibold text-slate-500">No reconciliation runs logged yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AdminPanel>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import FinancialAuditNav from '@/Components/Admin/FinancialAuditNav.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    overview: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

const { shell, chartMode, isDark } = useInjectedAdminTheme();
const data = reactive({ ...props.overview });

const periodFrom = ref(data.filters?.from ?? '');
const periodTo = ref(data.filters?.to ?? '');
const activePreset = ref('');

const presets = [
    { key: 'today', label: 'Today' },
    { key: '7d', label: '7 days' },
    { key: '30d', label: '30 days' },
    { key: 'month', label: 'This month' },
    { key: 'quarter', label: 'This quarter' },
    { key: 'year', label: 'This year' },
];

const vatForm = useForm({
    quarter_label: data.vat?.current_quarter_label ?? '',
    period_start: data.filters?.from ?? '',
    period_end: data.filters?.to ?? '',
    amount_major: '',
    remittance_reference: '',
    remitted_at: new Date().toISOString().slice(0, 10),
    notes: '',
});

let pollTimer;

const periodTiles = computed(() => [
    { label: 'Escrow funded', value: data.period?.escrow_funded_display, hint: `${data.period?.escrow_funding_count ?? 0} funding(s)` },
    { label: 'Platform fees', value: data.period?.platform_fee_display, hint: 'Recognised on release' },
    { label: 'VAT accrued', value: data.period?.vat_display, hint: '7.5% of platform fee' },
    { label: 'Released to hustlers', value: data.period?.released_to_freelancers_display, hint: `${data.period?.released_count ?? 0} release(s)` },
    { label: 'Refunds & payouts', value: data.period?.refunded_display, hint: `Withdrawals ${data.period?.withdrawals_display}` },
]);

const reconciliationAlert = computed(() => (data.reconciliation?.open_exceptions ?? 0) > 0 || data.reconciliation?.last_run_status === 'failed');

const ledgerHeldUrl = computed(() => route('admin.financial-audit.escrow-ledger', { status: 'held' }));

const lastRunLabel = computed(() => {
    const at = data.reconciliation?.last_run_at;
    if (!at) return 'Never';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(at));
});

const cashFlowSeries = computed(() => [
    { name: 'Escrow funded', type: 'column', data: (data.cash_flow ?? []).map((p) => p.inflow_minor / 100) },
    { name: 'Outflows', type: 'column', data: (data.cash_flow ?? []).map((p) => p.outflow_minor / 100) },
    { name: 'Platform fees', type: 'line', data: (data.cash_flow ?? []).map((p) => p.fee_minor / 100) },
    { name: 'VAT', type: 'line', data: (data.cash_flow ?? []).map((p) => p.vat_minor / 100) },
]);

const cashFlowOptions = computed(() => ({
    chart: { toolbar: { show: true }, fontFamily: 'inherit', foreColor: isDark.value ? '#94a3b8' : '#64748b' },
    theme: { mode: chartMode.value },
    stroke: { width: [0, 0, 3, 3], curve: 'smooth' },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
    colors: ['#0ea5e9', '#f43f5e', '#f59e0b', '#6366f1'],
    xaxis: { categories: (data.cash_flow ?? []).map((p) => p.label) },
    yaxis: { labels: { formatter: (v) => `₦${Number(v).toLocaleString()}` } },
    legend: { position: 'top' },
    tooltip: { theme: chartMode.value, y: { formatter: (v) => `₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}` } },
}));

function money(minor) {
    return `₦${(Number(minor || 0) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatChange(value) {
    if (value === null || value === undefined) return '—';
    return `${value >= 0 ? '+' : ''}${value}%`;
}

function changeClass(value) {
    if (value > 0) return 'text-emerald-600';
    if (value < 0) return 'text-rose-600';
    return 'text-slate-400';
}

function statusClass(status) {
    const map = {
        held: 'bg-sky-100 text-sky-800',
        disputed: 'bg-amber-100 text-amber-900',
        released: 'bg-emerald-100 text-emerald-800',
        refunded: 'bg-rose-100 text-rose-800',
    };
    return map[status] ?? 'bg-slate-100 text-slate-700';
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function openRecord(id) {
    router.visit(route('admin.financial-audit.escrow-records.show', id));
}

function applyPreset(key) {
    activePreset.value = key;
    router.get(route('admin.financial-audit.index'), { preset: key }, { preserveScroll: true, preserveState: false });
}

function applyCustomPeriod() {
    activePreset.value = '';
    router.get(route('admin.financial-audit.index'), { from: periodFrom.value, to: periodTo.value }, { preserveScroll: true, preserveState: false });
}

async function refreshDashboard() {
    try {
        const params = activePreset.value ? { preset: activePreset.value } : { from: periodFrom.value, to: periodTo.value };
        const { data: payload } = await window.axios.get(route('admin.financial-audit.api.dashboard'), { params });
        Object.assign(data, payload);
    } catch {
        /* silent */
    }
}

function submitVatRemittance() {
    vatForm.transform((form) => ({
        ...form,
        amount_minor: Math.round(Number(form.amount_major) * 100),
    })).post(route('admin.financial-audit.vat.remit'), {
        preserveScroll: true,
        onSuccess: () => {
            vatForm.reset('amount_major', 'remittance_reference', 'notes');
            refreshDashboard();
        },
    });
}

watch(() => props.overview, (val) => Object.assign(data, val), { deep: true });

onMounted(() => {
    pollTimer = setInterval(refreshDashboard, 60000);
});

onUnmounted(() => {
    clearInterval(pollTimer);
});
</script>
