<template>
    <AdminShell
        title="Financial Control Centre"
        subtitle="Monitor escrow, revenue, ledger movements, payouts, and refunds with fintech-grade clarity."
    >
        <div class="space-y-5">
            <div v-if="isSuperAdmin" class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-sm transition hover:bg-primary-700"
                    @click="platformFeesOpen = true"
                >
                    Platform fee ledger
                </button>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div v-for="item in summaryItems" :key="item.label" class="rounded-3xl border p-4 shadow-sm transition" :class="[shell.card, changedKeys.includes(item.key) ? 'scale-[1.02] ring-2 ring-primary-400' : '']">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ item.label }}</p>
                    <p class="mt-2 text-2xl font-black" :class="shell.title">{{ item.value }}</p>
                    <p class="mt-1 text-[11px] font-bold" :class="shell.cardMuted">Auto-refreshes every 90 seconds</p>
                </div>
            </div>

            <AdminTabbedPage v-model="activeTab" :tabs="tabs" id-prefix="financial-tab" aria-label="Financial control sections">
            <AdminTabPanel :current-tab="activeTab" value="escrow" id-prefix="financial-tab" class="space-y-5">
                <div class="grid gap-3 md:grid-cols-3">
                    <div v-for="tile in escrow.tiles" :key="tile.label" class="rounded-3xl border p-4" :class="shell.card">
                        <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                        <p class="mt-2 text-xl font-black" :class="shell.title">{{ tile.value }}</p>
                    </div>
                </div>

                <AdminPanel title="Escrow balance trend" description="Total held escrow over the last 90 days.">
                    <LineChart :series="escrow.trend" />
                </AdminPanel>

                <AdminPanel title="Escrow management centre" description="Search and control every escrow-backed contract.">
                    <div class="mb-4 grid gap-3 md:grid-cols-[1fr_14rem]">
                        <input v-model="escrowFilters.q" type="search" placeholder="Search contract, quest, client, freelancer…" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="debouncedApplyEscrow" />
                        <select v-model="escrowFilters.status" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyEscrowFilters">
                            <option value="">All statuses</option>
                            <option v-for="status in escrowStatuses" :key="status" :value="status">{{ status.replace(/_/g, ' ') }}</option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                    <th class="px-3 py-3">Contract</th>
                                    <th class="px-3 py-3">Quest</th>
                                    <th class="px-3 py-3">Client</th>
                                    <th class="px-3 py-3">Freelancer</th>
                                    <th class="px-3 py-3">Escrow</th>
                                    <th class="px-3 py-3">Released</th>
                                    <th class="px-3 py-3">Held</th>
                                    <th class="px-3 py-3">Status</th>
                                    <th class="px-3 py-3">Funded</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <tr v-for="row in escrow.escrows.data" :key="row.id" class="cursor-pointer hover:bg-primary-50/60 dark:hover:bg-white/[0.03]" @click="openEscrow(row)">
                                    <td class="px-3 py-4 font-black">{{ row.contract_id }}</td>
                                    <td class="px-3 py-4 font-bold">{{ row.title }}</td>
                                    <td class="px-3 py-4">{{ row.client || '—' }}</td>
                                    <td class="px-3 py-4">{{ row.freelancer || '—' }}</td>
                                    <td class="px-3 py-4 font-black">{{ row.amount }}</td>
                                    <td class="px-3 py-4">{{ row.released }}</td>
                                    <td class="px-3 py-4 font-black">{{ row.held }}</td>
                                    <td class="px-3 py-4"><StatusPill :status="row.status" /></td>
                                    <td class="px-3 py-4 text-xs font-bold">{{ dateLabel(row.funded_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="revenue" id-prefix="financial-tab" class="space-y-5">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="kpi in revenue.kpis" :key="kpi.label" class="rounded-3xl border p-4" :class="shell.card">
                        <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ kpi.label }}</p>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <p class="text-2xl font-black" :class="shell.title">{{ kpi.value }}</p>
                            <span v-if="kpi.change" class="rounded-full px-2 py-1 text-xs font-black" :class="kpi.change.startsWith('+') ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">{{ kpi.change }}</span>
                        </div>
                        <Sparkline :series="kpi.sparkline" class="mt-4" />
                    </div>
                </div>

                <AdminPanel title="Monthly revenue mix" description="Stacked monthly revenue by service fees, featured listings, and dispute fees.">
                    <StackedBars :rows="revenue.monthly_stack" />
                </AdminPanel>

                <div class="grid gap-5 xl:grid-cols-2">
                    <BreakdownTable title="Revenue by category" :rows="revenue.category_rows" />
                    <BreakdownTable title="Revenue by Nigerian state" :rows="revenue.state_rows" />
                </div>

                <div class="rounded-3xl border p-5" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ revenue.mrr.label }}</p>
                    <p class="mt-2 text-3xl font-black" :class="shell.title">{{ revenue.mrr.value }}</p>
                    <p class="mt-1 text-sm font-bold text-amber-600">{{ revenue.mrr.status }}</p>
                </div>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="ledger" id-prefix="financial-tab" class="space-y-5">
                <AdminPanel title="Transaction ledger" description="Immutable append-only financial movement log.">
                    <div class="mb-4 grid gap-3 md:grid-cols-4">
                        <input v-model="ledgerFilters.q" type="search" placeholder="Transaction ID, user, quest…" class="rounded-2xl border px-4 py-3 text-sm font-semibold md:col-span-2" :class="shell.input" @input="debouncedApplyLedger" />
                        <select v-model="ledgerFilters.type" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyLedgerFilters">
                            <option value="">All types</option>
                            <option v-for="type in ledger.type_options" :key="type" :value="type">{{ type.replace(/_/g, ' ') }}</option>
                        </select>
                        <select v-model="ledgerFilters.source" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyLedgerFilters">
                            <option value="">All sources</option>
                            <option value="system">System generated</option>
                            <option value="admin">Admin initiated</option>
                        </select>
                        <AdminDateInput v-model="ledgerFilters.from" button-class="rounded-2xl border px-4 py-3 text-sm font-semibold" @change="applyLedgerFilters" />
                        <AdminDateInput v-model="ledgerFilters.to" button-class="rounded-2xl border px-4 py-3 text-sm font-semibold" @change="applyLedgerFilters" />
                        <input v-model="ledgerFilters.amount_min" type="number" min="0" placeholder="Min amount" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @change="applyLedgerFilters" />
                        <input v-model="ledgerFilters.amount_max" type="number" min="0" placeholder="Max amount" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @change="applyLedgerFilters" />
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                    <th class="px-3 py-3">Transaction ID</th>
                                    <th class="px-3 py-3">Date</th>
                                    <th class="px-3 py-3">Type</th>
                                    <th class="px-3 py-3">Quest / Contract</th>
                                    <th class="px-3 py-3">Client</th>
                                    <th class="px-3 py-3">Freelancer</th>
                                    <th class="px-3 py-3">Direction</th>
                                    <th class="px-3 py-3">Gross</th>
                                    <th class="px-3 py-3">Fee</th>
                                    <th class="px-3 py-3">Net</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <tr v-for="row in ledger.ledger.data" :key="row.id" class="cursor-pointer" :class="ledgerTypeClass(row.type)" @click="selectedTransaction = row">
                                    <td class="px-3 py-4 font-black">{{ row.reference }}</td>
                                    <td class="px-3 py-4">{{ dateLabel(row.occurred_at) }}</td>
                                    <td class="px-3 py-4 font-bold capitalize">{{ row.type.replace(/_/g, ' ') }}</td>
                                    <td class="px-3 py-4">{{ row.quest || '—' }} <span class="block text-[10px] font-black">{{ row.contract_id }}</span></td>
                                    <td class="px-3 py-4">{{ row.client || '—' }}</td>
                                    <td class="px-3 py-4">{{ row.freelancer || '—' }}</td>
                                    <td class="px-3 py-4">{{ row.direction.replace(/_/g, ' ') }}</td>
                                    <td class="px-3 py-4 font-black">{{ row.gross }}</td>
                                    <td class="px-3 py-4">{{ row.fee }}</td>
                                    <td class="px-3 py-4 font-black">{{ row.net }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="payouts" id-prefix="financial-tab" class="space-y-5">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-3xl border p-4" :class="shell.card">
                        <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Minimum payout</p>
                        <p class="mt-2 text-xl font-black" :class="shell.title">{{ payouts.summary?.minimum_payout ?? '—' }}</p>
                    </div>
                    <div class="rounded-3xl border p-4" :class="shell.card">
                        <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Pending withdrawals</p>
                        <p class="mt-2 text-xl font-black" :class="shell.title">{{ payouts.summary?.pending ?? '0' }}</p>
                    </div>
                </div>
                <AdminPanel title="Payout queue" description="Wallet withdrawal requests awaiting processing.">
                    <div class="mb-4 grid gap-3 md:grid-cols-[1fr_12rem]">
                        <input v-model="payoutFilters.q" type="search" placeholder="Search freelancer name or email…" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="debouncedApplyPayouts" />
                        <select v-model="payoutFilters.status" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyPayoutFilters">
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="overflow-x-auto rounded-2xl border" :class="shell.card">
                        <table class="min-w-full text-left text-sm" :class="['divide-y', shell.tableDivide]">
                            <thead class="text-[10px] font-black uppercase tracking-wider" :class="shell.tableHead">
                                <tr>
                                    <th class="px-4 py-3">Reference</th>
                                    <th class="px-4 py-3">User</th>
                                    <th class="px-4 py-3">Amount</th>
                                    <th class="px-4 py-3">Fee</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Requested</th>
                                </tr>
                            </thead>
                            <tbody :class="['divide-y', shell.tableDivide]">
                                <tr v-for="row in payouts.queue?.data || []" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-3 font-mono text-xs">{{ row.reference }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-bold">{{ row.user }}</p>
                                        <p class="text-xs text-slate-500">{{ row.email }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-black">{{ row.amount }}</td>
                                    <td class="px-4 py-3">{{ row.fee }}</td>
                                    <td class="px-4 py-3 capitalize">{{ row.status }}</td>
                                    <td class="px-4 py-3 text-xs" :class="shell.cardMuted">{{ dateLabel(row.created_at) }}</td>
                                </tr>
                                <tr v-if="!(payouts.queue?.data || []).length">
                                    <td colspan="6" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">No withdrawals in this view.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <nav v-if="payouts.queue?.links?.length > 3" class="mt-4 flex flex-wrap justify-center gap-2">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="link in payouts.queue.links"
                            :key="String(link.label) + (link.url || 'x')"
                            :href="link.url || undefined"
                            class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold"
                            :class="[link.active ? 'bg-primary-600 text-white' : 'border border-slate-200 dark:border-white/10', !link.url ? 'opacity-40' : '']"
                            preserve-state
                        >
                            <span v-html="link.label" />
                        </component>
                    </nav>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="refunds" id-prefix="financial-tab" class="rounded-3xl border p-6" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ activeSection }}</p>
                <h2 class="mt-2 text-2xl font-black" :class="shell.title">{{ placeholderMessage }}</h2>
                <p class="mt-2 text-sm font-semibold" :class="shell.cardMuted">This section is scaffolded into the Financial Control Centre and ready for payout/refund processor integrations.</p>
            </AdminTabPanel>
            </AdminTabbedPage>
        </div>

        <AdminSlideOver :open="escrowOpen" :title="selectedEscrow?.title || 'Escrow ledger'" eyebrow="Escrow account" @close="escrowOpen = false">
            <div v-if="escrowLoading" class="rounded-3xl border p-5 text-sm font-bold" :class="shell.card">Loading escrow ledger…</div>
            <div v-else-if="escrowLedger" class="space-y-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border p-3" :class="shell.card"><p class="text-xs font-black">Held balance</p><p class="text-xl font-black">{{ escrowLedger.controls.held }}</p></div>
                    <div class="rounded-2xl border p-3" :class="shell.card"><p class="text-xs font-black">Fee percent</p><p class="text-xl font-black">{{ escrowLedger.controls.fee_percent }}%</p></div>
                </div>
                <div class="space-y-3">
                    <article v-for="entry in escrowLedger.entries" :key="entry.reference" class="rounded-2xl border p-3 text-sm" :class="shell.card">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-black">{{ entry.description }}</p>
                                <p class="text-xs font-semibold" :class="shell.cardMuted">{{ entry.reference }} · {{ dateLabel(entry.occurred_at) }}</p>
                                <p v-if="entry.reason" class="mt-1 text-xs font-semibold">Reason: {{ entry.reason }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-black">{{ entry.amount }}</p>
                                <p class="text-xs font-semibold" :class="shell.cardMuted">Balance {{ entry.balance }}</p>
                            </div>
                        </div>
                    </article>
                </div>
                <form class="space-y-3 rounded-3xl border p-4" :class="shell.card" @submit.prevent="submitEscrowAction">
                    <h4 class="font-black">Admin controls</h4>
                    <select v-model="escrowAction.action" class="w-full rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                        <option value="manual_release">Manual Release</option>
                        <option value="manual_hold">Manual Hold</option>
                        <option value="freeze">Freeze for Dispute</option>
                        <option value="unfreeze">Unfreeze</option>
                        <option value="full_refund">Full Refund to Client</option>
                        <option value="partial_refund">Partial Refund</option>
                    </select>
                    <input v-model="escrowAction.milestone" type="text" placeholder="Milestone or full release label" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input v-model="escrowAction.amount" type="number" min="0" step="0.01" placeholder="Client/refund/release amount" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <input v-model="escrowAction.freelancer_amount" type="number" min="0" step="0.01" placeholder="Freelancer split amount" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    </div>
                    <p v-if="escrowAction.action === 'partial_refund'" class="rounded-2xl bg-slate-50 p-3 text-xs font-black dark:bg-white/5">
                        Split total: {{ splitTotalLabel }}. Held balance: {{ escrowLedger.controls.held }}.
                    </p>
                    <AdminDateInput v-model="escrowAction.expected_resolution_at" button-class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" />
                    <textarea v-model="escrowAction.reason" rows="3" required minlength="10" placeholder="Mandatory audit reason" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    <button type="submit" class="w-full rounded-2xl px-4 py-3 text-sm font-black uppercase" :class="shell.btnPrimary">Review and execute</button>
                </form>
            </div>
        </AdminSlideOver>

        <AdminSlideOver :open="Boolean(selectedTransaction)" :title="selectedTransaction?.reference || 'Transaction'" eyebrow="Transaction detail" @close="selectedTransaction = null">
            <dl v-if="selectedTransaction" class="space-y-4 text-sm">
                <div v-for="(value, key) in selectedTransaction" :key="key">
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ key.replace(/_/g, ' ') }}</dt>
                    <dd class="mt-1 font-semibold">{{ value || '—' }}</dd>
                </div>
            </dl>
        </AdminSlideOver>

        <AdminPlatformFeesPanel :open="platformFeesOpen" @close="platformFeesOpen = false" />
    </AdminShell>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminPlatformFeesPanel from '@/Components/Admin/AdminPlatformFeesPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminTabbedPage from '@/Components/Admin/AdminTabbedPage.vue';
import { useTabState } from '@/composables/useTabState';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, defineComponent, h, reactive, ref, watch } from 'vue';

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.user?.role?.slug === 'super_admin');
const platformFeesOpen = ref(false);

const props = defineProps({
    section: { type: String, required: true },
    summary: { type: Object, required: true },
    escrow: { type: Object, required: true },
    revenue: { type: Object, required: true },
    ledger: { type: Object, required: true },
    payouts: { type: Object, default: () => ({}) },
    refunds: { type: Object, default: () => ({}) },
});

const { shell } = useInjectedAdminTheme();
const liveSummary = ref(props.summary);
const changedKeys = ref([]);
const escrowOpen = ref(false);
const escrowLoading = ref(false);
const selectedEscrow = ref(null);
const escrowLedger = ref(null);
const selectedTransaction = ref(null);

const tabs = [
    { key: 'escrow', label: 'Escrow Management' },
    { key: 'revenue', label: 'Revenue Dashboard' },
    { key: 'ledger', label: 'Transaction Ledger' },
    { key: 'payouts', label: 'Payout Queue' },
    { key: 'refunds', label: 'Refunds' },
];
const { activeTab } = useTabState(tabs.map((tab) => tab.key), props.section || 'escrow');
watch(
    () => props.section,
    (section) => {
        if (section && tabs.some((tab) => tab.key === section)) {
            activeTab.value = section;
        }
    },
);
const activeSection = computed(() => activeTab.value);

const escrowStatuses = ['awaiting_funding', 'funded', 'partially_released', 'released', 'held', 'frozen', 'disputed', 'refunded'];
const escrowFilters = reactive({ q: props.escrow.filters?.q || '', status: props.escrow.filters?.status || '' });
const payoutFilters = reactive({
    q: props.payouts?.filters?.q || '',
    status: props.payouts?.filters?.status || '',
});
const ledgerFilters = reactive({
    q: props.ledger.filters?.q || '',
    type: props.ledger.filters?.type || '',
    source: props.ledger.filters?.source || '',
    from: props.ledger.filters?.from || '',
    to: props.ledger.filters?.to || '',
    amount_min: props.ledger.filters?.amount_min || '',
    amount_max: props.ledger.filters?.amount_max || '',
});
const escrowAction = reactive({ action: 'manual_release', milestone: '', amount: '', freelancer_amount: '', expected_resolution_at: '', reason: '' });

const summaryItems = computed(() => [
    { key: 'escrow_balance_minor', label: 'Total platform escrow', value: liveSummary.value.escrow_balance },
    { key: 'month_revenue_minor', label: 'Revenue this month', value: liveSummary.value.month_revenue },
    { key: 'payouts_today_minor', label: 'Payouts today', value: liveSummary.value.payouts_today },
    { key: 'frozen_funds_minor', label: 'Frozen funds', value: liveSummary.value.frozen_funds },
]);
const placeholderMessage = computed(() => props[activeSection.value]?.message || 'Financial workflow queue');
const splitTotalLabel = computed(() => moneyLabel((Number(escrowAction.amount || 0) + Number(escrowAction.freelancer_amount || 0)) * 100));

let filterTimer = null;
setInterval(refreshSummary, 90000);

async function refreshSummary() {
    const { data } = await window.axios.get(route('admin.financial.summary'));
    changedKeys.value = Object.keys(data).filter((key) => key.endsWith('_minor') && data[key] !== liveSummary.value[key]);
    liveSummary.value = data;
    setTimeout(() => (changedKeys.value = []), 1600);
}

function applyEscrowFilters() {
    router.get(route('admin.financial.index'), { tab: 'escrow', ...clean(escrowFilters) }, { preserveScroll: true, preserveState: true });
}

function debouncedApplyEscrow() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyEscrowFilters, 250);
}

function applyLedgerFilters() {
    router.get(route('admin.financial.index'), { tab: 'ledger', ...clean(ledgerFilters) }, { preserveScroll: true, preserveState: true });
}

function debouncedApplyLedger() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyLedgerFilters, 250);
}

function applyPayoutFilters() {
    router.get(route('admin.financial.index'), { tab: 'payouts', ...clean(payoutFilters) }, { preserveScroll: true, preserveState: true });
}

function debouncedApplyPayouts() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyPayoutFilters, 250);
}

async function openEscrow(row) {
    selectedEscrow.value = row;
    escrowOpen.value = true;
    escrowLoading.value = true;
    const { data } = await window.axios.get(route('admin.financial.escrows.ledger', row.id));
    escrowLedger.value = data;
    escrowLoading.value = false;
}

async function submitEscrowAction() {
    if (!window.confirm(`Execute ${escrowAction.action.replace(/_/g, ' ')} on this escrow? This will be recorded in the immutable ledger.`)) {
        return;
    }

    const { data } = await window.axios.post(route('admin.financial.escrows.action', selectedEscrow.value.id), escrowAction);
    escrowLedger.value = data.ledger;
    Object.assign(escrowAction, { action: 'manual_release', milestone: '', amount: '', freelancer_amount: '', expected_resolution_at: '', reason: '' });
    router.reload({ only: ['summary', 'escrow', 'ledger'] });
}

function clean(obj) {
    const out = {};
    Object.entries(obj).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) out[key] = value;
    });
    return out;
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function moneyLabel(minor) {
    return `₦${(Number(minor || 0) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function ledgerTypeClass(type) {
    if (['payout', 'milestone_release'].includes(type)) return 'bg-emerald-50/70 dark:bg-emerald-400/5';
    if (type === 'escrow_funding') return 'bg-sky-50/70 dark:bg-sky-400/5';
    if (type === 'platform_fee' || type === 'featured_listing_payment') return 'bg-amber-50/70 dark:bg-amber-400/5';
    if (type.includes('refund') || type.includes('dispute')) return 'bg-rose-50/70 dark:bg-rose-400/5';
    return '';
}

const StatusPill = defineComponent({
    props: { status: { type: String, required: true } },
    setup(p) {
        return () => h('span', { class: 'rounded-full bg-slate-100 px-3 py-1 text-xs font-black capitalize text-slate-700 dark:bg-white/10 dark:text-slate-200' }, p.status.replace(/_/g, ' '));
    },
});

const Sparkline = defineComponent({
    props: { series: { type: Array, default: () => [] } },
    setup(p) {
        return () => h('div', { class: 'flex h-10 items-end gap-0.5' }, p.series.map((point) => h('span', {
            class: 'flex-1 rounded-t bg-primary-500/70',
            style: { height: `${Math.max(8, Math.min(100, Number(point.value || 0)))}%` },
            title: `${point.label}: ${point.value}`,
        })));
    },
});

const LineChart = defineComponent({
    props: { series: { type: Array, default: () => [] } },
    setup(p) {
        return () => h('div', { class: 'flex h-56 items-end gap-1 rounded-3xl bg-slate-50 p-4 dark:bg-white/5' }, p.series.map((point) => h('span', {
            class: 'flex-1 rounded-t bg-primary-600/80',
            style: { height: `${Math.max(2, Math.min(100, Number(point.value || 0) / Math.max(1, Math.max(...p.series.map((x) => Number(x.value || 0)))) * 100))}%` },
            title: `${point.label}: ${point.value}`,
        })));
    },
});

const StackedBars = defineComponent({
    props: { rows: { type: Array, default: () => [] } },
    setup(p) {
        return () => h('div', { class: 'space-y-3' }, p.rows.map((row) => {
            const total = Math.max(1, Number(row.service_fee || 0) + Number(row.featured_listing || 0) + Number(row.dispute_fee || 0));
            return h('div', { class: 'grid grid-cols-[5rem_1fr] items-center gap-3 text-xs font-bold' }, [
                h('span', row.label),
                h('div', { class: 'flex h-7 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10' }, [
                    h('span', { class: 'bg-primary-600', style: { width: `${(Number(row.service_fee || 0) / total) * 100}%` }, title: `Service fee: ${row.service_fee}` }),
                    h('span', { class: 'bg-amber-500', style: { width: `${(Number(row.featured_listing || 0) / total) * 100}%` }, title: `Featured: ${row.featured_listing}` }),
                    h('span', { class: 'bg-rose-500', style: { width: `${(Number(row.dispute_fee || 0) / total) * 100}%` }, title: `Dispute: ${row.dispute_fee}` }),
                ]),
            ]);
        }));
    },
});

const BreakdownTable = defineComponent({
    props: { title: { type: String, required: true }, rows: { type: Array, default: () => [] } },
    setup(p) {
        return () => h(AdminPanel, { title: p.title }, () => h('div', { class: 'overflow-x-auto' }, h('table', { class: 'min-w-full text-xs' }, [
            h('thead', [h('tr', { class: 'text-left font-black uppercase tracking-wider text-slate-500' }, ['Name', 'Contracts', 'GMV', 'Service fee', 'Fee %', 'MoM'].map((x) => h('th', { class: 'px-3 py-2' }, x)))]),
            h('tbody', { class: 'divide-y divide-slate-100 dark:divide-white/10' }, p.rows.map((row) => h('tr', [
                h('td', { class: 'px-3 py-3 font-black' }, row.label),
                h('td', { class: 'px-3 py-3' }, row.contracts_completed),
                h('td', { class: 'px-3 py-3 font-black' }, row.gmv),
                h('td', { class: 'px-3 py-3' }, row.service_fee),
                h('td', { class: 'px-3 py-3' }, row.fee_percent),
                h('td', { class: 'px-3 py-3' }, row.mom_change),
            ]))),
        ])));
    },
});
</script>
