<template>
    <component :is="shellComponent" title="Contract management" subtitle="Patrol active contracts, review delivery quality, and escalate issues to super admin.">
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-xl px-4 py-2 text-xs font-black uppercase transition"
                :class="activeTab === tab.key ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="toast" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ toast }}</div>

        <!-- Overview -->
        <div v-if="activeTab === 'overview'" class="space-y-5">
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                    <p class="text-[10px] font-black uppercase text-slate-500">Active contracts</p>
                    <p class="mt-1 text-2xl font-black">{{ overview.active_contracts }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
                    <p class="text-[10px] font-black uppercase text-amber-800">Awaiting approval</p>
                    <p class="mt-1 text-2xl font-black text-amber-950">{{ overview.awaiting_approval }}</p>
                </div>
                <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-4">
                    <p class="text-[10px] font-black uppercase text-rose-800">In dispute</p>
                    <p class="mt-1 text-2xl font-black text-rose-950">{{ overview.in_dispute }}</p>
                </div>
                <div class="rounded-2xl border border-orange-200 bg-orange-50/60 p-4">
                    <p class="text-[10px] font-black uppercase text-orange-800">Overdue</p>
                    <p class="mt-1 text-2xl font-black text-orange-950">{{ overview.overdue }}</p>
                </div>
                <div class="rounded-2xl border border-teal-200 bg-teal-50/60 p-4">
                    <p class="text-[10px] font-black uppercase text-teal-800">Escrow held</p>
                    <p class="mt-1 text-xl font-black text-teal-950">{{ overview.escrow_held_formatted }}</p>
                </div>
            </section>

            <section v-if="is_super_admin && system_operations" class="rounded-3xl border border-primary-200 bg-primary-50/40 p-5">
                <p class="text-[10px] font-black uppercase tracking-wide text-primary-800">System operations (super admin)</p>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs font-semibold text-primary-700">Revenue today</dt>
                        <dd class="text-lg font-black text-primary-950">{{ system_operations.revenue_today_formatted }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-primary-700">Payouts processed</dt>
                        <dd class="text-lg font-black text-primary-950">{{ system_operations.payouts_today_formatted }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-primary-700">Terminated this week</dt>
                        <dd class="text-lg font-black text-primary-950">{{ system_operations.terminated_this_week }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-primary-700">Escrow reconciliation</dt>
                        <dd class="flex flex-wrap items-center gap-2">
                            <span class="text-lg font-black text-emerald-700">✓ {{ system_operations.escrow_reconciled_label }}</span>
                            <button type="button" class="rounded-lg border border-primary-300 px-2 py-1 text-[10px] font-black uppercase text-primary-800" :disabled="reconcileBusy" @click="runReconcile">Reconcile now</button>
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                <p class="text-[10px] font-black uppercase text-slate-500">What counts as a contract</p>
                <p class="mt-2 text-sm font-semibold text-slate-700">{{ registry.definition }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-[10px] font-black uppercase text-emerald-700">Included ({{ registry.total }} total)</p>
                        <ul class="mt-2 list-disc space-y-1 pl-4 text-xs font-semibold text-slate-600">
                            <li v-for="(item, i) in registry.includes" :key="'in-'+i">{{ item }}</li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-500">Not shown here</p>
                        <ul class="mt-2 list-disc space-y-1 pl-4 text-xs font-semibold text-slate-600">
                            <li v-for="(item, i) in registry.excludes" :key="'ex-'+i">{{ item }}</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                <p class="text-[10px] font-black uppercase text-slate-500">Assigned to me</p>
                <ul class="mt-3 space-y-2 text-sm font-semibold text-slate-700">
                    <li>{{ quick_counts.assigned_mine }} contracts assigned to you</li>
                    <li>{{ quick_counts.disputes_open }} open disputes platform-wide</li>
                    <li>{{ quick_counts.needs_review }} contracts flagged for review</li>
                </ul>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" @click="activeTab = 'contracts'; applyQuick('mine')">My queue</button>
                    <button type="button" class="rounded-xl border px-4 py-2 text-xs font-black uppercase" @click="activeTab = 'alerts'">Browse alerts</button>
                    <button type="button" class="rounded-xl border px-4 py-2 text-xs font-black uppercase" @click="activeTab = 'contracts'">View all contracts</button>
                </div>
            </section>
        </div>

        <!-- Contracts table -->
        <div v-if="activeTab === 'contracts'" class="space-y-4">
            <div class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 lg:grid-cols-6 dark:border-slate-800 dark:bg-slate-950">
                <div class="lg:col-span-2">
                    <label class="text-[10px] font-black uppercase text-slate-500">Search</label>
                    <input v-model="filters.q" type="search" placeholder="Contract ID, quest, client, freelancer…" class="mt-1 w-full rounded-xl border-slate-200 text-sm" @keyup.enter="reloadListing" />
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase text-slate-500">Status</label>
                    <select v-model="filters.status" class="mt-1 w-full rounded-xl border-slate-200 text-sm" @change="reloadListing">
                        <option value="">All</option>
                        <option v-for="opt in filter_options.contract_statuses" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase text-slate-500">Risk</label>
                    <select v-model="filters.risk_level" class="mt-1 w-full rounded-xl border-slate-200 text-sm" @change="reloadListing">
                        <option value="">All</option>
                        <option v-for="opt in filter_options.risk_levels" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase text-slate-500">Sort</label>
                    <select v-model="filters.sort" class="mt-1 w-full rounded-xl border-slate-200 text-sm" @change="reloadListing">
                        <option v-for="opt in filter_options.sort_options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="button" class="w-full rounded-xl bg-primary-700 px-4 py-2.5 text-xs font-black uppercase text-white" @click="reloadListing">Apply</button>
                </div>
            </div>

            <div v-if="saved_filters.length" class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-black uppercase text-slate-500">Saved filters</span>
                <button v-for="sf in saved_filters" :key="sf.id" type="button" class="rounded-full border px-3 py-1 text-[10px] font-bold" @click="applySavedFilter(sf)">{{ sf.name }}</button>
            </div>

            <div class="mb-2 flex flex-wrap items-center gap-2">
                <button v-for="qv in quickViews" :key="qv.key" type="button" class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase" :class="filters.quick_view === qv.key ? 'bg-primary-600 text-white' : 'border border-slate-200'" @click="applyQuick(qv.key)">
                    {{ qv.label }}
                </button>
                <button type="button" class="rounded-full border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase text-slate-600" @click="promptSaveFilter">Save filter</button>
                <a :href="exportUrl" class="rounded-full border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase text-slate-600">Export CSV</a>
                <a v-if="capabilities.export_pdf" :href="exportPdfUrl" class="rounded-full border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase text-slate-600">Export PDF</a>
            </div>

            <div v-if="is_super_admin && selectedRefs.length" class="flex flex-wrap items-center gap-2 rounded-2xl border border-primary-200 bg-primary-50/60 p-3">
                <span class="text-xs font-black text-primary-900">{{ selectedRefs.length }} selected</span>
                <button type="button" class="rounded-xl bg-emerald-700 px-3 py-1.5 text-[10px] font-black uppercase text-white" :disabled="bulkBusy" @click="bulkRelease">Bulk release</button>
                <button type="button" class="rounded-xl bg-amber-600 px-3 py-1.5 text-[10px] font-black uppercase text-white" :disabled="bulkBusy" @click="bulkHold">Bulk hold escrow</button>
                <button type="button" class="rounded-xl border px-3 py-1.5 text-[10px] font-black uppercase" @click="selectedRefs = []">Clear</button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th v-if="is_super_admin" class="px-3 py-3"><span class="sr-only">Select</span></th>
                                <th class="px-4 py-3">Contract</th>
                                <th class="px-4 py-3">Quest</th>
                                <th class="px-4 py-3">Parties</th>
                                <th class="px-4 py-3">Amount</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Due</th>
                                <th class="px-4 py-3">Risk</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td :colspan="is_super_admin ? 9 : 8" class="px-4 py-10 text-center"><span class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" /></td>
                            </tr>
                            <tr v-else-if="!rows.length">
                                <td :colspan="is_super_admin ? 9 : 8" class="px-4 py-10 text-center font-semibold text-slate-500">No contracts match your filters.</td>
                            </tr>
                            <tr v-for="row in rows" :key="row.id" class="cursor-pointer border-b border-slate-100 hover:bg-slate-50/80" @click="openContract(row)">
                                <td v-if="is_super_admin" class="px-3 py-3" @click.stop>
                                    <input type="checkbox" :checked="selectedRefs.includes(row.reference_code)" class="rounded border-slate-300" @change="toggleSelect(row.reference_code)" />
                                </td>
                                <td class="px-4 py-3 font-bold text-slate-900">{{ row.reference_code }}</td>
                                <td class="max-w-[10rem] truncate px-4 py-3">{{ row.quest_title }}</td>
                                <td class="px-4 py-3 text-xs">
                                    <p>{{ row.client?.name }}</p>
                                    <p class="text-slate-500">{{ row.freelancer?.name }}</p>
                                </td>
                                <td class="px-4 py-3 font-bold">{{ row.amount_formatted }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-bold uppercase">{{ row.status_label }}</span>
                                    <span v-if="row.flagged_for_review" class="ml-1">⚠️</span>
                                </td>
                                <td class="px-4 py-3 text-xs" :class="row.is_overdue ? 'font-black text-rose-700' : ''">
                                    {{ row.is_overdue ? 'OVD' : row.days_until_due != null ? `${row.days_until_due}d` : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="riskBadgeClass(row.risk_level)">
                                        {{ riskEmoji(row.risk_level) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right" @click.stop>
                                    <button type="button" class="rounded-lg bg-primary-700 px-3 py-1.5 text-xs font-black uppercase text-white" @click="openContract(row)">View</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="meta.total > meta.per_page" class="flex items-center justify-between border-t px-4 py-3 text-xs text-slate-500">
                    <p>{{ meta.total }} total · page {{ meta.current_page }} / {{ meta.last_page }}</p>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border px-3 py-1 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">Prev</button>
                        <button type="button" class="rounded-lg border px-3 py-1 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <div v-if="activeTab === 'alerts'" class="space-y-3">
            <div class="flex flex-wrap gap-2">
                <button v-for="opt in filter_options.alert_types" :key="opt.value" type="button" class="rounded-full px-3 py-1 text-[10px] font-black uppercase" :class="alertTypeFilter === opt.value ? 'bg-primary-600 text-white' : 'border'" @click="filterAlerts(opt.value)">{{ opt.label }}</button>
                <button v-if="alertTypeFilter" type="button" class="rounded-full border px-3 py-1 text-[10px] font-bold text-slate-500" @click="filterAlerts(null)">Clear</button>
            </div>
            <div v-for="alert in alertRows" :key="alert.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3" :class="alertRowClass(alert.severity)">
                <div>
                    <p class="text-sm font-black">{{ alert.title }}</p>
                    <p class="text-xs font-semibold opacity-80">{{ alert.message }}</p>
                </div>
                <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white" @click="openContractByRef(alert.reference_code)">View</button>
            </div>
            <p v-if="!alertRows.length" class="py-10 text-center font-semibold text-slate-500">No alerts match this filter.</p>
        </div>

        <!-- Patrol flags -->
        <div v-if="activeTab === 'patrol'" class="space-y-3">
            <div v-for="flag in patrolRows" :key="flag.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3" :class="alertRowClass(flag.severity)">
                <div>
                    <p class="text-sm font-black">{{ flag.type_label }} · {{ flag.reference_code }}</p>
                    <p class="text-xs font-semibold opacity-80">{{ flag.summary }}</p>
                    <p class="text-[10px] text-slate-500">{{ flag.detected_ago }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded-xl border px-3 py-1.5 text-[10px] font-black uppercase" @click="ackPatrolFlag(flag.id)">Ack</button>
                    <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white" @click="openContractByRef(flag.reference_code)">View</button>
                </div>
            </div>
            <p v-if="!patrolRows.length" class="py-10 text-center font-semibold text-slate-500">No open patrol flags.</p>
        </div>

        <!-- QA random audits -->
        <div v-if="activeTab === 'qa'" class="space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="qaLoading" @click="loadQaSample">Generate random sample</button>
                <p v-if="qaSample.sample_size" class="text-xs font-semibold text-slate-600">{{ qaSample.sample_size }} contracts sampled</p>
            </div>
            <div v-for="item in qaSample.items || []" :key="item.id" class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-black text-slate-900">{{ item.reference_code }} · {{ item.quest_title }}</p>
                        <p class="text-xs text-slate-500">{{ item.client_name }} → {{ item.freelancer_name }} · {{ item.amount_formatted }}</p>
                        <p class="mt-1 text-xs font-semibold text-amber-800">{{ item.audit_prompt }}</p>
                    </div>
                    <button type="button" class="rounded-lg bg-primary-700 px-3 py-1.5 text-[10px] font-black uppercase text-white" @click="openContractByRef(item.reference_code)">Audit</button>
                </div>
            </div>
        </div>

        <!-- Settings (super admin) -->
        <div v-if="activeTab === 'settings' && is_super_admin && settingsPayload" class="max-w-xl space-y-4">
            <p class="text-sm font-semibold text-slate-600">Escrow and dispute configuration stored in platform settings.</p>
            <div v-for="setting in settingsPayload.settings" :key="setting.key" class="rounded-2xl border border-slate-200 bg-white p-4">
                <label class="text-[10px] font-black uppercase text-slate-500">{{ setting.label }}</label>
                <input v-model.number="settingsForm[setting.key]" type="number" :min="setting.min" :max="setting.max" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-bold" />
            </div>
            <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="settingsBusy" @click="saveSettings">Save settings</button>
        </div>

        <!-- Disputes -->
        <div v-if="activeTab === 'disputes'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Contract</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Filed</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in disputeRows" :key="d.id" class="border-b hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-bold">{{ d.reference || '—' }}</td>
                        <td class="max-w-xs truncate px-4 py-3">{{ d.reason }}</td>
                        <td class="px-4 py-3">{{ d.amount_formatted }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ d.filed_ago }}</td>
                        <td class="px-4 py-3 text-xs font-bold uppercase">{{ d.is_resolved ? 'Resolved' : 'Active' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="d.reference" type="button" class="rounded-lg bg-primary-700 px-3 py-1.5 text-xs font-black uppercase text-white" @click="openContractByRef(d.reference)">View contract</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </component>

    <ContractDetailPanel
        :open="panelOpen"
        :reference-code="selectedReference"
        :route-prefix="route_prefix"
        :is-super-admin="is_super_admin"
        @close="panelOpen = false"
        @action-done="onActionDone"
    />
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import ContractDetailPanel from '@/Pages/ContractManagement/Components/ContractDetailPanel.vue';

const props = defineProps({
    overview: { type: Object, required: true },
    system_operations: { type: Object, default: null },
    alerts: { type: Array, default: () => [] },
    patrol_flags: { type: Array, default: () => [] },
    saved_filters: { type: Array, default: () => [] },
    settings: { type: Object, default: null },
    quick_counts: { type: Object, required: true },
    listing: { type: Object, required: true },
    disputes: { type: Object, required: true },
    filter_options: { type: Object, required: true },
    is_super_admin: { type: Boolean, default: false },
    capabilities: { type: Object, required: true },
    registry: { type: Object, default: () => ({ total: 0, definition: '', includes: [], excludes: [] }) },
    route_prefix: { type: String, default: 'operations' },
    use_admin_shell: { type: Boolean, default: false },
});

const shellComponent = computed(() => (props.use_admin_shell ? AdminShell : OperationsShell));
const activeTab = ref('overview');
const tabs = computed(() => {
    const base = [
        { key: 'overview', label: 'Overview' },
        { key: 'contracts', label: 'Contracts' },
        { key: 'alerts', label: 'Alerts' },
        { key: 'patrol', label: 'Patrol flags' },
        { key: 'disputes', label: 'Disputes' },
    ];
    if (props.capabilities.quality_audit) {
        base.push({ key: 'qa', label: 'QA audits' });
    }
    if (props.is_super_admin && props.capabilities.system_settings) {
        base.push({ key: 'settings', label: 'Settings' });
    }
    return base;
});

const rows = ref(props.listing.items ?? []);
const meta = ref(props.listing.meta ?? { total: 0, per_page: 25, current_page: 1, last_page: 1 });
const alertRows = ref(props.alerts ?? []);
const patrolRows = ref(props.patrol_flags ?? []);
const saved_filters = ref(props.saved_filters ?? []);
const settingsPayload = ref(props.settings);
const disputeRows = ref(props.disputes.items ?? []);
const loading = ref(false);
const bulkBusy = ref(false);
const reconcileBusy = ref(false);
const qaLoading = ref(false);
const settingsBusy = ref(false);
const selectedRefs = ref([]);
const qaSample = ref({ items: [], sample_size: 0 });
const settingsForm = reactive({});
const panelOpen = ref(false);
const selectedReference = ref(null);
const toast = ref(null);
const alertTypeFilter = ref(null);

const filters = ref({
    q: '',
    status: '',
    risk_level: '',
    sort: 'recent',
    quick_view: '',
    page: 1,
});

const quickViews = [
    { key: 'critical', label: 'Critical' },
    { key: 'overdue', label: 'Overdue' },
    { key: 'disputed', label: 'Disputed' },
    { key: 'flagged', label: 'Flagged' },
    { key: 'mine', label: 'My queue' },
    { key: 'pending_escrow', label: 'Pending escrow' },
];

function routeName(name) {
    return `${props.route_prefix}.${name}`;
}

const exportUrl = computed(() => route(routeName('contract-management.export.csv'), { ...filters.value }));
const exportPdfUrl = computed(() => route(routeName('contract-management.export.pdf'), { ...filters.value }));

if (settingsPayload.value?.settings) {
    settingsPayload.value.settings.forEach((s) => {
        settingsForm[s.key] = s.value;
    });
}

function riskBadgeClass(level) {
    return {
        low: 'bg-emerald-100 text-emerald-800',
        medium: 'bg-amber-100 text-amber-800',
        high: 'bg-orange-100 text-orange-800',
        critical: 'bg-rose-100 text-rose-800',
    }[level] || 'bg-slate-100 text-slate-700';
}

function riskEmoji(level) {
    return { low: '🟢', medium: '🟡', high: '🟠', critical: '🔴' }[level] || '⚪';
}

function alertRowClass(severity) {
    return {
        critical: 'border-rose-300 bg-rose-50',
        high: 'border-orange-300 bg-orange-50',
        medium: 'border-amber-300 bg-amber-50',
    }[severity] || 'border-slate-200 bg-white';
}

async function reloadListing() {
    loading.value = true;
    try {
        const { data } = await axios.get(route(routeName('contract-management.api.listing')), { params: filters.value });
        rows.value = data.items ?? [];
        meta.value = data.meta ?? meta.value;
    } finally {
        loading.value = false;
    }
}

async function filterAlerts(type) {
    alertTypeFilter.value = type;
    const { data } = await axios.get(route(routeName('contract-management.api.alerts')), { params: { type, limit: 50 } });
    alertRows.value = data.items ?? [];
}

function applyQuick(key) {
    filters.value.quick_view = filters.value.quick_view === key ? '' : key;
    filters.value.page = 1;
    activeTab.value = 'contracts';
    reloadListing();
}

function changePage(page) {
    filters.value.page = page;
    reloadListing();
}

function openContract(row) {
    selectedReference.value = row.reference_code;
    panelOpen.value = true;
}

function openContractByRef(ref) {
    selectedReference.value = ref;
    panelOpen.value = true;
    activeTab.value = 'contracts';
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const openRef = params.get('open');
    const q = params.get('q');

    if (q) {
        filters.value.q = q;
    }

    if (openRef) {
        if (q) {
            reloadListing().finally(() => openContractByRef(openRef));
        } else {
            openContractByRef(openRef);
        }
    } else if (q) {
        reloadListing();
    }
});

function onActionDone() {
    toast.value = 'Action saved.';
    reloadListing();
    refreshPatrolFlags();
    setTimeout(() => { toast.value = null; }, 3000);
}

function toggleSelect(ref) {
    if (selectedRefs.value.includes(ref)) {
        selectedRefs.value = selectedRefs.value.filter((r) => r !== ref);
    } else {
        selectedRefs.value = [...selectedRefs.value, ref];
    }
}

async function refreshPatrolFlags() {
    const { data } = await axios.get(route(routeName('contract-management.api.patrol-flags')));
    patrolRows.value = data.items ?? [];
}

async function ackPatrolFlag(id) {
    await axios.post(route(routeName('contract-management.api.patrol-flags.acknowledge'), id));
    await refreshPatrolFlags();
    toast.value = 'Patrol flag acknowledged.';
}

async function loadQaSample() {
    qaLoading.value = true;
    try {
        const { data } = await axios.get(route(routeName('contract-management.api.quality-audit')));
        qaSample.value = data;
    } finally {
        qaLoading.value = false;
    }
}

async function promptSaveFilter() {
    const name = window.prompt('Name this filter');
    if (!name) return;
    const { data } = await axios.post(route(routeName('contract-management.api.saved-filters.store')), {
        name,
        filters: { ...filters.value },
    });
    saved_filters.value = [...saved_filters.value.filter((f) => f.id !== data.filter?.id), data.filter].filter(Boolean);
    toast.value = 'Filter saved.';
}

function applySavedFilter(sf) {
    Object.assign(filters.value, sf.filters ?? {});
    filters.value.page = 1;
    activeTab.value = 'contracts';
    reloadListing();
}

async function bulkRelease() {
    const reason = window.prompt('Reason for bulk release (min 10 chars)');
    if (!reason || reason.length < 10) return;
    bulkBusy.value = true;
    try {
        const { data } = await axios.post(route(routeName('contract-management.bulk.release')), {
            reference_codes: selectedRefs.value,
            reason,
        });
        toast.value = data.message || 'Bulk release complete.';
        selectedRefs.value = [];
        reloadListing();
    } finally {
        bulkBusy.value = false;
    }
}

async function bulkHold() {
    const reason = window.prompt('Reason for bulk escrow hold (min 10 chars)');
    if (!reason || reason.length < 10) return;
    bulkBusy.value = true;
    try {
        const { data } = await axios.post(route(routeName('contract-management.bulk.hold-escrow')), {
            reference_codes: selectedRefs.value,
            reason,
        });
        toast.value = data.message || 'Bulk hold applied.';
        selectedRefs.value = [];
    } finally {
        bulkBusy.value = false;
    }
}

async function runReconcile() {
    reconcileBusy.value = true;
    try {
        const { data } = await axios.post(route(routeName('contract-management.reconcile-escrow')));
        toast.value = data.message || 'Reconciliation complete.';
    } finally {
        reconcileBusy.value = false;
    }
}

async function saveSettings() {
    settingsBusy.value = true;
    try {
        const { data } = await axios.patch(route(routeName('contract-management.settings.update')), { ...settingsForm });
        settingsPayload.value = data.settings;
        toast.value = 'Settings saved.';
    } finally {
        settingsBusy.value = false;
    }
}
</script>
