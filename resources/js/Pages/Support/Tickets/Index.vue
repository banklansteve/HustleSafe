<template>
    <component :is="shellComponent" title="Support tickets" :subtitle="shellSubtitle">
        <div class="space-y-6">
            <section class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-primary-50/30 p-5 shadow-sm md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Ticket management</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">Customer support queue</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Search, filter, and open tickets in a sortable table.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link v-if="isSuperAdmin && routePrefix === 'admin'" :href="route('admin.support-tickets.issue-groups')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-black uppercase text-slate-700">Issue groups</Link>
                    <Link :href="route(routeName('support-tickets.create'))" class="inline-flex items-center justify-center rounded-2xl bg-primary-700 px-5 py-3 text-sm font-black text-white">Create ticket</Link>
                </div>
            </section>

            <section v-if="analytics" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-black uppercase text-slate-500">Open</p><p class="mt-2 text-3xl font-black text-slate-950">{{ analytics.open_tickets }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-black uppercase text-slate-500">Avg resolution</p><p class="mt-2 text-3xl font-black text-slate-950">{{ analytics.average_resolution_label || '—' }}</p></article>
                <article class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm"><p class="text-xs font-black uppercase text-rose-700">SLA breach rate</p><p class="mt-2 text-3xl font-black text-rose-900">{{ analytics.sla_breach_rate }}%</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-black uppercase text-slate-500">Updated</p><p class="mt-2 text-sm font-black text-slate-700">{{ formatDate(analytics.refreshed_at) }}</p></article>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <select v-model="localFilters.status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold" @change="applyServerFilters">
                        <option value="">All statuses</option>
                        <option v-for="status in statuses" :key="status" :value="status">{{ labelize(status) }}</option>
                    </select>
                    <select v-model="localFilters.priority" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold" @change="applyServerFilters">
                        <option value="">All priorities</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                    <select v-model="localFilters.issue_group" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold" @change="applyServerFilters">
                        <option value="">All categories</option>
                        <option v-for="group in issueGroups" :key="group.key" :value="group.key">{{ group.label }}</option>
                    </select>
                    <select v-if="isSuperAdmin" v-model="localFilters.assignee_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold" @change="applyServerFilters">
                        <option value="">All assignees</option>
                        <option v-for="admin in assignableAdmins" :key="admin.id" :value="admin.id">{{ admin.name }}</option>
                    </select>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700">
                        <input v-model="slaBreachedOnly" type="checkbox" class="h-4 w-4 rounded border-slate-300" @change="applyServerFilters" />
                        SLA breached only
                    </label>
                </div>
            </section>

            <OperationsQueueTable
                :columns="columns"
                :rows="queue.pageItems.value"
                v-model:search="queue.search.value"
                v-model:per-page="queue.perPage.value"
                :total="queue.total.value"
                :page="queue.page.value"
                :total-pages="queue.totalPages.value"
                :sort-key="queue.sortKey.value"
                :sort-dir="queue.sortDir.value"
                row-key-field="uuid"
                row-clickable
                search-placeholder="Search reference, subject, customer, assignee…"
                empty-message="No tickets match your filters."
                @sort="queue.setSort"
                @page="(page) => (queue.page.value = page)"
                @open="openTicket"
            >
                <template #cell-ticket_reference="{ row }">
                    <span class="font-black text-primary-800">{{ row.ticket_reference || row.uuid }}</span>
                </template>
                <template #cell-subject="{ row }">
                    <span class="font-bold text-slate-900">{{ row.subject }}</span>
                </template>
                <template #cell-customer="{ row }">
                    <span class="font-semibold text-slate-700">{{ row.customer?.name || '—' }}</span>
                </template>
                <template #cell-status="{ row }">
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusClass(row.status)">{{ labelize(row.status) }}</span>
                </template>
                <template #cell-priority="{ row }">
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide" :class="priorityClass(row.priority)">{{ row.priority }}</span>
                </template>
                <template #cell-assigned_admin="{ row }">
                    {{ row.assigned_admin?.name || 'Unassigned' }}
                </template>
                <template #cell-expected_resolution_at="{ row }">
                    <span :class="row.sla_breached ? 'font-black text-rose-700' : 'text-slate-600'">{{ formatDate(row.expected_resolution_at) }}</span>
                </template>
                <template #actions="{ row }">
                    <Link :href="route(routeName('support-tickets.show'), row.uuid)" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-[10px] font-black uppercase text-slate-700">View</Link>
                </template>
            </OperationsQueueTable>
        </div>
    </component>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { formatLeaveDateTime } from '@/utils/formatHumanDateTime';

const props = defineProps({
    ticketRows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    issueGroups: { type: Array, default: () => [] },
    assignableAdmins: { type: Array, default: () => [] },
    analytics: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },
    routePrefix: { type: String, default: 'admin' },
    isSuperAdmin: { type: Boolean, default: false },
});

const shellComponent = computed(() => (props.routePrefix === 'admin' ? AdminShell : OperationsShell));
const shellSubtitle = computed(() => (props.routePrefix === 'admin' ? 'Super Admin · Unified support queue' : 'Staff Admin · Assigned tickets'));

const localFilters = reactive({
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    issue_group: props.filters.issue_group ?? '',
    assignee_id: props.filters.assignee_id ?? '',
    sla_breached: props.filters.sla_breached ?? '',
});
const slaBreachedOnly = ref(localFilters.sla_breached === '1');

const columns = [
    { key: 'ticket_reference', label: 'Reference', sortable: true },
    { key: 'subject', label: 'Subject', sortable: true },
    { key: 'customer.name', label: 'Customer', sortable: true },
    { key: 'issue_group_label', label: 'Category', sortable: true },
    { key: 'priority', label: 'Priority', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'assigned_admin.name', label: 'Assignee', sortable: true },
    { key: 'expected_resolution_at', label: 'Due', sortable: true },
];

const queue = useClientQueue(() => props.ticketRows, {
    defaultSortKey: 'opened_at',
    defaultSortDir: 'desc',
    perPage: 25,
    searchFields: ['ticket_reference', 'subject', 'issue_group_label', 'priority', 'status', 'customer.name', 'customer.email', 'assigned_admin.name'],
});

function routeName(name) {
    return `${props.routePrefix}.${name}`;
}

function applyServerFilters() {
    localFilters.sla_breached = slaBreachedOnly.value ? '1' : '';
    router.get(route(routeName('support-tickets.index')), localFilters, { preserveState: true, replace: true });
}

function openTicket(row) {
    router.visit(route(routeName('support-tickets.show'), row.uuid));
}

function labelize(value) {
    return String(value || '').replaceAll('_', ' ');
}

function formatDate(value) {
    return value ? formatLeaveDateTime(value) : '—';
}

function statusClass(status) {
    if (status === 'closed' || status === 'resolved') return 'bg-emerald-100 text-emerald-800';
    if (status === 'awaiting_customer') return 'bg-amber-100 text-amber-800';
    if (status === 'in_progress') return 'bg-sky-100 text-sky-800';
    return 'bg-slate-100 text-slate-700';
}

function priorityClass(priority) {
    if (priority === 'critical') return 'bg-rose-100 text-rose-800';
    if (priority === 'high') return 'bg-orange-100 text-orange-800';
    return 'bg-slate-100 text-slate-700';
}
</script>
