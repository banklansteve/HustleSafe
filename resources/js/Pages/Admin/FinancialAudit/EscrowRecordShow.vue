<template>
    <AdminShell :title="detail.record.escrow_reference" subtitle="Complete escrow financial story and double-entry ledger trail.">
        <div class="space-y-5">
            <FinancialAuditNav active="ledger" />
            <Link :href="route('admin.financial-audit.escrow-ledger')" class="text-xs font-black uppercase tracking-wide text-primary-600 hover:underline">← Escrow ledger</Link>

            <div class="grid gap-4 lg:grid-cols-3">
                <section class="rounded-3xl border p-5 lg:col-span-2" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Escrow record</p>
                    <h2 class="mt-1 font-display text-xl font-black">{{ detail.record.quest_title }}</h2>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                        <div><dt class="font-bold text-slate-500">Client (paid)</dt><dd class="font-black">{{ detail.record.client_name }}</dd><dd v-if="detail.record.client_email" class="text-xs text-slate-400">{{ detail.record.client_email }}</dd></div>
                        <div><dt class="font-bold text-slate-500">Freelancer (earns)</dt><dd class="font-black">{{ detail.record.freelancer_name }}</dd><dd v-if="detail.record.freelancer_email" class="text-xs text-slate-400">{{ detail.record.freelancer_email }}</dd></div>
                        <div><dt class="font-bold text-slate-500">Contract</dt><dd>{{ detail.record.contract_reference || '—' }}</dd></div>
                        <div><dt class="font-bold text-slate-500">Category</dt><dd>{{ detail.record.category || '—' }}</dd></div>
                        <div><dt class="font-bold text-slate-500">Gateway</dt><dd>{{ detail.record.gateway_name }} · {{ detail.record.paystack_reference || '—' }}</dd></div>
                        <div><dt class="font-bold text-slate-500">Status</dt><dd class="font-black capitalize">{{ detail.record.status_label }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-3xl border border-teal-200 bg-gradient-to-br from-teal-50/80 to-white p-5 dark:from-teal-950/20">
                    <p class="text-[10px] font-black uppercase text-teal-800">Money breakdown</p>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><dt>Gross funded</dt><dd class="font-black">{{ detail.record.funded_display }}</dd></div>
                        <div class="flex justify-between text-amber-800"><dt>Platform fee ({{ detail.record.platform_fee_percent }}%)</dt><dd class="font-black">− {{ detail.record.platform_fee_display }}</dd></div>
                        <div class="flex justify-between text-indigo-800"><dt>VAT on fee ({{ detail.record.vat_percent }}%)</dt><dd class="font-black">− {{ detail.record.vat_display }}</dd></div>
                        <div class="flex justify-between border-t border-teal-200 pt-2 text-emerald-800"><dt class="font-black">Freelancer net</dt><dd class="font-black">{{ detail.record.freelancer_net_display }}</dd></div>
                    </dl>
                </section>
            </div>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border p-4" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Funded</p><p class="mt-1 text-sm font-black">{{ dateLabel(detail.record.funded_at) }}</p></div>
                <div class="rounded-2xl border p-4" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Due date</p><p class="mt-1 text-sm font-black" :class="detail.record.is_overdue ? 'text-rose-600' : ''">{{ detail.record.due_date_label || '—' }}</p></div>
                <div class="rounded-2xl border p-4" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Released</p><p class="mt-1 text-sm font-black">{{ dateLabel(detail.record.released_at) }}</p><p v-if="detail.record.release_trigger_label" class="text-xs capitalize text-slate-500">{{ detail.record.release_trigger_label }}</p></div>
                <div class="rounded-2xl border p-4" :class="shell.card"><p class="text-[10px] font-black uppercase text-slate-500">Fee recognised</p><p class="mt-1 text-sm font-black">{{ dateLabel(detail.record.fee_recognised_at) }}</p></div>
            </section>

            <AdminPanel title="Double-entry ledger trail" :description="detail.ledger_trail_balanced ? 'Debits equal credits for this escrow.' : 'Warning: trail imbalance detected.'">
                <div v-if="!detail.ledger_trail?.length" class="text-sm font-semibold text-slate-500">No ledger entries yet. Run backfill if this escrow was funded before the audit system launched.</div>
                <div v-else class="space-y-4">
                    <article v-for="(batch, i) in detail.ledger_trail" :key="i" class="rounded-2xl border p-4" :class="shell.card">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-black">{{ batch.reference }}</p>
                                <p class="text-xs font-bold capitalize text-primary-700">{{ batch.event_label }}</p>
                            </div>
                            <p class="text-xs text-slate-400">{{ dateLabel(batch.occurred_at) }}</p>
                        </div>
                        <p v-if="batch.description" class="mt-2 text-sm text-slate-600">{{ batch.description }}</p>
                        <p v-if="batch.reversal_reason" class="mt-1 text-xs font-bold text-rose-600">Reversal: {{ batch.reversal_reason }}</p>
                        <table class="mt-3 w-full text-xs">
                            <thead><tr class="text-left text-[10px] font-black uppercase text-slate-400"><th class="py-1">Account</th><th>Side</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>
                                <tr v-for="(entry, j) in batch.entries" :key="j" class="border-t border-slate-100 dark:border-white/10">
                                    <td class="py-2 font-semibold">{{ entry.account }}</td>
                                    <td class="py-2 capitalize" :class="entry.side === 'debit' ? 'text-rose-600' : 'text-emerald-600'">{{ entry.side }}</td>
                                    <td class="py-2 text-right font-black">{{ entry.amount_display }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="mt-2 text-[10px] text-slate-400">Process: {{ batch.created_by_process }}</p>
                    </article>
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
import { Link } from '@inertiajs/vue3';

defineProps({
    detail: { type: Object, required: true },
});

const shell = useInjectedAdminTheme();

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
</script>
