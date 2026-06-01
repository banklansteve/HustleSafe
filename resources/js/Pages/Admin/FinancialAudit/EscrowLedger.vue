<template>
    <AdminShell title="Escrow ledger" subtitle="Complete financial record of every escrow — search, filter, export for bank reconciliation.">
        <div class="space-y-5">
            <FinancialAuditNav active="ledger" />

            <AdminPanel title="Filters" description="Narrow by status, date funded, category, or amount.">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <input v-model="filters.q" type="search" placeholder="Search reference, quest, names, Paystack…" class="rounded-2xl border px-4 py-3 text-sm font-semibold xl:col-span-2" :class="shell.input" @input="debouncedApply" />
                    <select v-model="filters.status" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply">
                        <option value="">All statuses</option>
                        <option v-for="s in statuses" :key="s" :value="s">{{ s.replace(/_/g, ' ') }}</option>
                    </select>
                    <select v-model="filters.category_id" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply">
                        <option value="">All categories</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <input v-model="filters.from" type="date" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" />
                    <input v-model="filters.to" type="date" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" />
                    <input v-model="filters.amount_min" type="number" placeholder="Min amount (kobo)" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" />
                    <input v-model="filters.amount_max" type="number" placeholder="Max amount (kobo)" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="apply" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a :href="exportCsvUrl" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white">Export CSV</a>
                    <a :href="exportPdfUrl" class="rounded-full border px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost">Export PDF</a>
                </div>
            </AdminPanel>

            <div v-if="listing.totals?.count" class="grid gap-2 sm:grid-cols-4">
                <div class="rounded-2xl border p-3 text-center" :class="shell.card">
                    <p class="text-[10px] font-black uppercase text-slate-500">{{ listing.totals.count }} records</p>
                    <p class="mt-1 text-sm font-black">Gross {{ listing.totals.gross_display }}</p>
                </div>
                <div class="rounded-2xl border p-3 text-center" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Fees</p><p class="mt-1 text-sm font-black">{{ listing.totals.fee_display }}</p></div>
                <div class="rounded-2xl border p-3 text-center" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">VAT</p><p class="mt-1 text-sm font-black">{{ listing.totals.vat_display }}</p></div>
                <div class="rounded-2xl border p-3 text-center" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Freelancer net</p><p class="mt-1 text-sm font-black">{{ listing.totals.net_display }}</p></div>
            </div>

            <div class="overflow-x-auto rounded-3xl border shadow-sm" :class="shell.card">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                            <th class="px-3 py-3">Escrow ref</th>
                            <th class="px-3 py-3">Contract</th>
                            <th class="px-3 py-3">Quest</th>
                            <th class="px-3 py-3">Client</th>
                            <th class="px-3 py-3">Freelancer</th>
                            <th class="px-3 py-3">Gross</th>
                            <th class="px-3 py-3">Fee</th>
                            <th class="px-3 py-3">VAT</th>
                            <th class="px-3 py-3">Net</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Funded</th>
                            <th class="px-3 py-3">Due</th>
                            <th class="px-3 py-3">Released</th>
                            <th class="px-3 py-3">Paystack</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <tr
                            v-for="row in listing.data"
                            :key="row.id"
                            class="cursor-pointer hover:bg-primary-50/50 dark:hover:bg-white/[0.03]"
                            @click="openRecord(row.id)"
                        >
                            <td class="px-3 py-3 font-black">{{ row.escrow_reference }}</td>
                            <td class="px-3 py-3 text-xs font-bold">{{ row.contract_reference || '—' }}</td>
                            <td class="max-w-[11rem] truncate px-3 py-3 font-semibold">{{ row.quest_title }}</td>
                            <td class="px-3 py-3">{{ row.client_name }}</td>
                            <td class="px-3 py-3">{{ row.freelancer_name }}</td>
                            <td class="px-3 py-3 font-black">{{ row.gross_display }}</td>
                            <td class="px-3 py-3 text-xs">{{ row.platform_fee_display }}</td>
                            <td class="px-3 py-3 text-xs">{{ row.vat_display }}</td>
                            <td class="px-3 py-3 font-semibold text-emerald-700">{{ row.freelancer_net_display }}</td>
                            <td class="px-3 py-3 capitalize text-xs">{{ row.status.replace(/_/g, ' ') }}</td>
                            <td class="px-3 py-3 text-xs whitespace-nowrap">{{ dateLabel(row.funded_at) }}</td>
                            <td class="px-3 py-3 text-xs whitespace-nowrap">
                                <span :class="row.is_overdue ? 'font-black text-rose-600' : ''">{{ row.due_date_label || '—' }}</span>
                            </td>
                            <td class="px-3 py-3 text-xs whitespace-nowrap">{{ dateLabel(row.released_at) }}</td>
                            <td class="max-w-[8rem] truncate px-3 py-3 font-mono text-[10px]">{{ row.paystack_reference || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="listing.meta?.last_page > 1" class="flex items-center justify-between text-xs font-bold" :class="shell.cardMuted">
                <span>Page {{ listing.meta.current_page }} of {{ listing.meta.last_page }} · {{ listing.meta.total }} records</span>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg px-3 py-1.5" :class="shell.btnGhost" :disabled="listing.meta.current_page <= 1" @click="goPage(listing.meta.current_page - 1)">Prev</button>
                    <button type="button" class="rounded-lg px-3 py-1.5" :class="shell.btnGhost" :disabled="listing.meta.current_page >= listing.meta.last_page" @click="goPage(listing.meta.current_page + 1)">Next</button>
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
    listing: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const shell = useInjectedAdminTheme();
const filters = reactive({ ...props.listing.filters, category_id: props.listing.filters?.category_id ?? '', amount_min: props.listing.filters?.amount_min ?? '', amount_max: props.listing.filters?.amount_max ?? '' });
let timer;

const exportCsvUrl = computed(() => route('admin.financial-audit.escrow-ledger.export', { ...clean(filters), format: 'csv' }));
const exportPdfUrl = computed(() => route('admin.financial-audit.escrow-ledger.export', { ...clean(filters), format: 'pdf' }));

function clean(obj) {
    const out = {};
    Object.entries(obj).forEach(([k, v]) => { if (v !== '' && v != null) out[k] = v; });
    return out;
}

function apply() {
    router.get(route('admin.financial-audit.escrow-ledger'), clean(filters), { preserveScroll: true, preserveState: true });
}

function goPage(page) {
    router.get(route('admin.financial-audit.escrow-ledger'), { ...clean(filters), page }, { preserveScroll: true });
}

function debouncedApply() {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
}

function openRecord(id) {
    router.visit(route('admin.financial-audit.escrow-records.show', id));
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value));
}
</script>
