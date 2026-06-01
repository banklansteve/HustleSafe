<template>
    <AdminShell title="Platform fee report" subtitle="Cumulative platform revenue by source. Click a row for full quest, contract and ledger details.">
        <div class="space-y-5">
            <FinancialAuditNav active="fees" />

            <!-- Revenue source tabs -->
            <section class="rounded-3xl border p-4 shadow-sm" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Revenue source</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="source in report.revenue_sources"
                        :key="source.key"
                        type="button"
                        class="relative rounded-full px-4 py-2 text-xs font-black uppercase tracking-wide transition"
                        :class="activeSource === source.key ? 'bg-primary-600 text-white shadow-sm' : shell.btnGhost"
                        @click="switchSource(source.key)"
                    >
                        {{ source.label }}
                        <span v-if="source.coming_soon" class="ml-1 rounded-full bg-amber-400 px-1.5 py-0.5 text-[9px] text-amber-950">Soon</span>
                    </button>
                </div>
                <p v-if="activeSourceMeta?.description" class="mt-2 text-xs font-semibold" :class="shell.cardMuted">{{ activeSourceMeta.description }}</p>
            </section>

            <!-- Cumulative revenue summary -->
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50/80 to-white p-4 dark:from-amber-950/20 xl:col-span-1">
                    <p class="text-[10px] font-black uppercase text-amber-800">Total platform revenue</p>
                    <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white">{{ report.revenue_summary?.total_display }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ report.filters?.label }}</p>
                </div>
                <div v-for="tile in revenueTiles" :key="tile.key" class="rounded-2xl border p-4" :class="[shell.card, tile.muted ? 'opacity-70' : '']">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                    <p class="mt-2 text-lg font-black" :class="shell.title">{{ tile.value }}</p>
                    <p v-if="tile.hint" class="mt-1 text-[10px] font-bold uppercase text-amber-600">{{ tile.hint }}</p>
                </div>
            </section>

            <AdminPanel title="Filters" description="Filter by date field, status, or search. Click column headers to sort.">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <input v-model="filters.q" type="search" placeholder="Search contract ref, escrow ref, names…" class="rounded-2xl border px-4 py-3 text-sm font-semibold xl:col-span-2" :class="shell.input" @input="debouncedApply" />
                    <select v-model="filters.date_field" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" :disabled="report.coming_soon">
                        <option v-for="f in report.date_fields" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                    <select v-model="filters.status" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" :disabled="report.coming_soon">
                        <option value="">All statuses</option>
                        <option v-for="s in report.statuses" :key="s" :value="s">{{ s.replace(/_/g, ' ') }}</option>
                    </select>
                    <input v-model="filters.from" type="date" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" :disabled="report.coming_soon" />
                    <input v-model="filters.to" type="date" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" :disabled="report.coming_soon" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="rounded-full px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost" :disabled="report.coming_soon" @click="applyAllTime">All time</button>
                    <a :href="exportUrl" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white">Export CSV</a>
                </div>
            </AdminPanel>

            <div v-if="report.coming_soon" class="rounded-3xl border border-dashed border-amber-300 bg-amber-50/50 p-10 text-center dark:bg-amber-950/20">
                <p class="font-display text-lg font-black text-amber-900 dark:text-amber-100">{{ activeSourceMeta?.label }} — coming soon</p>
                <p class="mx-auto mt-2 max-w-md text-sm font-semibold text-amber-800/80">{{ report.coming_soon_message }}</p>
                <p class="mt-4 text-xs text-slate-500">This stream will feed into cumulative platform revenue on dashboards and reports once live.</p>
            </div>

            <template v-else>
                <div v-if="report.totals?.count" class="grid gap-2 sm:grid-cols-3">
                    <div class="rounded-2xl border p-3 text-center" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">{{ report.totals.count }} contracts</p></div>
                    <div class="rounded-2xl border p-3 text-center" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Contract gross</p><p class="mt-1 text-sm font-black">{{ report.totals.gross_display }}</p></div>
                    <div class="rounded-2xl border p-3 text-center" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Net platform revenue</p><p class="mt-1 text-sm font-black text-amber-700">{{ report.totals.revenue_display }}</p></div>
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
                                <td class="px-3 py-3 text-xs whitespace-nowrap">{{ dateLabel(row.fee_recognised_at) }}</td>
                                <td class="px-3 py-3 font-mono text-xs font-bold">{{ row.escrow_reference }}</td>
                                <td class="px-3 py-3 font-bold">{{ row.contract_reference || '—' }}</td>
                                <td class="px-3 py-3">{{ row.client_name }}</td>
                                <td class="px-3 py-3">{{ row.freelancer_name }}</td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.status)">{{ row.status_label }}</span></td>
                                <td class="px-3 py-3 font-black">{{ row.gross_display }}</td>
                                <td class="px-3 py-3">{{ row.platform_fee_display }}</td>
                                <td class="px-3 py-3 font-black text-amber-700">{{ row.platform_revenue_display }}</td>
                            </tr>
                            <tr v-if="!report.data?.length">
                                <td :colspan="columns.length" class="px-3 py-8 text-center text-sm font-semibold text-slate-500">No contracts in this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-center text-xs font-semibold text-slate-400">Click any row to open the full escrow record — quest title, category, VAT, and ledger trail.</p>

                <div v-if="report.meta?.last_page > 1" class="flex items-center justify-between text-xs font-bold" :class="shell.cardMuted">
                    <span>Page {{ report.meta.current_page }} of {{ report.meta.last_page }} · {{ report.meta.total }} records</span>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg px-3 py-1.5" :class="shell.btnGhost" :disabled="report.meta.current_page <= 1" @click="goPage(report.meta.current_page - 1)">Prev</button>
                        <button type="button" class="rounded-lg px-3 py-1.5" :class="shell.btnGhost" :disabled="report.meta.current_page >= report.meta.last_page" @click="goPage(report.meta.current_page + 1)">Next</button>
                    </div>
                </div>
            </template>
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
const activeSource = computed(() => props.report.filters?.source ?? 'escrow_fees');
const activeSourceMeta = computed(() => props.report.revenue_sources?.find((s) => s.key === activeSource.value));

const filters = reactive({
    q: '',
    from: props.report.filters?.from ?? '',
    to: props.report.filters?.to ?? '',
    date_field: props.report.filters?.date_field ?? 'funded_at',
    status: props.report.filters?.status ?? '',
    sort: props.report.filters?.sort ?? 'funded_at',
    dir: props.report.filters?.dir ?? 'desc',
    source: activeSource.value,
});
let timer;

const columns = [
    { key: 'funded_at', label: 'Funded' },
    { key: 'released_at', label: 'Released' },
    { key: 'fee_recognised_at', label: 'Fee recognised' },
    { key: 'escrow_reference', label: 'Escrow ref' },
    { key: 'contract_reference', label: 'Contract' },
    { key: 'client_name', label: 'Client' },
    { key: 'freelancer_name', label: 'Freelancer' },
    { key: 'status', label: 'Status' },
    { key: 'total_funded_minor', label: 'Gross' },
    { key: 'platform_fee_minor', label: 'Gross fee' },
    { key: 'platform_revenue_minor', label: 'Net revenue' },
];

const revenueTiles = computed(() => [
    { key: 'escrow_fees', label: 'Escrow fees', value: props.report.revenue_summary?.escrow_fees_display, muted: false },
    { key: 'quest_boosts', label: 'Quest boosts', value: props.report.revenue_summary?.quest_boosts_display, hint: 'Coming soon', muted: true },
    { key: 'premium_freelancers', label: 'Premium freelancers', value: props.report.revenue_summary?.premium_freelancers_display, hint: 'Coming soon', muted: true },
]);

const exportUrl = computed(() => route('admin.financial-audit.reports.platform-fees.export', clean(filters)));

function clean(obj) {
    const out = {};
    Object.entries(obj).forEach(([k, v]) => { if (v !== '' && v != null) out[k] = v; });
    return out;
}

function apply(extra = {}) {
    router.get(route('admin.financial-audit.reports.platform-fees'), { ...clean(filters), ...extra }, { preserveScroll: true, preserveState: true });
}

function switchSource(source) {
    filters.source = source;
    apply({ source });
}

function applyAllTime() {
    apply({ all_time: 1, from: '', to: '' });
}

function debouncedApply() {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
}

function sortBy(key) {
    if (['client_name', 'freelancer_name', 'escrow_reference', 'platform_revenue_minor'].includes(key)) return;
    filters.dir = filters.sort === key && filters.dir === 'desc' ? 'asc' : 'desc';
    filters.sort = key === 'platform_revenue_minor' ? 'platform_fee_minor' : key;
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
