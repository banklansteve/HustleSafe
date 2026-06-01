<template>
    <OperationsShell title="Verification queue" subtitle="Pick up pending identity and KYC submissions from the queue, or review cases already assigned to you. Client BVN and freelancer selfie + ID checks are Super Admin only.">
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="tab in queueTabs"
                :key="tab.key"
                type="button"
                class="shrink-0 rounded-2xl px-4 py-2 text-left transition"
                :class="activeTab === tab.key ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700'"
                @click="switchTab(tab.key)"
            >
                <span class="block text-xs font-black uppercase">{{ tab.label }}</span>
                <span class="mt-0.5 block text-[11px] font-semibold opacity-80">{{ tab.hint }}</span>
            </button>
        </div>

        <div
            v-if="activeTab === 'my_assignments'"
            class="mb-4 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3 ring-1 ring-slate-100"
        >
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="preset in rangePresets"
                    :key="preset.value"
                    type="button"
                    class="rounded-xl px-3 py-1.5 text-xs font-black uppercase tracking-wide transition"
                    :class="dateRange === preset.value ? 'bg-primary-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200'"
                    @click="setDateRange(preset.value)"
                >
                    {{ preset.label }}
                </button>
            </div>
            <div v-if="dateRange === 'custom'" class="flex flex-wrap items-center gap-2">
                <label class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                    From
                    <input v-model="dateFrom" type="date" class="mt-1 block min-h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold" />
                </label>
                <label class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                    To
                    <input v-model="dateTo" type="date" class="mt-1 block min-h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold" />
                </label>
                <button type="button" class="min-h-10 rounded-xl bg-primary-700 px-4 text-xs font-black uppercase text-white" @click="reload">Apply</button>
            </div>
        </div>

        <div class="mb-4 space-y-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-100">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Filters &amp; search</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="chip in statusChips"
                    :key="chip.value"
                    type="button"
                    class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide transition"
                    :class="statusFilter === chip.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200'"
                    @click="setStatusFilter(chip.value)"
                >
                    {{ chip.label }}
                </button>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block sm:col-span-2">
                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Search</span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold"
                        placeholder="Name, email, or verification type"
                        @keydown.enter="applyFilters"
                    />
                </label>
                <label class="block">
                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Verification type</span>
                    <select v-model="typeFilter" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold">
                        <option value="">All types</option>
                        <option v-for="opt in typeFilterOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Sort by</span>
                    <select v-model="sortKey" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold">
                        <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </label>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select v-model="sortDir" class="min-h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold uppercase tracking-wide text-slate-700">
                    <option value="desc">Newest first</option>
                    <option value="asc">Oldest first</option>
                </select>
                <select v-model="perPage" class="min-h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold uppercase tracking-wide text-slate-700">
                    <option :value="15">15 / page</option>
                    <option :value="25">25 / page</option>
                    <option :value="50">50 / page</option>
                </select>
                <button type="button" class="min-h-10 rounded-xl bg-primary-700 px-5 text-xs font-black uppercase text-white" @click="applyFilters">Apply</button>
                <button type="button" class="min-h-10 rounded-xl border border-slate-200 bg-white px-4 text-xs font-black uppercase text-slate-700" @click="resetFilters">Reset</button>
                <p class="text-xs font-semibold text-slate-500">{{ total }} result{{ total === 1 ? '' : 's' }}</p>
            </div>
        </div>

        <OperationsQueueTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :show-search="false"
            :show-per-page="false"
            :page="currentPage"
            :total="total"
            :total-pages="totalPages"
            :sort-key="sortKey"
            :sort-dir="sortDir"
            :empty-message="emptyMessage"
            @sort="onSort"
            @page="onPage"
            @open="openDetail"
        >
            <template #cell-user="{ row }">
                <span class="font-semibold text-slate-950">{{ row.user?.name }}</span>
                <span class="mt-0.5 block text-xs text-slate-500">{{ row.user?.email }}</span>
            </template>
            <template #cell-type="{ row }">
                <span class="font-bold text-slate-800">{{ row.type_label || row.type }}</span>
            </template>
            <template #cell-assigned_at="{ row }">
                <span class="text-xs font-semibold text-slate-700">{{ row.staff_assigned_at_label || row.staff_assigned_at || '—' }}</span>
            </template>
            <template #cell-submitted_at="{ row }">
                <span class="text-xs font-semibold text-slate-700">{{ row.submitted_at_label || row.submitted_at }}</span>
            </template>
            <template #cell-reviewed_at="{ row }">
                <span class="text-xs font-semibold text-slate-700">{{ row.reviewed_at_label || row.reviewed_at || '—' }}</span>
            </template>
            <template #cell-status="{ row }">
                <span class="inline-flex flex-wrap items-center gap-1">
                    <span
                        v-if="row.is_duplicate_identity"
                        class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-black uppercase text-white ring-2 ring-rose-200"
                    >
                        Duplicate ID
                    </span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusPill(row.status)">{{ row.status_label || row.status }}</span>
                    <span v-if="row.is_escalated_to_super_admin" class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-black uppercase text-violet-800">Super Admin</span>
                </span>
            </template>
            <template #actions="{ row }">
                <button
                    type="button"
                    class="rounded-lg px-2 py-1 text-[10px] font-black uppercase text-white"
                    :class="row.staff_can_decide ? 'bg-primary-700' : 'bg-slate-600'"
                    @click.stop="openDetail(row)"
                >
                    {{ row.staff_can_decide ? 'Review' : 'View' }}
                </button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" subtitle="Identity verification review" eyebrow="KYC" @close="slideOpen = false">
            <div v-if="detailLoading" class="py-10 text-center text-sm font-semibold text-slate-500">Loading submission…</div>
            <div v-else-if="presentation" class="space-y-4">
                <div
                    v-if="presentation.is_escalated_to_super_admin && !canDecide"
                    class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-semibold text-violet-950"
                >
                    This case is with Super Admin. Approve, reject, and correction actions are hidden until they return it to the queue.
                </div>

                <VerificationReviewPanel
                    :presentation="presentation"
                    :can-decide="canDecide"
                    :decide-url="decideUrl"
                    :decision-reasons="decisionReasons"
                    @decided="onDecided"
                />

                <OperationsExpandableAction
                    v-if="canEscalate"
                    title="Escalate to Super Admin"
                    icon="⬆"
                    tone="slate"
                    submit-label="Escalate"
                    :busy="busy.escalate"
                    @submit="escalate"
                >
                    <UiTextarea v-model="escalateForm.reason" label="Escalation reason" placeholder="Why does Super Admin need to decide this case?" :min-rows="3" :required="true" />
                </OperationsExpandableAction>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import VerificationReviewPanel from '@/Components/Verification/VerificationReviewPanel.vue';
import UiTextarea from '@/Components/Ui/UiTextarea.vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useOperationsAction } from '@/composables/useOperationsAction';
import { useVerificationQueueEcho } from '@/composables/useVerificationQueueEcho';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    decision_reasons: { type: Array, default: () => [] },
    queue_defaults: {
        type: Object,
        default: () => ({ tab: 'pending_queue', range: '30d', per_page: 25 }),
    },
});

const inertiaPage = usePage();

const decisionReasons = ref([...props.decision_reasons]);

const queueTabs = [
    { key: 'pending_queue', label: 'Pending queue', hint: 'Unassigned cases you can pick up — oldest first' },
    { key: 'my_assignments', label: 'My assignments', hint: 'Cases assigned to you (date range)' },
    { key: 'assigned_today', label: 'Assigned today', hint: 'Everything assigned to you today' },
];

const rangePresets = [
    { value: '7d', label: 'Last 7 days' },
    { value: '30d', label: 'Last 30 days' },
    { value: '90d', label: 'Last 90 days' },
    { value: 'custom', label: 'Custom range' },
];

const statusChips = [
    { value: '', label: 'All statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'in_review', label: 'In review' },
    { value: 'unverified', label: 'Corrections' },
    { value: 'flagged', label: 'Flagged' },
    { value: 'verified', label: 'Verified' },
    { value: 'rejected', label: 'Rejected' },
];

const typeFilterOptions = [
    { value: 'identity_address', label: 'Identity & address' },
    { value: 'nin', label: 'NIN' },
    { value: 'bvn', label: 'BVN' },
    { value: 'cac', label: 'CAC' },
    { value: 'tin', label: 'TIN' },
    { value: 'live_presence', label: 'Selfie + ID' },
    { value: 'professional_certificate', label: 'Professional certificate' },
];

const sortOptions = [
    { value: 'submitted_at', label: 'Submitted date' },
    { value: 'staff_assigned_at', label: 'Assigned date' },
    { value: 'reviewed_at', label: 'Reviewed date' },
    { value: 'user', label: 'User name' },
    { value: 'type', label: 'Verification type' },
    { value: 'status', label: 'Status' },
];

const columns = computed(() => {
    const base = [
        { key: 'user', label: 'User', sortable: true },
        { key: 'type', label: 'Type', sortable: true },
        { key: 'status', label: 'Status', sortable: true },
    ];

    if (activeTab.value !== 'pending_queue') {
        base.push({ key: 'assigned_at', label: 'Assigned', sortable: true });
    }

    base.push(
        { key: 'submitted_at', label: 'Submitted', sortable: true },
        { key: 'reviewed_at', label: 'Reviewed', sortable: true },
    );

    return base;
});

const activeTab = ref(props.queue_defaults.tab || 'pending_queue');
const dateRange = ref(props.queue_defaults.range || '30d');
const dateFrom = ref('');
const dateTo = ref('');
const statusFilter = ref(activeTab.value === 'pending_queue' ? 'pending' : '');
const typeFilter = ref('');
const searchQuery = ref('');

const rows = ref([]);
const loading = ref(false);
const currentPage = ref(1);
const perPage = ref(props.queue_defaults.per_page || 25);
const total = ref(0);
const totalPages = ref(1);
const sortKey = ref(activeTab.value === 'pending_queue' ? 'submitted_at' : 'staff_assigned_at');
const sortDir = ref(activeTab.value === 'pending_queue' ? 'asc' : 'desc');

const slideOpen = ref(false);
const detailLoading = ref(false);
const presentation = ref(null);
const selectedRow = ref(null);
const escalateForm = reactive({ reason: '' });

const { busy, runAction } = useOperationsAction();
const slideTitle = computed(() => presentation.value?.user?.name || 'Verification');
const decideUrl = computed(() => (selectedRow.value ? route('operations.api.verifications.decide', selectedRow.value.id) : ''));
const canDecide = computed(() => Boolean(presentation.value?.staff_can_decide));
const canEscalate = computed(() => canDecide.value && !presentation.value?.is_escalated_to_super_admin);

const emptyMessage = computed(() => {
    if (activeTab.value === 'assigned_today') {
        return 'Nothing assigned to you today yet.';
    }
    if (activeTab.value === 'pending_queue') {
        return statusFilter.value === 'pending' ? 'No pending verifications in the queue.' : 'No verifications match this filter.';
    }
    if (statusFilter.value === 'pending') {
        return 'You have no pending assignments in this date range.';
    }

    return 'No verifications match your filters.';
});

let searchDebounce = null;

watch(searchQuery, () => {
    if (searchDebounce) {
        clearTimeout(searchDebounce);
    }
    searchDebounce = setTimeout(() => {
        currentPage.value = 1;
        reload();
    }, 400);
});

watch([sortKey, sortDir, perPage, typeFilter], () => {
    currentPage.value = 1;
    reload();
});

onMounted(reload);

useVerificationQueueEcho(
    computed(() => inertiaPage.props.broadcast),
    () => {
        reload();
    },
);

function applyFilters() {
    currentPage.value = 1;
    reload();
}

function resetFilters() {
    searchQuery.value = '';
    typeFilter.value = '';
    statusFilter.value = activeTab.value === 'pending_queue' ? 'pending' : '';
    sortKey.value = activeTab.value === 'pending_queue' ? 'submitted_at' : 'staff_assigned_at';
    sortDir.value = activeTab.value === 'pending_queue' ? 'asc' : 'desc';
    currentPage.value = 1;
    reload();
}

function switchTab(key) {
    if (activeTab.value === key) {
        return;
    }
    activeTab.value = key;
    statusFilter.value = key === 'pending_queue' ? 'pending' : '';
    sortKey.value = key === 'pending_queue' || statusFilter.value === 'pending' ? 'submitted_at' : 'staff_assigned_at';
    sortDir.value = key === 'pending_queue' || statusFilter.value === 'pending' ? 'asc' : 'desc';
    currentPage.value = 1;
    reload();
}

function setDateRange(value) {
    dateRange.value = value;
    if (value !== 'custom') {
        currentPage.value = 1;
        reload();
    }
}

function setStatusFilter(value) {
    statusFilter.value = value;
    if (value === 'pending') {
        sortKey.value = 'submitted_at';
        sortDir.value = 'asc';
    }
    currentPage.value = 1;
    reload();
}

function onSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = key === 'submitted_at' && statusFilter.value === 'pending' ? 'asc' : 'desc';
    }
    currentPage.value = 1;
    reload();
}

function onPage(nextPage) {
    currentPage.value = nextPage;
    reload();
}

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.verifications.listing'), {
            params: {
                tab: activeTab.value,
                q: searchQuery.value,
                status: statusFilter.value,
                type: typeFilter.value,
                range: dateRange.value,
                date_from: dateRange.value === 'custom' ? dateFrom.value : undefined,
                date_to: dateRange.value === 'custom' ? dateTo.value : undefined,
                sort: mapSortKey(sortKey.value),
                direction: sortDir.value,
                page: currentPage.value,
                per_page: perPage.value,
            },
        });
        rows.value = data.items ?? [];
        total.value = data.meta?.total ?? 0;
        totalPages.value = data.meta?.last_page ?? 1;
        currentPage.value = data.meta?.current_page ?? currentPage.value;
    } finally {
        loading.value = false;
    }
}

function mapSortKey(key) {
    if (key === 'assigned_at') {
        return 'staff_assigned_at';
    }

    return key;
}

async function openDetail(row) {
    selectedRow.value = row;
    slideOpen.value = true;
    detailLoading.value = true;
    presentation.value = null;
    try {
        const { data } = await window.axios.get(route('operations.api.verifications.detail', row.id));
        presentation.value = data.presentation ?? data.verification?.presentation ?? null;
        if (data.decision_reasons?.length) {
            decisionReasons.value = data.decision_reasons;
        }
        if (data.verification) {
            const idx = rows.value.findIndex((item) => item.id === row.id);
            if (idx !== -1) {
                rows.value[idx] = { ...rows.value[idx], ...data.verification };
            }
        }
    } finally {
        detailLoading.value = false;
    }
}

async function onDecided(data) {
    if (data?.presentation) {
        presentation.value = data.presentation;
    }
    await reload();
}

async function escalate() {
    if (!selectedRow.value || escalateForm.reason.trim().length < 8) {
        return;
    }
    await runAction('escalate', () => window.axios.post(route('operations.api.verifications.escalate', selectedRow.value.id), { reason: escalateForm.reason }), 'Escalated to Super Admin.', async (response) => {
        escalateForm.reason = '';
        const data = response?.data;
        if (data?.presentation) {
            presentation.value = data.presentation;
        } else {
            await openDetail(selectedRow.value);
        }
        await reload();
    });
}

function statusPill(status) {
    if (status === 'verified' || status === 'approved') {
        return 'bg-emerald-100 text-emerald-800';
    }
    if (status === 'flagged') {
        return 'bg-violet-100 text-violet-800';
    }
    if (status === 'rejected') {
        return 'bg-rose-100 text-rose-800';
    }
    if (status === 'unverified') {
        return 'bg-amber-100 text-amber-900';
    }
    if (status === 'pending' || status === 'in_review') {
        return 'bg-sky-100 text-sky-900';
    }
    return 'bg-slate-100 text-slate-700';
}
</script>
