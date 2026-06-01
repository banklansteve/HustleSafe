<template>
    <AdminShell title="Reconciliation" subtitle="Period totals, escrow position as of any date, and reconciliation run history.">
        <div class="space-y-5">
            <FinancialAuditNav active="reconciliation" />

            <AdminPanel title="Date filters" description="Choose a reporting period and an as-of date for escrow held.">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="text-[10px] font-black uppercase text-slate-400">Period from</label>
                        <input v-model="filters.from" type="date" class="mt-1 block rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase text-slate-400">Period to</label>
                        <input v-model="filters.to" type="date" class="mt-1 block rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase text-slate-400">Escrow held as of</label>
                        <input v-model="filters.as_of" type="date" class="mt-1 block rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                    </div>
                    <button type="button" class="rounded-xl bg-primary-600 px-4 py-2.5 text-xs font-black uppercase text-white" @click="apply">Apply</button>
                    <a :href="exportUrl" class="rounded-xl border px-4 py-2.5 text-xs font-black uppercase" :class="shell.btnGhost">Export CSV</a>
                </div>
            </AdminPanel>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="tile in periodTiles" :key="tile.label" class="rounded-2xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                    <p class="mt-2 text-xl font-black" :class="shell.title">{{ tile.value }}</p>
                    <p v-if="tile.hint" class="mt-1 text-xs font-semibold" :class="shell.cardMuted">{{ tile.hint }}</p>
                </div>
            </section>

            <AdminPanel
                :title="`Escrow held as of ${report.as_of_position?.as_of_label}`"
                :description="`${report.as_of_position?.active_count} contract(s) · ${report.as_of_position?.total_held_display} total`"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                <th class="px-3 py-2">Escrow ref</th>
                                <th class="px-3 py-2">Contract</th>
                                <th class="px-3 py-2">Quest</th>
                                <th class="px-3 py-2">Client</th>
                                <th class="px-3 py-2">Freelancer</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Funded</th>
                                <th class="px-3 py-2">Due</th>
                                <th class="px-3 py-2">Held</th>
                                <th class="px-3 py-2">Fee</th>
                                <th class="px-3 py-2">VAT</th>
                                <th class="px-3 py-2">Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <tr
                                v-for="row in report.as_of_position?.contracts"
                                :key="row.id"
                                class="cursor-pointer hover:bg-primary-50/40"
                                @click="openRecord(row.id)"
                            >
                                <td class="px-3 py-3 font-black">{{ row.escrow_reference }}</td>
                                <td class="px-3 py-3 text-xs">{{ row.contract_reference || '—' }}</td>
                                <td class="max-w-[10rem] truncate px-3 py-3 font-semibold">{{ row.quest_title }}</td>
                                <td class="px-3 py-3">{{ row.client_name }}</td>
                                <td class="px-3 py-3">{{ row.freelancer_name }}</td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.status)">{{ row.status_label }}</span></td>
                                <td class="px-3 py-3 text-xs whitespace-nowrap">{{ dateLabel(row.funded_at) }}</td>
                                <td class="px-3 py-3 text-xs">{{ row.due_date_label || '—' }}</td>
                                <td class="px-3 py-3 font-black">{{ row.funded_display }}</td>
                                <td class="px-3 py-3 text-xs">{{ row.platform_fee_display }}</td>
                                <td class="px-3 py-3 text-xs">{{ row.vat_display }}</td>
                                <td class="px-3 py-3 font-semibold text-emerald-700">{{ row.freelancer_net_display }}</td>
                            </tr>
                            <tr v-if="!report.as_of_position?.contracts?.length">
                                <td colspan="12" class="px-3 py-8 text-center text-sm font-semibold text-slate-500">No escrow held on this date.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AdminPanel>

            <AdminPanel title="Reconciliation runs" description="Automated hourly checks — gateway match, ledger balance, escrow position.">
                <div class="mb-3 flex flex-wrap gap-3 text-xs font-semibold" :class="shell.cardMuted">
                    <span>Last run: {{ lastRunLabel }}</span>
                    <span class="capitalize">Status: {{ report.reconciliation?.last_run_status || '—' }}</span>
                    <span :class="report.reconciliation?.ledger_balanced ? 'text-emerald-600' : 'text-rose-600'">Ledger {{ report.reconciliation?.ledger_balanced ? 'balanced' : 'imbalanced' }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                <th class="px-3 py-2">Started</th>
                                <th class="px-3 py-2">Duration</th>
                                <th class="px-3 py-2">Result</th>
                                <th class="px-3 py-2">Processed</th>
                                <th class="px-3 py-2">Exceptions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <tr v-for="run in report.recent_runs" :key="run.id">
                                <td class="px-3 py-3 text-xs">{{ dateLabel(run.started_at) }}</td>
                                <td class="px-3 py-3 text-xs">{{ run.duration_seconds ?? '—' }}s</td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="run.passed ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">{{ run.passed ? 'Passed' : 'Failed' }}</span></td>
                                <td class="px-3 py-3 text-xs">{{ run.records_processed }}</td>
                                <td class="px-3 py-3 text-xs font-bold" :class="run.exceptions_found > 0 ? 'text-amber-700' : ''">{{ run.exceptions_found }}</td>
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
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    report: { type: Object, required: true },
});

const shell = useInjectedAdminTheme();
const filters = reactive({
    from: props.report.filters?.from ?? '',
    to: props.report.filters?.to ?? '',
    as_of: props.report.filters?.as_of ?? '',
});

const periodTiles = computed(() => [
    { label: 'Escrow funded', value: props.report.period?.escrow_funded_display, hint: `${props.report.period?.escrow_funding_count ?? 0} funding(s)` },
    { label: 'Platform fees', value: props.report.period?.platform_fee_display, hint: 'Recognised on release' },
    { label: 'VAT accrued', value: props.report.period?.vat_display, hint: '7.5% of platform fee' },
    { label: 'Released (gross)', value: props.report.period?.released_gross_display, hint: `${props.report.period?.released_count ?? 0} release(s)` },
    { label: 'To freelancers', value: props.report.period?.released_to_freelancers_display },
    { label: 'Refunded', value: props.report.period?.refunded_display },
]);

const exportUrl = computed(() => route('admin.financial-audit.reconciliation.export', clean(filters)));

const lastRunLabel = computed(() => {
    const at = props.report.reconciliation?.last_run_at;
    if (!at) return 'Never';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(at));
});

function clean(obj) {
    const out = {};
    Object.entries(obj).forEach(([k, v]) => { if (v) out[k] = v; });
    return out;
}

function apply() {
    router.get(route('admin.financial-audit.reconciliation.index'), clean(filters), { preserveScroll: true });
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
