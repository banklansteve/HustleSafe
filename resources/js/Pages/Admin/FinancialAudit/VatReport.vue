<template>
    <AdminShell title="VAT audit report" subtitle="VAT by contract — projected for held escrows, recognised on release. Click a row for full quest and contract details.">
        <div class="space-y-5">
            <FinancialAuditNav active="vat" />

            <AdminPanel title="Filters" description="Filter by date field, status, or search. Click column headers to sort.">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <input v-model="filters.q" type="search" placeholder="Search contract ref, escrow ref, names…" class="rounded-2xl border px-4 py-3 text-sm font-semibold xl:col-span-2" :class="shell.input" @input="debouncedApply" />
                    <select v-model="filters.date_field" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply">
                        <option v-for="f in report.date_fields" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                    <select v-model="filters.status" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply">
                        <option value="">All statuses</option>
                        <option v-for="s in report.statuses" :key="s" :value="s">{{ s.replace(/_/g, ' ') }}</option>
                    </select>
                    <input v-model="filters.from" type="date" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" />
                    <input v-model="filters.to" type="date" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="rounded-full px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost" @click="applyAllTime">All time</button>
                    <a :href="exportUrl" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white">Export CSV</a>
                </div>
            </AdminPanel>

            <div v-if="report.totals?.count" class="grid gap-2 sm:grid-cols-3">
                <div class="rounded-2xl border p-3 text-center" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">{{ report.totals.count }} contracts</p><p class="mt-1 text-sm font-black">Gross {{ report.totals.gross_display }}</p></div>
                <div class="rounded-2xl border p-3 text-center" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Total VAT</p><p class="mt-1 text-sm font-black text-indigo-700">{{ report.totals.vat_display }}</p></div>
                <div class="rounded-2xl border p-3 text-center" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Period</p><p class="mt-1 text-xs font-bold">{{ report.filters?.label }}</p></div>
            </div>

            <div class="overflow-x-auto rounded-3xl border shadow-sm" :class="shell.card">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                            <th v-for="col in columns" :key="col.key" class="cursor-pointer px-3 py-3 hover:text-primary-600" @click="sortBy(col.key)">
                                {{ col.label }}
                                <span v-if="filters.sort === col.key" class="ml-0.5">{{ filters.dir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <tr v-for="row in report.data" :key="row.id" class="cursor-pointer hover:bg-primary-50/40" @click="openRecord(row.id)" title="View full contract, quest and ledger details">
                            <td class="px-3 py-3 text-xs whitespace-nowrap">{{ dateLabel(row.funded_at) }}</td>
                            <td class="px-3 py-3 text-xs whitespace-nowrap">{{ dateLabel(row.released_at) }}</td>
                            <td class="px-3 py-3 font-mono text-xs font-bold">{{ row.escrow_reference }}</td>
                            <td class="px-3 py-3 font-bold">{{ row.contract_reference || '—' }}</td>
                            <td class="px-3 py-3">{{ row.client_name }}</td>
                            <td class="px-3 py-3">{{ row.freelancer_name }}</td>
                            <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.status)">{{ row.status_label }}</span></td>
                            <td class="px-3 py-3 font-black">{{ row.gross_display }}</td>
                            <td class="px-3 py-3 text-xs">{{ row.vat_percent }}%</td>
                            <td class="px-3 py-3 font-black text-indigo-700">{{ row.vat_display }}</td>
                            <td class="px-3 py-3 text-xs" :class="row.vat_status === 'Recognised' ? 'text-emerald-600' : 'text-slate-500'">{{ row.vat_status }}</td>
                            <td class="px-3 py-3 text-xs font-bold">{{ row.cumulative_vat_display || '—' }}</td>
                        </tr>
                        <tr v-if="!report.data?.length">
                            <td :colspan="columns.length" class="px-3 py-8 text-center text-sm font-semibold text-slate-500">No contracts in this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="text-center text-xs font-semibold text-slate-400">Click any row to open the full escrow record — quest title, category, fees, and ledger trail.</p>

            <div v-if="report.meta?.last_page > 1" class="flex items-center justify-between text-xs font-bold" :class="shell.cardMuted">
                <span>Page {{ report.meta.current_page }} of {{ report.meta.last_page }} · {{ report.meta.total }} records</span>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg px-3 py-1.5" :class="shell.btnGhost" :disabled="report.meta.current_page <= 1" @click="goPage(report.meta.current_page - 1)">Prev</button>
                    <button type="button" class="rounded-lg px-3 py-1.5" :class="shell.btnGhost" :disabled="report.meta.current_page >= report.meta.last_page" @click="goPage(report.meta.current_page + 1)">Next</button>
                </div>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import FinancialAuditNav from '@/Components/Admin/FinancialAuditNav.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    report: { type: Object, required: true },
});

const shell = useInjectedAdminTheme();
const filters = reactive({
    q: '',
    from: props.report.filters?.from ?? '',
    to: props.report.filters?.to ?? '',
    date_field: props.report.filters?.date_field ?? 'funded_at',
    status: props.report.filters?.status ?? '',
    sort: props.report.filters?.sort ?? 'funded_at',
    dir: props.report.filters?.dir ?? 'desc',
});
let timer;

const columns = [
    { key: 'funded_at', label: 'Funded' },
    { key: 'released_at', label: 'Released' },
    { key: 'escrow_reference', label: 'Escrow ref' },
    { key: 'contract_reference', label: 'Contract' },
    { key: 'client_name', label: 'Client' },
    { key: 'freelancer_name', label: 'Freelancer' },
    { key: 'status', label: 'Status' },
    { key: 'total_funded_minor', label: 'Gross' },
    { key: 'vat_percent', label: 'VAT %' },
    { key: 'vat_minor', label: 'VAT' },
    { key: 'vat_status', label: 'VAT status' },
    { key: 'cumulative_vat', label: 'Cumulative' },
];

const exportUrl = computed(() => route('admin.financial-audit.reports.vat.export', clean(filters)));

function clean(obj) {
    const out = {};
    Object.entries(obj).forEach(([k, v]) => { if (v !== '' && v != null) out[k] = v; });
    return out;
}

function apply(extra = {}) {
    router.get(route('admin.financial-audit.reports.vat'), { ...clean(filters), ...extra }, { preserveScroll: true, preserveState: true });
}

function applyAllTime() {
    apply({ all_time: 1, from: '', to: '' });
}

function debouncedApply() {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
}

function sortBy(key) {
    if (['vat_status', 'cumulative_vat', 'client_name', 'freelancer_name', 'vat_percent', 'escrow_reference'].includes(key)) return;
    filters.dir = filters.sort === key && filters.dir === 'desc' ? 'asc' : 'desc';
    filters.sort = key;
    apply();
}

function goPage(page) {
    apply({ page });
}

function openRecord(id) {
    router.visit(route('admin.financial-audit.escrow-records.show', id));
}

function statusClass(status) {
    const map = { held: 'bg-sky-100 text-sky-800', disputed: 'bg-amber-100 text-amber-900', released: 'bg-emerald-100 text-emerald-800', refunded: 'bg-rose-100 text-rose-800' };
    return map[status] ?? 'bg-slate-100 text-slate-700';
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value));
}
</script>
