<template>
    <div v-if="detail" class="space-y-4">
        <!-- Super Admin guidance -->
        <section
            v-if="detail.staff_guidance"
            class="rounded-2xl border border-violet-300 bg-violet-50/80 p-4 shadow-sm ring-1 ring-violet-200"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-violet-900">Action required from you</p>
                    <p class="mt-1 text-sm font-black text-violet-950">{{ detail.staff_guidance.type_label }}</p>
                    <p v-if="detail.staff_guidance.requested_by" class="mt-1 text-xs font-semibold text-violet-800">
                        From {{ detail.staff_guidance.requested_by }}
                        <span v-if="detail.staff_guidance.requested_at"> · {{ formatWhen(detail.staff_guidance.requested_at) }}</span>
                    </p>
                </div>
            </div>
            <p v-if="detail.staff_guidance.note" class="mt-3 whitespace-pre-wrap rounded-xl border border-violet-200 bg-white/80 px-3 py-3 text-sm font-semibold text-slate-800">
                {{ detail.staff_guidance.note }}
            </p>
            <div v-if="detail.staff_guidance.can_respond" class="mt-4 space-y-3">
                <OperationsFormField
                    v-model="guidanceResponse"
                    label="Your response to Super Admin"
                    hint="Explain what you found, what changed in your assessment, or what you still need."
                    multiline
                    :rows="4"
                    placeholder="Address the Super Admin’s questions directly…"
                />
                <button
                    type="button"
                    class="rounded-xl bg-violet-700 px-4 py-2.5 text-xs font-black uppercase text-white disabled:opacity-50"
                    :disabled="busy.guidance || !guidanceResponse.trim()"
                    @click="submitGuidanceResponse"
                >
                    {{ busy.guidance ? 'Sending…' : 'Send response to Super Admin' }}
                </button>
            </div>
        </section>

        <!-- Status + acknowledge -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm ring-1 ring-slate-100">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Status</p>
                    <p class="mt-1 text-sm font-black text-slate-900">{{ detail.dispute.management_status_label }}</p>
                    <p v-if="detail.dispute.staff_acknowledged_at" class="mt-1 text-xs font-semibold text-emerald-700">
                        Acknowledged {{ formatWhen(detail.dispute.staff_acknowledged_at) }}
                    </p>
                    <p v-else class="mt-1 text-xs font-semibold text-amber-800">Not yet acknowledged</p>
                </div>
                <button
                    v-if="!detail.dispute.staff_acknowledged_at"
                    type="button"
                    class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white"
                    :disabled="busy.acknowledge"
                    @click="$emit('acknowledge')"
                >
                    I've acknowledged this
                </button>
            </div>
        </div>

        <section
            v-if="detail.self_resolution_activity?.events?.length || detail.dispute?.party_self_resolved"
            class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 ring-1 ring-emerald-100"
        >
            <p class="text-[10px] font-black uppercase tracking-wider text-emerald-900">Self-resolution activity</p>
            <p v-if="detail.self_resolution_activity?.outcome_label" class="mt-1 text-sm font-bold text-emerald-950">
                {{ detail.self_resolution_activity.outcome_label }}
            </p>
            <p v-if="detail.dispute?.needs_admin_acknowledgment" class="mt-2 text-xs font-semibold text-amber-900">
                Parties closed this case — Super Admin must acknowledge in the admin console.
            </p>
            <ul v-if="detail.self_resolution_activity?.events?.length" class="mt-3 max-h-40 space-y-2 overflow-y-auto text-xs font-semibold">
                <li
                    v-for="event in detail.self_resolution_activity.events"
                    :key="event.id"
                    class="rounded-xl border border-emerald-100 bg-white/80 px-3 py-2"
                >
                    <p class="font-black text-emerald-950">{{ formatWhen(event.created_at) }} · {{ event.actor }}</p>
                    <p class="mt-0.5 font-bold">{{ event.action_label }}</p>
                </li>
            </ul>
        </section>

        <section
            v-if="detail.dispute?.management_status === 'awaiting_mutual_approval'"
            class="rounded-2xl border border-emerald-300 bg-emerald-50/80 p-4 ring-1 ring-emerald-200"
        >
            <p class="text-[10px] font-black uppercase tracking-wider text-emerald-900">Mutual agreement pending</p>
            <p class="mt-1 text-sm font-semibold text-emerald-950">
                Both parties accepted a negotiated settlement. Verify amounts match escrow, terms are clear, and approve to release funds.
            </p>
            <button
                type="button"
                class="mt-3 rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black uppercase text-white disabled:opacity-50"
                :disabled="busy.approveMutual"
                @click="$emit('approve-mutual')"
            >
                Approve mutual agreement & execute
            </button>
        </section>

        <section
            v-if="detail.negotiation_history?.length"
            class="rounded-2xl border border-sky-200 bg-sky-50/60 p-4 ring-1 ring-sky-100"
        >
            <p class="text-[10px] font-black uppercase tracking-wider text-sky-900">Negotiation history</p>
            <ul class="mt-3 max-h-48 space-y-2 overflow-y-auto text-xs">
                <li
                    v-for="offer in detail.negotiation_history"
                    :key="offer.id"
                    class="rounded-xl border border-sky-100 bg-white/80 px-3 py-2"
                >
                    <p class="font-black text-sky-950">{{ offer.summary }} · Attempt {{ offer.attempt_number }}</p>
                    <p class="mt-0.5 font-semibold text-slate-700">{{ offer.offered_by }} ({{ offer.party_role }}) · {{ offer.status }}</p>
                </li>
            </ul>
            <p v-if="detail.negotiation_meta && !detail.negotiation_meta.binding_mediation_ack_client" class="mt-2 text-xs font-bold text-amber-800">
                Client has not acknowledged binding mediation yet.
            </p>
            <p v-if="detail.negotiation_meta && !detail.negotiation_meta.binding_mediation_ack_freelancer" class="mt-1 text-xs font-bold text-amber-800">
                Freelancer has not acknowledged binding mediation yet.
            </p>
        </section>

        <!-- Sticky quick actions -->
        <div class="sticky top-0 z-10 -mx-1 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-md backdrop-blur-sm ring-1 ring-slate-100">
            <p class="mb-2 px-1 text-[10px] font-black uppercase tracking-wider text-slate-500">Quick actions</p>
            <div class="flex flex-wrap gap-1.5">
                <button type="button" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[10px] font-black uppercase text-slate-800" @click="evidenceTab = 'files'">View evidence</button>
                <button type="button" class="rounded-lg bg-sky-100 px-2.5 py-1.5 text-[10px] font-black uppercase text-sky-900" @click="openContact('client')">Message client</button>
                <button type="button" class="rounded-lg bg-sky-100 px-2.5 py-1.5 text-[10px] font-black uppercase text-sky-900" @click="openContact('freelancer')">Message freelancer</button>
                <button type="button" class="rounded-lg bg-amber-100 px-2.5 py-1.5 text-[10px] font-black uppercase text-amber-900" @click="showNote = true">Add note</button>
                <button type="button" class="rounded-lg bg-violet-100 px-2.5 py-1.5 text-[10px] font-black uppercase text-violet-900" @click="showEvidence = true">Request info</button>
                <button
                    v-if="detail.permissions?.can_mark_ready"
                    type="button"
                    class="rounded-lg bg-emerald-700 px-2.5 py-1.5 text-[10px] font-black uppercase text-white"
                    :disabled="busy.ready"
                    @click="$emit('ready')"
                >
                    Mark done
                </button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,240px)_1fr]">
            <!-- Checklist -->
            <aside class="rounded-2xl border border-primary-100 bg-primary-50/40 p-4 lg:sticky lg:top-28 lg:self-start">
                <p class="text-[10px] font-black uppercase tracking-wider text-primary-900">Workflow checklist</p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-primary-100">
                    <div class="h-full rounded-full bg-primary-600 transition-all" :style="{ width: `${workflowProgress}%` }" />
                </div>
                <p class="mt-1 text-xs font-bold text-primary-900">{{ workflowProgress }}% complete</p>
                <ul class="mt-3 space-y-2">
                    <li v-for="step in workflowSteps" :key="step.key">
                        <label class="flex cursor-pointer items-start gap-2 text-xs font-semibold text-slate-800">
                            <input
                                type="checkbox"
                                class="mt-0.5 rounded border-slate-300 text-primary-600"
                                :checked="step.completed"
                                @change="toggleStep(step.key, $event.target.checked)"
                            />
                            <span :class="step.completed ? 'text-emerald-800' : ''">{{ step.label }}</span>
                        </label>
                    </li>
                </ul>
            </aside>

            <!-- Main content -->
            <div class="space-y-4">
                <OperationsContextStats heading="Parties" :stats="quickStats" :chips="quickChips" :links="contextLinks" />

                <section class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                    <p class="text-xs font-black uppercase text-slate-500">Dispute overview</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ detail.dispute.reason_label }} · {{ formatMinor(detail.dispute.disputed_amount_minor) }}</p>
                    <p class="mt-3 whitespace-pre-wrap text-sm font-medium text-slate-800">{{ detail.intake.description }}</p>
                </section>

                <!-- Evidence tabs -->
                <section class="rounded-2xl border border-slate-100 bg-white p-4">
                    <div class="flex flex-wrap gap-1 border-b border-slate-100 pb-2">
                        <button
                            v-for="tab in evidenceTabs"
                            :key="tab.key"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-[10px] font-black uppercase"
                            :class="evidenceTab === tab.key ? 'bg-primary-700 text-white' : 'bg-slate-100 text-slate-700'"
                            @click="evidenceTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div v-if="evidenceTab === 'files'" class="mt-3 space-y-2">
                        <div
                            v-for="(file, idx) in detail.intake.evidence_files || []"
                            :key="idx"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2"
                        >
                            <a :href="file.url" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-primary-800 underline">{{ file.original_name }}</a>
                            <button
                                type="button"
                                class="rounded-lg px-2 py-1 text-[10px] font-black uppercase"
                                :class="isReviewed(`file:${idx}`) ? 'bg-emerald-100 text-emerald-800' : 'bg-white text-slate-700 ring-1 ring-slate-200'"
                                @click="$emit('evidence-reviewed', { key: `file:${idx}`, note: null })"
                            >
                                {{ isReviewed(`file:${idx}`) ? 'Reviewed ✓' : 'Mark reviewed' }}
                            </button>
                        </div>
                        <p v-if="!(detail.intake.evidence_files || []).length" class="text-sm font-semibold text-slate-500">No files uploaded.</p>
                    </div>

                    <div v-else-if="evidenceTab === 'links'" class="mt-3 space-y-2">
                        <div
                            v-for="(link, idx) in detail.intake.external_links || []"
                            :key="idx"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2"
                        >
                            <a :href="link.url || link" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-primary-800 underline">{{ link.label || link.url || link }}</a>
                            <button type="button" class="rounded-lg bg-white px-2 py-1 text-[10px] font-black uppercase text-slate-700 ring-1 ring-slate-200" @click="$emit('evidence-reviewed', { key: `link:${idx}` })">Mark reviewed</button>
                        </div>
                        <p v-if="!(detail.intake.external_links || []).length" class="text-sm font-semibold text-slate-500">No external links.</p>
                    </div>

                    <div v-else-if="evidenceTab === 'messages'" class="mt-3 max-h-48 space-y-2 overflow-y-auto">
                        <div v-for="msg in detail.party_messages || []" :key="msg.id" class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-800">
                            <span class="font-black text-slate-900">{{ msg.author }}</span> · {{ formatWhen(msg.created_at) }}
                            <p class="mt-1 whitespace-pre-wrap">{{ msg.body }}</p>
                        </div>
                        <button type="button" class="mt-2 text-[10px] font-black uppercase text-primary-800 underline" @click="$emit('checklist-auto', 'review_conversation')">Mark conversation reviewed</button>
                    </div>

                    <div v-else class="mt-3 max-h-48 space-y-2 overflow-y-auto">
                        <div v-for="msg in detail.messages || []" :key="msg.id" class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-800">
                            <span class="font-black">{{ msg.author }}</span> · {{ formatWhen(msg.created_at) }}
                            <p class="mt-1 whitespace-pre-wrap">{{ msg.body }}</p>
                        </div>
                    </div>
                </section>

                <!-- Assessment -->
                <section class="rounded-2xl border border-primary-100 bg-primary-50/40 p-4">
                    <p class="text-xs font-black uppercase text-primary-900">Investigation & recommendation</p>
                    <slot name="assessment" />
                </section>

                <!-- Audit -->
                <section class="rounded-2xl border border-slate-100 bg-white p-4">
                    <p class="text-xs font-black uppercase text-slate-500">Audit trail</p>
                    <ul class="mt-2 max-h-56 space-y-2 overflow-y-auto text-xs font-semibold text-slate-700">
                        <li v-for="event in sortedEvents" :key="event.id">
                            <span class="font-black text-slate-900">{{ event.action_label || event.action }}</span>
                            · {{ formatWhen(event.created_at) }}
                            <span v-if="event.actor">· {{ event.actor }}</span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <!-- Expandable panels triggered from quick bar -->
        <OperationsExpandableAction v-if="showNote" title="Internal note" icon="📝" tone="slate" submit-label="Save note" :busy="busy.note" @submit="$emit('note', noteBody); noteBody = ''; showNote = false">
            <OperationsFormField v-model="noteBody" label="Staff-only note" multiline :rows="4" />
        </OperationsExpandableAction>

        <OperationsExpandableAction v-if="showEvidence" title="Request evidence" icon="📎" submit-label="Send request" :busy="busy.evidence" @submit="submitEvidence">
            <div class="space-y-3">
                <label class="block text-xs font-bold text-slate-600">
                    Template
                    <select v-model="evidenceTemplate" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold" @change="applyTemplate">
                        <option value="">Custom message</option>
                        <option v-for="t in detail.evidence_templates || []" :key="t.key" :value="t.key">{{ t.label }}</option>
                    </select>
                </label>
                <OperationsFormField v-model="evidenceBody" label="Request details" multiline :rows="5" />
            </div>
        </OperationsExpandableAction>

        <OperationsExpandableAction v-if="showContact" :title="`Message ${contactParty}`" icon="✉" submit-label="Send" :busy="busy.contact" @submit="submitContact">
            <div class="space-y-3">
                <OperationsFormField v-model="contactSubject" label="Subject" />
                <OperationsFormField v-model="contactBody" label="Message" multiline :rows="5" />
            </div>
        </OperationsExpandableAction>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import OperationsContextStats from '@/Pages/Operations/Components/OperationsContextStats.vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsFormField from '@/Pages/Operations/Components/OperationsFormField.vue';

const props = defineProps({
    detail: { type: Object, default: null },
    busy: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
    'acknowledge',
    'checklist',
    'checklist-auto',
    'evidence-reviewed',
    'note',
    'evidence',
    'contact',
    'ready',
    'respond-guidance',
    'approve-mutual',
]);

const evidenceTab = ref('files');
const guidanceResponse = ref('');
const showNote = ref(false);
const showEvidence = ref(false);
const showContact = ref(false);
const contactParty = ref('client');
const noteBody = ref('');
const evidenceBody = ref('');
const evidenceTemplate = ref('');
const contactSubject = ref('');
const contactBody = ref('');

const evidenceTabs = [
    { key: 'files', label: 'Files' },
    { key: 'links', label: 'Links' },
    { key: 'messages', label: 'Messages' },
    { key: 'thread', label: 'Full thread' },
];

const workflowSteps = computed(() => props.detail?.workflow?.steps ?? []);
const workflowProgress = computed(() => props.detail?.workflow?.progress_percent ?? 0);

const quickStats = computed(() => {
    if (!props.detail?.dispute) return [];
    const d = props.detail.dispute;
    return [
        { label: 'Severity', value: d.severity },
        { label: 'Value', value: formatMinor(d.disputed_amount_minor) },
        { label: 'Days open', value: `${d.days_open ?? 0}d` },
    ];
});

const quickChips = computed(() => [
    { label: 'Client', value: props.detail?.parties?.client?.name },
    { label: 'Freelancer', value: props.detail?.parties?.freelancer?.name },
    { label: 'Filed by', value: props.detail?.parties?.filed_by_party },
]);

const contextLinks = computed(() => {
    const contract = props.detail?.dispute?.contract;
    if (!contract?.url) {
        return [];
    }

    return [{
        label: 'Contract',
        title: contract.reference_code,
        href: contract.url,
        preview: 'View the contract this dispute belongs to',
    }];
});

const sortedEvents = computed(() => [...(props.detail?.events ?? [])].reverse());

watch(() => props.detail, () => {
    showNote.value = false;
    showEvidence.value = false;
    showContact.value = false;
    guidanceResponse.value = '';
});

function submitGuidanceResponse() {
    if (!guidanceResponse.value.trim()) {
        return;
    }
    emit('respond-guidance', guidanceResponse.value.trim());
}

function isReviewed(key) {
    return Boolean(props.detail?.evidence_review?.[key]);
}

function toggleStep(key, checked) {
    const completed = workflowSteps.value.filter((s) => s.completed).map((s) => s.key);
    const next = checked ? [...new Set([...completed, key])] : completed.filter((k) => k !== key);
    emit('checklist', next);
}

function openContact(party) {
    contactParty.value = party;
    contactSubject.value = 'Dispute update from HustleSafe';
    contactBody.value = '';
    showContact.value = true;
}

function applyTemplate() {
    const t = (props.detail?.evidence_templates ?? []).find((x) => x.key === evidenceTemplate.value);
    if (t) evidenceBody.value = t.body;
}

function submitEvidence() {
    emit('evidence', { body: evidenceBody.value, audience: 'both' });
    evidenceBody.value = '';
    showEvidence.value = false;
}

function submitContact() {
    emit('contact', { party: contactParty.value, subject: contactSubject.value, body: contactBody.value, channel: 'both' });
    showContact.value = false;
}

function formatMinor(minor) {
    if (!minor) return '—';
    return `₦${(Number(minor) / 100).toLocaleString()}`;
}

function formatWhen(iso) {
    if (!iso) return '—';
    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    } catch {
        return iso;
    }
}
</script>
