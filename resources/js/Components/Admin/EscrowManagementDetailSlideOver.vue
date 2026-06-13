<template>
    <AdminSlideOver
        :open="open"
        :title="detail?.record?.contract_reference || detail?.record?.escrow_reference || 'Escrow detail'"
        eyebrow="Escrow management"
        width-class="max-w-2xl"
        @close="emit('close')"
    >
        <div v-if="loading" class="rounded-2xl border p-6 text-sm font-bold" :class="shell.card">Loading escrow detail…</div>

        <div v-else-if="detail" class="space-y-5 pb-8">
            <div class="rounded-2xl border p-4" :class="statusBannerClass">
                <p class="text-[10px] font-black uppercase tracking-wider opacity-80">Status</p>
                <p class="mt-1 text-sm font-black">{{ detail.management?.status_headline }}</p>
            </div>

            <section class="rounded-2xl border p-4" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Quest</p>
                <h3 class="mt-1 font-display text-lg font-black" :class="shell.title">{{ detail.record.quest_title }}</h3>
                <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                    <div>
                        <dt class="font-bold text-slate-500">Client</dt>
                        <dd class="font-black">{{ detail.management.parties?.client?.name }}</dd>
                        <dd class="text-slate-500">@{{ detail.management.parties?.client?.username }}</dd>
                    </div>
                    <div>
                        <dt class="font-bold text-slate-500">Freelancer</dt>
                        <dd class="font-black">{{ detail.management.parties?.freelancer?.name }}</dd>
                        <dd class="text-slate-500">@{{ detail.management.parties?.freelancer?.username }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-teal-200 bg-gradient-to-br from-teal-50/90 to-white p-4 dark:from-teal-950/20">
                <p class="text-[10px] font-black uppercase text-teal-800">Financial breakdown</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt>Client funded (gross)</dt><dd class="font-black">{{ detail.record.funded_display }}</dd></div>
                    <div class="flex justify-between text-amber-800"><dt>Platform fee ({{ detail.record.platform_fee_percent }}%)</dt><dd class="font-black">− {{ detail.record.platform_fee_display }}</dd></div>
                    <div class="flex justify-between text-indigo-800"><dt>VAT on fee ({{ detail.record.vat_percent }}%)</dt><dd class="font-black">− {{ detail.record.vat_display }}</dd></div>
                    <div class="flex justify-between border-t border-teal-200 pt-2 text-emerald-800"><dt class="font-black">Freelancer net payout</dt><dd class="font-black">{{ detail.record.freelancer_net_display }}</dd></div>
                </dl>
            </section>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="rounded-xl border p-3" :class="shell.card">
                    <p class="text-[10px] font-black uppercase text-slate-500">Funded</p>
                    <p class="mt-1 text-sm font-black">{{ dateLabel(detail.record.funded_at) }}</p>
                </div>
                <div class="rounded-xl border p-3" :class="shell.card">
                    <p class="text-[10px] font-black uppercase text-slate-500">Scheduled release</p>
                    <p class="mt-1 text-sm font-black">{{ detail.management.scheduled_release_label || '—' }}</p>
                </div>
            </div>

            <section v-if="detail.management.timeline?.length" class="space-y-3">
                <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Escrow timeline</p>
                <article
                    v-for="step in detail.management.timeline"
                    :key="step.key"
                    class="rounded-xl border p-3"
                    :class="[shell.card, step.current ? 'ring-2 ring-primary-400' : '']"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-black">{{ step.label }}</p>
                        <span v-if="step.completed" class="text-emerald-600">✓</span>
                        <span v-else-if="step.current" class="text-amber-600">⏳</span>
                    </div>
                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ step.at_label }}</p>
                    <p v-if="step.detail" class="mt-1 text-xs text-slate-500">{{ step.detail }}</p>
                </article>
            </section>

            <section v-if="detail.ledger_trail?.length" class="space-y-3">
                <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Ledger entries</p>
                <article v-for="(batch, i) in detail.ledger_trail" :key="i" class="rounded-xl border p-3 text-xs" :class="shell.card">
                    <div class="flex justify-between gap-2">
                        <p class="font-black">{{ batch.reference }}</p>
                        <p class="text-slate-400">{{ dateLabel(batch.occurred_at) }}</p>
                    </div>
                    <p class="mt-1 capitalize text-primary-700">{{ batch.event_label }}</p>
                    <div v-for="(entry, j) in batch.entries" :key="j" class="mt-1 flex justify-between border-t border-slate-100 pt-1 dark:border-white/10">
                        <span>{{ entry.account }} · {{ entry.side }}</span>
                        <span class="font-black">{{ entry.amount_display }}</span>
                    </div>
                </article>
            </section>

            <div v-if="detail.management.action_routes?.escrow_action" class="space-y-3 rounded-2xl border p-4" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Super Admin actions</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-full bg-emerald-700 px-4 py-2 text-[10px] font-black uppercase text-white" @click="openAction('manual_release')">Approve & release</button>
                    <button type="button" class="rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-[10px] font-black uppercase text-amber-950" @click="openAction('manual_hold')">Hold</button>
                    <button type="button" class="rounded-full border border-slate-200 px-4 py-2 text-[10px] font-black uppercase" :class="shell.btnGhost" @click="openAction('freeze')">Freeze</button>
                    <button type="button" class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-[10px] font-black uppercase text-rose-800" @click="openAction('full_refund')">Refund</button>
                </div>

                <form v-if="actionOpen" class="mt-3 space-y-2 border-t pt-3" @submit.prevent="submitAction">
                    <p class="text-xs font-black capitalize">{{ actionForm.action.replace(/_/g, ' ') }}</p>
                    <input v-model="actionForm.amount" type="number" min="0" step="0.01" placeholder="Amount (NGN)" class="w-full rounded-xl border px-3 py-2 text-sm" :class="shell.input" />
                    <textarea v-model="actionForm.reason" rows="3" required minlength="10" placeholder="Audit reason (required)" class="w-full rounded-xl border px-3 py-2 text-sm" :class="shell.input" />
                    <p v-if="actionError" class="text-xs font-bold text-rose-600">{{ actionError }}</p>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white" :disabled="actionBusy">Execute</button>
                        <button type="button" class="rounded-full border px-4 py-2 text-xs font-black uppercase" @click="actionOpen = false">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link v-if="detail.management.contract_url" :href="detail.management.contract_url" class="text-xs font-black uppercase text-primary-700 underline">View contract</Link>
                <Link v-if="detail.management.audit_record_url" :href="detail.management.audit_record_url" class="text-xs font-black uppercase text-primary-700 underline">Full audit record</Link>
                <Link v-if="detail.management.active_dispute" :href="detail.management.active_dispute.url" class="text-xs font-black uppercase text-rose-700 underline">Open dispute</Link>
            </div>
        </div>
    </AdminSlideOver>
</template>

<script setup>
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { Link } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    recordId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'updated']);

const { shell } = useInjectedAdminTheme();
const loading = ref(false);
const detail = ref(null);
const actionOpen = ref(false);
const actionBusy = ref(false);
const actionError = ref('');
const actionForm = reactive({ action: 'manual_release', amount: '', reason: '' });

const statusBannerClass = computed(() => {
    const tone = detail.value?.management?.status_tone;
    if (tone === 'urgent') return 'border-rose-200 bg-rose-50 text-rose-950';
    if (tone === 'warning') return 'border-amber-200 bg-amber-50 text-amber-950';
    if (tone === 'released') return 'border-slate-200 bg-slate-50 text-slate-700';
    return 'border-emerald-200 bg-emerald-50 text-emerald-950';
});

watch(
    () => [props.open, props.recordId],
    async ([open, id]) => {
        if (!open || !id) {
            detail.value = null;
            return;
        }
        loading.value = true;
        actionOpen.value = false;
        try {
            const { data } = await window.axios.get(route('admin.escrow-management.records.show', id));
            detail.value = data;
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function openAction(action) {
    actionForm.action = action;
    actionForm.reason = '';
    actionForm.amount = detail.value?.record?.freelancer_net_minor
        ? (detail.value.record.freelancer_net_minor / 100).toFixed(2)
        : '';
    actionError.value = '';
    actionOpen.value = true;
}

async function submitAction() {
    const url = detail.value?.management?.action_routes?.escrow_action;
    if (!url || actionForm.reason.trim().length < 10) {
        actionError.value = 'Enter a clear audit reason (at least 10 characters).';
        return;
    }
    if (!window.confirm(`Execute ${actionForm.action.replace(/_/g, ' ')} on this escrow?`)) {
        return;
    }
    actionBusy.value = true;
    actionError.value = '';
    try {
        await window.axios.post(url, actionForm);
        actionOpen.value = false;
        emit('updated');
        const { data } = await window.axios.get(route('admin.escrow-management.records.show', props.recordId));
        detail.value = data;
    } catch (err) {
        actionError.value = err?.response?.data?.message || 'Action failed.';
    } finally {
        actionBusy.value = false;
    }
}
</script>
