<template>
    <AdminSlideOver
        :open="open"
        :title="detail?.contract?.reference_code || 'Contract'"
        eyebrow="Contract detail"
        width-class="max-w-2xl lg:max-w-3xl"
        @close="emit('close')"
    >
        <div v-if="loading" class="flex justify-center py-16">
            <span class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
        </div>

        <div v-else-if="detail" class="space-y-6">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-primary-100 px-3 py-1 text-xs font-black uppercase text-primary-800">{{ detail.contract.status_label }}</span>
                <span class="rounded-full px-3 py-1 text-xs font-black uppercase" :class="riskBadgeClass(detail.risk?.level)">
                    {{ riskEmoji(detail.risk?.level) }} {{ detail.risk?.label }} risk
                </span>
                <span v-if="detail.contract.flagged_for_review" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black uppercase text-amber-900">Flagged</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-[10px] font-black uppercase text-slate-500">Quest</p>
                    <p class="mt-1 font-bold text-slate-900">{{ detail.quest?.title }}</p>
                    <p class="text-xs text-slate-500">{{ detail.quest?.reference_code }} · {{ detail.quest?.category }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4">
                    <p class="text-[10px] font-black uppercase text-emerald-800">Financial</p>
                    <p class="mt-1 text-xl font-black text-emerald-900">{{ detail.financial?.total_formatted }}</p>
                    <p class="text-xs font-semibold text-emerald-800">{{ detail.financial?.status_label }}</p>
                    <p class="mt-1 text-xs text-emerald-700">Net to freelancer: {{ detail.financial?.freelancer_net_formatted }}</p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div v-if="detail.parties?.client" class="rounded-2xl border p-4">
                    <p class="text-[10px] font-black uppercase text-slate-500">Client</p>
                    <p class="mt-1 font-bold">{{ detail.parties.client.name }}</p>
                    <p class="text-xs text-slate-500">Tier {{ detail.parties.client.tier }} · {{ detail.parties.client.completed_contracts }} completed</p>
                </div>
                <div v-if="detail.parties?.freelancer" class="rounded-2xl border p-4">
                    <p class="text-[10px] font-black uppercase text-slate-500">Freelancer</p>
                    <p class="mt-1 font-bold">{{ detail.parties.freelancer.name }}</p>
                    <p class="text-xs text-slate-500">Tier {{ detail.parties.freelancer.tier }} · ⭐ {{ detail.parties.freelancer.rating ?? '—' }}</p>
                </div>
            </div>

            <div class="rounded-2xl border p-4">
                <p class="text-[10px] font-black uppercase text-slate-500">Timeline</p>
                <ul class="mt-2 space-y-1 text-sm font-semibold text-slate-700">
                    <li>Awarded: {{ detail.timeline?.awarded_at || '—' }}</li>
                    <li>Expected delivery: {{ detail.timeline?.delivery_expected || '—' }}
                        <span v-if="detail.timeline?.days_overdue" class="font-black text-rose-700">(OVERDUE {{ detail.timeline.days_overdue }}d)</span>
                    </li>
                    <li>Delivery status: {{ detail.delivery?.status }}</li>
                    <li v-if="detail.timeline?.auto_release?.label">Auto-release: {{ detail.timeline.auto_release.label }}</li>
                </ul>
            </div>

            <div v-if="detail.dispute" class="rounded-2xl border border-rose-200 bg-rose-50/60 p-4">
                <p class="text-[10px] font-black uppercase text-rose-800">Active dispute</p>
                <p class="mt-1 text-sm font-bold text-rose-950">{{ detail.dispute.reason }}</p>
                <p class="mt-1 text-xs text-rose-700">Filed by {{ detail.dispute.filed_by }} · {{ detail.dispute.filed_at }}</p>
            </div>

            <div v-if="detail.patrol_flags?.length" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                <p class="text-[10px] font-black uppercase text-amber-800">Patrol alerts</p>
                <div v-for="flag in detail.patrol_flags" :key="flag.id" class="mt-3 border-t border-amber-200/80 pt-3 first:mt-0 first:border-0 first:pt-0">
                    <p class="text-sm font-black text-slate-900">
                        {{ flag.label }}
                        <span class="ml-2 rounded-full bg-white px-2 py-0.5 text-[10px] uppercase text-amber-900">{{ flag.severity }}</span>
                    </p>
                    <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-700">{{ flag.reason }}</p>
                    <div v-if="isSuperAdmin" class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-if="flag.status === 'open'"
                            type="button"
                            class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-[10px] font-black uppercase text-amber-900 disabled:opacity-50"
                            :disabled="actionBusy"
                            @click="ackPatrolFlag(flag.id)"
                        >
                            Acknowledge
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-slate-900 px-3 py-1.5 text-[10px] font-black uppercase text-white disabled:opacity-50"
                            :disabled="actionBusy"
                            @click="dismissPatrolFlag(flag.id)"
                        >
                            Close alert
                        </button>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border p-4">
                <p class="text-[10px] font-black uppercase text-slate-500">Risk assessment</p>
                <ul class="mt-2 list-disc space-y-1 pl-4 text-sm text-slate-700">
                    <li v-for="(reason, i) in detail.risk?.reasons || []" :key="i">{{ reason }}</li>
                </ul>
            </div>

            <div v-if="detail.delivery?.files?.length || detail.delivery?.delivery_url" class="rounded-2xl border p-4">
                <p class="text-[10px] font-black uppercase text-slate-500">Deliverables</p>
                <p v-if="detail.delivery.freelancer_notes" class="mt-2 text-sm text-slate-700">{{ detail.delivery.freelancer_notes }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="(file, i) in detail.delivery.files" :key="i" class="font-semibold text-primary-700">{{ file.label }} <span class="text-slate-500">{{ file.size_label }}</span></li>
                    <li v-if="detail.delivery.delivery_url">
                        <a :href="detail.delivery.delivery_url" target="_blank" rel="noopener" class="font-bold text-primary-700 underline">Live link</a>
                    </li>
                </ul>
            </div>

            <div v-if="detail.messages?.messages?.length" class="rounded-2xl border p-4">
                <p class="text-[10px] font-black uppercase text-slate-500">Message transcript ({{ detail.messages.total }} total)</p>
                <ul class="mt-3 max-h-56 space-y-2 overflow-y-auto text-sm">
                    <li v-for="msg in detail.messages.messages" :key="msg.id" class="rounded-xl bg-slate-50 px-3 py-2">
                        <p class="text-[10px] font-black uppercase text-slate-500">{{ msg.sender }} · {{ msg.sender_role }} · {{ msg.at }}</p>
                        <p class="mt-1 font-semibold text-slate-800" :class="msg.is_redacted ? 'italic text-slate-500' : ''">{{ msg.body }}</p>
                    </li>
                </ul>
            </div>

            <div v-if="isSuperAdmin" class="rounded-2xl border border-primary-200 bg-primary-50/40 p-4">
                <p class="text-[10px] font-black uppercase text-primary-800">Super admin — delivery & escrow</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="rounded-xl bg-emerald-700 px-3 py-1.5 text-[10px] font-black uppercase text-white" :disabled="actionBusy" @click="showApproveForm = !showApproveForm">Force approve</button>
                    <button type="button" class="rounded-xl bg-amber-600 px-3 py-1.5 text-[10px] font-black uppercase text-white" :disabled="actionBusy" @click="showRejectForm = !showRejectForm">Force reject</button>
                    <button type="button" class="rounded-xl bg-primary-700 px-3 py-1.5 text-[10px] font-black uppercase text-white" :disabled="actionBusy" @click="showReleaseForm = !showReleaseForm">Release payment</button>
                    <button type="button" class="rounded-xl border border-primary-400 px-3 py-1.5 text-[10px] font-black uppercase text-primary-900" :disabled="actionBusy" @click="showPartialForm = !showPartialForm">Partial release</button>
                    <button type="button" class="rounded-xl border border-amber-400 px-3 py-1.5 text-[10px] font-black uppercase text-amber-900" :disabled="actionBusy" @click="showHoldForm = !showHoldForm">Hold escrow</button>
                    <a v-if="detail.links?.escrow_ledger" :href="detail.links.escrow_ledger" class="rounded-xl border px-3 py-1.5 text-[10px] font-black uppercase text-primary-700">Financial control</a>
                </div>
                <div v-if="showApproveForm" class="mt-3">
                    <textarea v-model="approveForm.reason" rows="2" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Reason for force approval…" />
                    <button type="button" class="mt-2 rounded-xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="actionBusy" @click="submitApprove">Confirm approve</button>
                </div>
                <div v-if="showRejectForm" class="mt-3">
                    <textarea v-model="rejectForm.reason" rows="2" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Revision note for freelancer…" />
                    <button type="button" class="mt-2 rounded-xl bg-amber-600 px-4 py-2 text-xs font-black uppercase text-white" :disabled="actionBusy" @click="submitReject">Confirm reject</button>
                </div>
                <div v-if="showReleaseForm" class="mt-3">
                    <textarea v-model="releaseForm.reason" rows="2" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Reason for release…" />
                    <button type="button" class="mt-2 rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="actionBusy" @click="submitRelease">Confirm release</button>
                </div>
                <div v-if="showPartialForm" class="mt-3 space-y-2">
                    <input v-model.number="partialForm.amount_minor" type="number" min="100" step="100" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Amount in kobo (minor units)" />
                    <textarea v-model="partialForm.reason" rows="2" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Reason for partial release…" />
                    <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="actionBusy" @click="submitPartial">Confirm partial release</button>
                </div>
                <div v-if="showHoldForm" class="mt-3">
                    <textarea v-model="holdForm.reason" rows="2" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Reason for escrow hold…" />
                    <button type="button" class="mt-2 rounded-xl bg-amber-600 px-4 py-2 text-xs font-black uppercase text-white" :disabled="actionBusy" @click="submitHold">Confirm hold</button>
                </div>
            </div>

            <div class="rounded-2xl border p-4">
                <p class="text-[10px] font-black uppercase text-slate-500">Staff review (optional)</p>
                <div class="mt-3 flex gap-2">
                    <select v-model.number="qualityForm.rating" class="rounded-xl border px-3 py-2 text-sm font-bold">
                        <option v-for="n in 5" :key="n" :value="n">{{ n }} ★</option>
                    </select>
                </div>
                <textarea v-model="qualityForm.notes" rows="3" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm" placeholder="Quality notes for super admin…" />
                <button type="button" class="mt-2 rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white disabled:opacity-50" :disabled="actionBusy" @click="saveQualityReview">
                    Save review
                </button>
            </div>

            <div class="rounded-2xl border p-4">
                <p class="text-[10px] font-black uppercase text-slate-500">Internal note</p>
                <textarea v-model="noteForm.body" rows="3" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm" placeholder="Note visible to staff and super admin…" />
                <button type="button" class="mt-2 rounded-xl border px-4 py-2 text-xs font-black uppercase" :disabled="actionBusy" @click="saveNote">Add note</button>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white" :disabled="actionBusy" @click="assignToMe">Assign to me</button>
                <button type="button" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-black uppercase text-amber-900" :disabled="actionBusy" @click="showFlagForm = !showFlagForm">Flag for review</button>
                <a v-if="detail.links?.full_contract" :href="detail.links.full_contract" class="rounded-xl border px-4 py-2 text-xs font-black uppercase text-primary-700">Full contract</a>
                <button v-if="isSuperAdmin" type="button" class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-black uppercase text-rose-800" :disabled="actionBusy" @click="showTerminateForm = !showTerminateForm">Terminate</button>
            </div>

            <div v-if="showFlagForm" class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                <textarea v-model="flagForm.reason" rows="3" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Why should super admin review this contract?" />
                <button type="button" class="mt-2 rounded-xl bg-amber-600 px-4 py-2 text-xs font-black uppercase text-white" :disabled="actionBusy" @click="submitFlag">Submit flag</button>
            </div>

            <div v-if="showTerminateForm && isSuperAdmin" class="rounded-2xl border border-rose-200 bg-rose-50/50 p-4">
                <textarea v-model="terminateForm.reason" rows="3" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Reason for termination (audit trail)…" />
                <button type="button" class="mt-2 rounded-xl bg-rose-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="actionBusy" @click="submitTerminate">Confirm terminate</button>
            </div>

            <div class="rounded-2xl border p-4">
                <p class="text-[10px] font-black uppercase text-slate-500">Audit log</p>
                <ul class="mt-2 max-h-48 space-y-2 overflow-y-auto text-xs">
                    <li v-for="(entry, i) in detail.audit_log" :key="i" class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                        <span class="font-semibold text-slate-700">{{ entry.action }}</span>
                        <span class="shrink-0 text-slate-500">{{ entry.at }} · {{ entry.by }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AdminSlideOver>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import axios from 'axios';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    referenceCode: { type: String, default: null },
    routePrefix: { type: String, required: true },
    isSuperAdmin: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'action-done']);

const loading = ref(false);
const detail = ref(null);
const actionBusy = ref(false);
const showFlagForm = ref(false);
const showTerminateForm = ref(false);
const showApproveForm = ref(false);
const showRejectForm = ref(false);
const showReleaseForm = ref(false);
const showPartialForm = ref(false);
const showHoldForm = ref(false);
const noteForm = reactive({ body: '' });
const flagForm = reactive({ reason: '' });
const terminateForm = reactive({ reason: '' });
const approveForm = reactive({ reason: '' });
const rejectForm = reactive({ reason: '' });
const releaseForm = reactive({ reason: '' });
const partialForm = reactive({ reason: '', amount_minor: null });
const holdForm = reactive({ reason: '' });
const qualityForm = reactive({ rating: 5, notes: '' });

function routeName(name) {
    return `${props.routePrefix}.${name}`;
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

async function loadDetail() {
    if (!props.referenceCode) {
        return;
    }
    loading.value = true;
    try {
        const { data } = await axios.get(route(routeName('contract-management.api.detail'), props.referenceCode));
        detail.value = data;
    } finally {
        loading.value = false;
    }
}

async function postAction(name, payload = {}) {
    actionBusy.value = true;
    try {
        await axios.post(route(routeName(`contract-management.contracts.${name}`), props.referenceCode), payload);
        emit('action-done');
        await loadDetail();
    } finally {
        actionBusy.value = false;
    }
}

function assignToMe() {
    postAction('assign');
}

function saveNote() {
    postAction('note', { body: noteForm.body });
    noteForm.body = '';
}

function submitFlag() {
    postAction('flag', { reason: flagForm.reason });
    flagForm.reason = '';
    showFlagForm.value = false;
}

function saveQualityReview() {
    postAction('quality-review', { rating: qualityForm.rating, notes: qualityForm.notes });
}

function submitTerminate() {
    postAction('terminate', { reason: terminateForm.reason });
    terminateForm.reason = '';
    showTerminateForm.value = false;
}

function submitApprove() {
    postAction('force-approve-delivery', { reason: approveForm.reason });
    approveForm.reason = '';
    showApproveForm.value = false;
}

function submitReject() {
    postAction('force-reject-delivery', { reason: rejectForm.reason });
    rejectForm.reason = '';
    showRejectForm.value = false;
}

function submitRelease() {
    postAction('release-payment', { reason: releaseForm.reason });
    releaseForm.reason = '';
    showReleaseForm.value = false;
}

function submitPartial() {
    postAction('partial-release', { reason: partialForm.reason, amount_minor: partialForm.amount_minor });
    partialForm.reason = '';
    partialForm.amount_minor = null;
    showPartialForm.value = false;
}

function submitHold() {
    postAction('hold-escrow', { reason: holdForm.reason });
    holdForm.reason = '';
    showHoldForm.value = false;
}

async function ackPatrolFlag(flagId) {
    actionBusy.value = true;
    try {
        await axios.post(route(routeName('contract-management.api.patrol-flags.acknowledge'), flagId));
        emit('action-done');
        await loadDetail();
    } finally {
        actionBusy.value = false;
    }
}

async function dismissPatrolFlag(flagId) {
    const reason = window.prompt('Reason for closing this patrol alert (optional):') ?? '';
    actionBusy.value = true;
    try {
        await axios.post(route(routeName('contract-management.api.patrol-flags.dismiss'), flagId), {
            reason: reason.trim() || 'Dismissed from contract detail panel',
        });
        emit('action-done');
        await loadDetail();
    } finally {
        actionBusy.value = false;
    }
}

watch(
    () => [props.open, props.referenceCode],
    ([open]) => {
        if (open) {
            loadDetail();
        }
    },
);
</script>
