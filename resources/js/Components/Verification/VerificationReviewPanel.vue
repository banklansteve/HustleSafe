<template>
    <div class="space-y-4">
        <div
            v-if="decisionBanner"
            class="rounded-2xl border px-4 py-3 text-sm font-bold"
            :class="decisionBanner.tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : decisionBanner.tone === 'error' ? 'border-rose-200 bg-rose-50 text-rose-950' : 'border-sky-200 bg-sky-50 text-sky-950'"
            role="status"
        >
            {{ decisionBanner.message }}
        </div>

        <section v-if="presentation?.requires_super_admin_review && !presentation?.staff_can_decide" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-800">Super Admin review</p>
            <p class="mt-2 text-xs leading-relaxed">This is a final-tier verification for this account type. It is routed to Super Admin only — you can view the submission but cannot approve or reject it here.</p>
        </section>

        <section v-else-if="presentation?.is_escalated" class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-semibold text-violet-950">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-violet-800">{{ presentation.escalation?.label || 'Escalated to Super Admin' }}</p>
            <p v-if="presentation.escalation?.reason" class="mt-2 text-xs leading-relaxed">{{ presentation.escalation.reason }}</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Submission</p>
                    <h3 class="mt-1 font-display text-lg font-black text-slate-950">{{ presentation?.category_label }}</h3>
                    <p class="mt-1 text-xs font-semibold text-slate-600">
                        {{ presentation?.verification_type_label }}
                        <span v-if="presentation?.submitted_at_label"> · Submitted {{ presentation.submitted_at_label }}</span>
                    </p>
                    <p v-if="presentation?.user" class="mt-2 text-sm font-bold text-slate-800">
                        {{ presentation.user.name }}
                        <span class="font-semibold text-slate-500"> · {{ presentation.user.email }}</span>
                    </p>
                </div>
                <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusClass(presentation?.status)">
                    {{ presentation?.status_label }}
                </span>
            </div>

            <dl v-if="presentation?.fields?.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                <div v-for="field in presentation.fields" :key="field.key" class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ field.label }}</dt>
                    <dd class="mt-1 break-all text-sm font-bold text-slate-900">{{ field.value }}</dd>
                </div>
            </dl>

            <p v-if="presentation?.queue_reason_label" class="mt-3 text-xs font-semibold text-amber-800">
                Queue reason: {{ presentation.queue_reason_label }}
            </p>
            <p v-if="presentation?.rejection_reason && !canDecide" class="mt-3 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-950">
                {{ presentation.rejection_reason }}
            </p>
        </section>

        <section v-if="presentation?.documents?.length" class="space-y-3">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Uploaded documents</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <article
                    v-for="doc in presentation.documents"
                    :key="doc.path"
                    class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-primary-200 hover:shadow-md"
                >
                    <button
                        type="button"
                        class="block aspect-square w-full cursor-zoom-in bg-slate-100"
                        :aria-label="`Preview ${doc.label}`"
                        @click="openPreview(doc)"
                    >
                        <img
                            v-if="doc.is_image"
                            :src="doc.url"
                            :alt="doc.label"
                            class="h-full w-full object-cover transition group-hover:scale-[1.02]"
                            loading="lazy"
                        />
                        <div v-else class="flex h-full flex-col items-center justify-center gap-2 px-3 text-center">
                            <span class="text-2xl">{{ doc.is_pdf ? '📄' : '📎' }}</span>
                            <span class="text-[10px] font-bold leading-tight text-slate-600">{{ doc.is_pdf ? 'PDF — tap to preview' : 'File — tap to open' }}</span>
                        </div>
                    </button>
                    <div class="space-y-1 border-t border-slate-100 p-2.5">
                        <p class="line-clamp-2 text-xs font-black text-slate-900">{{ doc.label }}</p>
                        <p class="truncate text-[10px] font-semibold text-slate-500">{{ doc.original_name }}</p>
                    </div>
                </article>
            </div>
        </section>
        <p v-else class="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm font-semibold text-slate-500">No files were attached to this submission.</p>

        <section v-if="presentation?.provider_summary?.length" class="rounded-2xl border border-sky-100 bg-sky-50/60 p-4">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-800">Provider check</p>
            <dl class="mt-3 space-y-2">
                <div v-for="row in presentation.provider_summary" :key="row.label" class="flex justify-between gap-3 text-xs">
                    <dt class="font-bold text-slate-600">{{ row.label }}</dt>
                    <dd class="font-semibold text-slate-900">{{ row.value }}</dd>
                </div>
            </dl>
        </section>

        <section v-if="canDecide && isApproved" class="space-y-4 rounded-2xl border border-violet-200 bg-violet-50/60 p-4">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-violet-800">Re-review approved submission</p>
            <p class="text-xs font-semibold text-violet-950">
                This verification is already approved. You can revoke approval or ask the user to submit corrections again.
            </p>

            <div class="grid gap-2 sm:grid-cols-2">
                <button
                    v-for="option in reReviewOptions"
                    :key="option.value"
                    type="button"
                    class="rounded-2xl border px-3 py-3 text-left text-xs font-black transition"
                    :class="decisionAction === option.value ? option.activeClass : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                    @click="decisionAction = option.value"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="text-xs font-black uppercase tracking-wide text-slate-500">Reason for user</label>
                    <select
                        v-model="reasonCode"
                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        required
                    >
                        <option value="">Select a reason…</option>
                        <option v-for="reason in decisionReasons" :key="reason.value" :value="reason.value">
                            {{ reason.label }}
                        </option>
                    </select>
                    <p v-if="selectedReasonHint" class="mt-1 text-xs font-semibold text-slate-600">{{ selectedReasonHint }}</p>
                </div>
                <UiTextarea
                    v-model="reasonNote"
                    :label="reasonCode === 'other' ? 'Explain (required)' : 'Additional note (optional)'"
                    :placeholder="reasonCode === 'other' ? 'Tell the user exactly what to fix…' : 'Optional detail for the user notification…'"
                    :required="reasonCode === 'other'"
                    :min-rows="2"
                    :max-rows="6"
                    :error="decisionError"
                />
            </div>

            <button
                type="button"
                class="w-full rounded-2xl px-4 py-3 text-sm font-black uppercase tracking-wide text-white shadow-sm disabled:opacity-50"
                :class="submitClass"
                :disabled="deciding || !canSubmitDecision"
                @click="submitDecision"
            >
                {{ deciding ? 'Saving decision…' : submitLabel }}
            </button>
        </section>

        <section v-if="canDecide && isReviewable" class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-600">Reviewer decision</p>

            <div class="grid gap-2 sm:grid-cols-3">
                <button
                    v-for="option in decisionOptions"
                    :key="option.value"
                    type="button"
                    class="rounded-2xl border px-3 py-3 text-left text-xs font-black transition"
                    :class="decisionAction === option.value ? option.activeClass : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                    @click="decisionAction = option.value"
                >
                    {{ option.label }}
                </button>
            </div>

            <div v-if="decisionAction !== 'approve'" class="space-y-3">
                <div>
                    <label class="text-xs font-black uppercase tracking-wide text-slate-500">Reason for user</label>
                    <select
                        v-model="reasonCode"
                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        required
                    >
                        <option value="">Select a reason…</option>
                        <option v-for="reason in decisionReasons" :key="reason.value" :value="reason.value">
                            {{ reason.label }}
                        </option>
                    </select>
                    <p v-if="selectedReasonHint" class="mt-1 text-xs font-semibold text-slate-600">{{ selectedReasonHint }}</p>
                </div>
                <UiTextarea
                    v-model="reasonNote"
                    :label="reasonCode === 'other' ? 'Explain (required)' : 'Additional note (optional)'"
                    :placeholder="reasonCode === 'other' ? 'Tell the user exactly what to fix…' : 'Optional detail for the user notification…'"
                    :required="reasonCode === 'other'"
                    :min-rows="2"
                    :max-rows="6"
                    :error="decisionError"
                />
            </div>

            <UiTextarea
                v-else
                v-model="reasonNote"
                label="Optional note to user"
                placeholder="Optional message included in the user notification."
                :min-rows="2"
                :max-rows="4"
            />

            <button
                type="button"
                class="w-full rounded-2xl px-4 py-3 text-sm font-black uppercase tracking-wide text-white shadow-sm disabled:opacity-50"
                :class="submitClass"
                :disabled="deciding || !canSubmitDecision"
                @click="submitDecision"
            >
                {{ deciding ? 'Saving decision…' : submitLabel }}
            </button>
        </section>

        <Teleport to="body">
            <div
                v-if="previewDoc"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="previewDoc = null"
            >
                <div class="flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex shrink-0 items-center justify-between border-b px-4 py-3">
                        <div class="min-w-0 pr-4">
                            <p class="truncate text-sm font-black text-slate-900">{{ previewDoc.label }}</p>
                            <p class="truncate text-xs font-semibold text-slate-500">{{ previewDoc.original_name }}</p>
                        </div>
                        <button type="button" class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100" @click="previewDoc = null">
                            Close
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto bg-slate-950/5 p-2 sm:p-4">
                        <img v-if="previewDoc.is_image" :src="previewDoc.url" :alt="previewDoc.label" class="mx-auto max-h-[80vh] w-auto max-w-full object-contain" />
                        <iframe v-else :src="previewDoc.url" class="mx-auto h-[80vh] w-full max-w-4xl rounded-lg bg-white" title="Document preview" />
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import UiTextarea from '@/Components/Ui/UiTextarea.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    presentation: { type: Object, default: null },
    canDecide: { type: Boolean, default: false },
    decideUrl: { type: String, default: '' },
    decisionReasons: { type: Array, default: () => [] },
});

const emit = defineEmits(['decided']);

const previewDoc = ref(null);
const decisionAction = ref('approve');
const reasonCode = ref('');
const reasonNote = ref('');
const decisionError = ref('');
const deciding = ref(false);
const decisionBanner = ref(null);

const reviewableStatuses = ['pending', 'in_review', 'flagged', 'unverified', 'rejected'];
const approvedStatuses = ['approved', 'verified'];

const isReviewable = computed(() => reviewableStatuses.includes(props.presentation?.status));
const isApproved = computed(() => approvedStatuses.includes(props.presentation?.status));

const decisionOptions = [
    { value: 'approve', label: 'Approve', activeClass: 'border-emerald-300 bg-emerald-50 text-emerald-900 ring-2 ring-emerald-100' },
    { value: 'request_corrections', label: 'Request corrections', activeClass: 'border-amber-300 bg-amber-50 text-amber-900 ring-2 ring-amber-100' },
    { value: 'reject', label: 'Reject', activeClass: 'border-rose-300 bg-rose-50 text-rose-900 ring-2 ring-rose-100' },
];

const reReviewOptions = [
    { value: 'request_corrections', label: 'Request corrections again', activeClass: 'border-amber-300 bg-amber-50 text-amber-900 ring-2 ring-amber-100' },
    { value: 'reject', label: 'Revoke approval', activeClass: 'border-rose-300 bg-rose-50 text-rose-900 ring-2 ring-rose-100' },
];

const selectedReasonHint = computed(() => props.decisionReasons.find((r) => r.value === reasonCode.value)?.hint || '');

const submitLabel = computed(() => {
    if (decisionAction.value === 'approve') {
        return 'Approve verification';
    }
    if (decisionAction.value === 'request_corrections') {
        return isApproved.value ? 'Request corrections again' : 'Request corrections';
    }
    return isApproved.value ? 'Revoke approval' : 'Reject verification';
});

const submitClass = computed(() => {
    if (decisionAction.value === 'reject') {
        return 'bg-rose-600 hover:bg-rose-700';
    }
    if (decisionAction.value === 'request_corrections') {
        return 'bg-amber-600 hover:bg-amber-700';
    }
    return 'bg-emerald-600 hover:bg-emerald-700';
});

const canSubmitDecision = computed(() => {
    if (decisionAction.value === 'approve') {
        return true;
    }
    if (!reasonCode.value) {
        return false;
    }
    if (reasonCode.value === 'other') {
        return reasonNote.value.trim().length >= 8;
    }

    return true;
});

watch(
    () => props.presentation?.id,
    () => {
        decisionBanner.value = null;
        reasonNote.value = '';
        reasonCode.value = '';
        decisionError.value = '';
        decisionAction.value = approvedStatuses.includes(props.presentation?.status) ? 'request_corrections' : 'approve';
    },
);

watch(decisionAction, (action) => {
    if (action === 'approve') {
        reasonCode.value = '';
    }
});

function statusClass(status) {
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
    return 'bg-slate-100 text-slate-700';
}

function openPreview(doc) {
    previewDoc.value = doc;
}

async function submitDecision() {
    if (!props.decideUrl || !canSubmitDecision.value) {
        return;
    }
    deciding.value = true;
    decisionError.value = '';
    try {
        const payload = {
            action: decisionAction.value,
            reason_code: decisionAction.value === 'approve' ? null : reasonCode.value,
            reason_note: reasonNote.value.trim() || null,
        };
        const { data } = await window.axios.post(props.decideUrl, payload);
        decisionBanner.value = { tone: 'success', message: data.message || 'Decision saved.' };
        emit('decided', data);
    } catch (error) {
        decisionError.value = error?.response?.data?.message
            || Object.values(error?.response?.data?.errors || {})?.flat?.()?.[0]
            || 'Could not save this decision.';
        decisionBanner.value = { tone: 'error', message: decisionError.value };
    } finally {
        deciding.value = false;
    }
}
</script>
