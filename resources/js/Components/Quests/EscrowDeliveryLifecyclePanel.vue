<template>
    <section
        v-if="lifecycle.show_panel"
        class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">Job progress</p>
                <h2 class="font-display mt-1 text-lg font-black text-slate-900">{{ lifecycle.stage_label }}</h2>
            </div>
            <span
                class="inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide ring-1"
                :class="stageBadgeClass"
            >
                {{ stageBadgeText }}
            </span>
        </div>

        <p v-if="lifecycle.timeline_notice && isWorkStage" class="mt-3 rounded-xl border border-amber-100 bg-amber-50/80 px-3 py-2.5 text-xs font-semibold leading-relaxed text-amber-950">
            {{ lifecycle.timeline_notice }}
        </p>

        <div v-if="recurring.is_recurring" class="mt-4 space-y-3 rounded-xl border border-violet-100 bg-violet-50/60 p-4">
            <p class="text-[10px] font-black uppercase tracking-wide text-violet-800">Ongoing contract · {{ recurring.frequency_label }} pay</p>
            <p class="text-xs font-semibold leading-relaxed text-violet-950">{{ recurring.plain_english }}</p>
            <div v-if="recurring.current_installment" class="grid gap-2 sm:grid-cols-3">
                <div class="rounded-lg border border-violet-200/80 bg-white/80 px-3 py-2">
                    <p class="text-[10px] font-black uppercase text-violet-700">Current period</p>
                    <p class="mt-0.5 text-sm font-black text-violet-950">#{{ recurring.current_installment.number }}</p>
                </div>
                <div class="rounded-lg border border-violet-200/80 bg-white/80 px-3 py-2">
                    <p class="text-[10px] font-black uppercase text-violet-700">Period ends</p>
                    <p class="mt-0.5 text-sm font-black text-violet-950">{{ recurring.current_installment.period_end_label }}</p>
                </div>
                <div class="rounded-lg border border-violet-200/80 bg-white/80 px-3 py-2">
                    <p class="text-[10px] font-black uppercase text-violet-700">This payout</p>
                    <p class="mt-0.5 text-sm font-black text-violet-950">{{ recurring.current_installment.amount_label }}</p>
                </div>
            </div>
        </div>

        <ContractRenewalPanel :renewal="recurring.contract_renewal ?? { show_panel: false }" />

        <!-- Stage 1: Work in progress -->
        <template v-if="lifecycle.stage === 'work_in_progress'">
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-primary-100 bg-primary-50/60 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wide text-primary-800">Finish by</p>
                    <p class="mt-1 font-display text-lg font-black text-primary-950">
                        {{ expectedDeliveryDisplay }}
                    </p>
                    <p v-if="lifecycle.expected_delivery_label" class="mt-1 text-xs font-semibold text-primary-800/80">
                        {{ lifecycle.expected_delivery_label }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-600">Time left</p>
                    <p class="mt-1 font-display text-lg font-black text-slate-900">{{ workCountdownLabel }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Work is ongoing — payment stays locked</p>
                </div>
            </div>
            <p class="mt-4 text-xs font-semibold text-slate-600">
                Message your {{ lifecycle.is_client ? 'worker' : 'client' }} for updates. Money only moves after the work is submitted and approved.
            </p>
            <p v-if="lifecycle.is_freelancer && contractUrl" class="mt-3">
                <Link
                    :href="contractUrl + '#delivery-extension'"
                    class="inline-flex text-xs font-black text-primary-800 underline underline-offset-2"
                >
                    Need more time or can finish sooner? Change the finish date →
                </Link>
            </p>
        </template>

        <!-- Revision requested -->
        <template v-else-if="lifecycle.stage === 'revision_requested'">
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-wide text-amber-900">What the client wants changed</p>
                <p class="mt-2 whitespace-pre-wrap text-sm font-medium text-amber-950">{{ lifecycle.revision_note }}</p>
            </div>
            <form v-if="lifecycle.show_freelancer_submit" class="mt-4 space-y-3" @submit.prevent="submitDeliverable">
                <div>
                    <label class="text-xs font-bold text-slate-700">What you fixed or updated</label>
                    <textarea
                        v-model="submitForm.summary"
                        rows="4"
                        required
                        minlength="20"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-900 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100"
                        placeholder="Explain what you changed and where to find it…"
                    />
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-700">Link (optional)</label>
                    <input
                        v-model="submitForm.delivery_url"
                        type="url"
                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-900 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100"
                        placeholder="https://…"
                    />
                </div>
                <label class="flex items-start gap-2 text-xs font-semibold text-slate-700">
                    <input v-model="submitForm.confirm" type="checkbox" class="mt-0.5 rounded border-slate-300 text-primary-600" />
                    <span>I have addressed the client's feedback.</span>
                </label>
                <button
                    type="submit"
                    class="inline-flex rounded-full bg-primary-700 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-primary-800 disabled:opacity-50"
                    :disabled="submitForm.processing"
                >
                    Send updated work
                </button>
            </form>
        </template>

        <!-- Stage 2: Awaiting review -->
        <template v-else-if="lifecycle.stage === 'awaiting_review'">
            <div v-if="lifecycle.latest_submission" class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Submitted work</p>
                <p class="mt-2 whitespace-pre-wrap text-sm font-medium text-slate-800">{{ lifecycle.latest_submission.summary }}</p>
                <a
                    v-if="lifecycle.latest_submission.delivery_url"
                    :href="lifecycle.latest_submission.delivery_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-2 inline-flex text-xs font-black text-primary-700 underline underline-offset-2"
                >
                    Open link →
                </a>
                <p class="mt-2 text-xs font-semibold text-slate-500">
                    Sent {{ lifecycle.latest_submission.submitted_label }}
                </p>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-violet-100 bg-violet-50/60 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wide text-violet-800">Check it by</p>
                    <p class="mt-1 font-display text-base font-black text-violet-950">{{ lifecycle.review_deadline_label || '—' }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-600">Auto-pay in</p>
                    <p class="mt-1 font-display text-base font-black text-slate-900">{{ reviewCountdownLabel }}</p>
                    <p class="mt-1 text-[11px] font-semibold text-slate-600">{{ lifecycle.auto_release_plain_english }}</p>
                </div>
            </div>
            <div v-if="lifecycle.show_client_actions" class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex rounded-full bg-emerald-700 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-800"
                    @click="openApproveModal"
                >
                    Work looks good — approve
                </button>
                <button
                    type="button"
                    class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-amber-950 hover:bg-amber-100"
                    @click="showRevisionModal = true"
                >
                    Ask for small fixes
                </button>
                <Link
                    v-if="disputeUrl"
                    :href="disputeUrl"
                    class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-rose-900 hover:bg-rose-100"
                >
                    Raise a complaint
                </Link>
            </div>
        </template>

        <!-- Stage 3: Approved releasing -->
        <template v-else-if="lifecycle.stage === 'approved_releasing'">
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="font-display text-base font-black text-emerald-900">Approved — paying the worker</p>
                <p class="mt-2 text-sm font-semibold text-emerald-950">
                    {{ recurring.is_recurring ? lifecycle.release_amount_formatted : lifecycle.escrow_amount_formatted }} is being sent to the worker.
                </p>
                <p v-if="lifecycle.release?.blocked_release_reason" class="mt-2 text-xs font-bold text-amber-900">
                    {{ lifecycle.release.blocked_release_reason }}
                    <span v-if="releaseCooldownLabel" class="mt-1 block">Payment unlocks in {{ releaseCooldownLabel }}</span>
                </p>
                <button
                    v-if="lifecycle.release?.can_release_funds"
                    type="button"
                    class="mt-3 inline-flex rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase text-white"
                    @click="$emit('release-funds')"
                >
                    Pay now
                </button>
            </div>
        </template>

        <!-- Stage 4: Completed -->
        <template v-else-if="lifecycle.stage === 'completed_paid'">
            <div class="mt-4 rounded-xl border border-teal-200 bg-teal-50 p-4">
                <p class="font-display text-base font-black text-teal-900">Done and paid</p>
                <p class="mt-2 text-sm font-semibold text-teal-950">
                    {{ lifecycle.escrow_amount_formatted }} has been paid out. Thank you for using HustleSafe.
                </p>
            </div>
        </template>

        <!-- Freelancer initial submit -->
        <form v-if="lifecycle.stage === 'work_in_progress' && lifecycle.show_freelancer_submit" class="mt-5 space-y-3 border-t border-slate-100 pt-5" @submit.prevent="submitDeliverable">
            <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Job is done — send for review</p>
            <div>
                <label class="text-xs font-bold text-slate-700">Describe what you delivered</label>
                <textarea
                    v-model="submitForm.summary"
                    rows="4"
                    required
                    minlength="20"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-900 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100"
                    placeholder="What you finished, how to check it, and any handover notes…"
                />
            </div>
            <div>
                <label class="text-xs font-bold text-slate-700">Link to photos or files (optional)</label>
                <input
                    v-model="submitForm.delivery_url"
                    type="url"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-900 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100"
                    placeholder="https://…"
                />
            </div>
            <label class="flex items-start gap-2 text-xs font-semibold text-slate-700">
                <input v-model="submitForm.confirm" type="checkbox" class="mt-0.5 rounded border-slate-300 text-primary-600" />
                <span>The work is ready for the client to check.</span>
            </label>
            <button
                type="submit"
                class="inline-flex rounded-full bg-primary-700 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-primary-800 disabled:opacity-50"
                :disabled="submitForm.processing"
            >
                Send for review
            </button>
        </form>

        <details v-if="lifecycle.delivery_adjustment_rules" class="mt-5 rounded-xl border border-slate-100 bg-slate-50/50 p-3">
            <summary class="cursor-pointer text-xs font-black uppercase tracking-wide text-slate-600">Changing the finish date</summary>
            <ul class="mt-2 space-y-1.5 text-xs font-semibold text-slate-600">
                <li>{{ lifecycle.delivery_adjustment_rules.freelancer_adjustments?.label }}</li>
                <li>{{ lifecycle.delivery_adjustment_rules.client_amendments?.label }}</li>
            </ul>
        </details>

        <!-- Revision modal -->
        <div v-if="showRevisionModal" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" @click.self="showRevisionModal = false">
            <form class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" @submit.prevent="submitRevision">
                <h3 class="font-display text-lg font-black text-slate-900">Ask for small fixes</h3>
                <p class="mt-1 text-xs font-semibold text-slate-600">Tell the worker exactly what needs to change. This is not for adding new work.</p>
                <textarea
                    v-model="revisionForm.note"
                    rows="4"
                    required
                    minlength="20"
                    class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                    placeholder="Be clear and specific…"
                />
                <label class="mt-3 flex items-start gap-2 text-xs font-semibold text-slate-700">
                    <input v-model="revisionForm.confirm" type="checkbox" class="mt-0.5" />
                    <span>I understand auto-payment pauses until they resubmit.</span>
                </label>
                <div class="mt-4 flex gap-2">
                    <button type="button" class="flex-1 rounded-full border border-slate-200 py-2 text-xs font-bold" @click="showRevisionModal = false">Cancel</button>
                    <button type="submit" class="flex-1 rounded-full bg-amber-600 py-2 text-xs font-black text-white" :disabled="revisionForm.processing">Send</button>
                </div>
            </form>
        </div>
    </section>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ContractRenewalPanel from '@/Components/Quests/ContractRenewalPanel.vue';

const props = defineProps({
    lifecycle: { type: Object, required: true },
    quest: { type: Object, required: true },
    contractUrl: { type: String, default: null },
    disputeUrl: { type: String, default: null },
});

const emit = defineEmits(['release-funds', 'approve']);

const showRevisionModal = ref(false);

const submitForm = useForm({
    summary: '',
    delivery_url: '',
    confirm: false,
});

const revisionForm = useForm({
    note: '',
    confirm: false,
});

const workSeconds = ref(props.lifecycle.seconds_until_work_deadline ?? 0);
const reviewSeconds = ref(props.lifecycle.seconds_until_review_deadline ?? 0);
const releaseSeconds = ref(props.lifecycle.release?.seconds_until_release ?? 0);
let timer = null;

const isWorkStage = computed(() => props.lifecycle.stage === 'work_in_progress');

const recurring = computed(() => props.lifecycle.recurring_engagement ?? { is_recurring: false });

const stageBadgeClass = computed(() => {
    const map = {
        work_in_progress: 'bg-sky-50 text-sky-900 ring-sky-200',
        awaiting_review: 'bg-violet-50 text-violet-900 ring-violet-200',
        revision_requested: 'bg-amber-50 text-amber-900 ring-amber-200',
        approved_releasing: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        completed_paid: 'bg-teal-50 text-teal-900 ring-teal-200',
    };
    return map[props.lifecycle.stage] || 'bg-slate-50 text-slate-800 ring-slate-200';
});

const stageBadgeText = computed(() => {
    const map = {
        work_in_progress: 'In progress',
        awaiting_review: 'Check work',
        revision_requested: 'Fixes needed',
        approved_releasing: 'Paying',
        completed_paid: 'Paid',
    };
    return map[props.lifecycle.stage] || props.lifecycle.stage;
});

const expectedDeliveryDisplay = computed(() => {
    const raw = props.lifecycle.expected_delivery_at;
    if (!raw) return 'Not set';
    try {
        return new Date(raw).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Lagos' });
    } catch {
        return raw;
    }
});

function formatCountdown(total) {
    const s = Math.max(0, total);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m ${s % 60}s`;
}

const workCountdownLabel = computed(() => formatCountdown(workSeconds.value));
const reviewCountdownLabel = computed(() => formatCountdown(reviewSeconds.value));
const releaseCooldownLabel = computed(() => formatCountdown(releaseSeconds.value));

watch(
    () => props.lifecycle,
    (v) => {
        workSeconds.value = v?.seconds_until_work_deadline ?? 0;
        reviewSeconds.value = v?.seconds_until_review_deadline ?? 0;
        releaseSeconds.value = v?.release?.seconds_until_release ?? 0;
    },
    { deep: true },
);

onMounted(() => {
    timer = window.setInterval(() => {
        if (workSeconds.value > 0) workSeconds.value -= 1;
        if (reviewSeconds.value > 0) reviewSeconds.value -= 1;
        if (releaseSeconds.value > 0) releaseSeconds.value -= 1;
    }, 1000);
});

onBeforeUnmount(() => {
    if (timer) window.clearInterval(timer);
});

function submitDeliverable() {
    submitForm.post(route('quests.delivery-submissions.store', props.quest.slug || props.quest.uuid), {
        preserveScroll: true,
        onSuccess: () => submitForm.reset(),
    });
}

function submitRevision() {
    revisionForm.post(route('quests.delivery.request-revision', props.quest.slug || props.quest.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            revisionForm.reset();
            showRevisionModal.value = false;
        },
    });
}

function openApproveModal() {
    emit('approve');
}
</script>
