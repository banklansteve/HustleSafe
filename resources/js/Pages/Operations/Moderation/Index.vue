<template>
    <component :is="shellComponent" title="Moderation centre" subtitle="Tabbed quest and proposal queues with slide-in review panels. Search and sort happen on the loaded queue without extra page reloads.">
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="mod in modules"
                :key="mod.key"
                type="button"
                class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide"
                :class="activeModule === mod.key ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700 hover:bg-primary-50'"
                @click="switchModule(mod.key)"
            >
                {{ mod.label }}
            </button>
        </div>

        <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="queue in currentQueues"
                :key="queue.key"
                type="button"
                class="shrink-0 rounded-2xl px-4 py-2 text-left"
                :class="activeQueue === queue.key ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700 hover:bg-primary-50'"
                @click="loadQueue(queue)"
            >
                <span class="block text-xs font-black uppercase tracking-wide">{{ queue.label }}</span>
                <span class="mt-0.5 block text-[11px] font-semibold opacity-80">{{ queue.hint }}</span>
            </button>
        </div>

        <OperationsQueueTable
            :columns="tableColumns"
            :rows="queue.pageItems.value"
            :loading="loading"
            v-model:search="queue.search.value"
            v-model:per-page="queue.perPage.value"
            :page="queue.page.value"
            :total="queue.total.value"
            :total-pages="queue.totalPages.value"
            :sort-key="queue.sortKey.value"
            :sort-dir="queue.sortDir.value"
            :empty-message="emptyMessage"
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openDetail"
        >
            <template #cell-title="{ row }">
                <span class="font-semibold text-slate-950">{{ row.title || row.excerpt || `Proposal #${row.id}` }}</span>
                <span class="mt-0.5 block text-xs text-slate-500">{{ row.reference_code || row.quest?.reference_code }}</span>
            </template>
            <template #cell-admin_status="{ row }">
                <span class="rounded-full bg-primary-50 px-2 py-1 text-[10px] font-black uppercase text-primary-800 ring-1 ring-primary-100">
                    {{ labelize(row.admin_status?.value || row.admin_status) }}
                </span>
            </template>
            <template #cell-health_score="{ row }">
                <span
                    v-if="row.health_score != null"
                    class="rounded-full px-2 py-1 text-[10px] font-black tabular-nums"
                    :class="row.health_score < 50 ? 'bg-rose-100 text-rose-800 ring-1 ring-rose-200' : row.health_score < 75 ? 'bg-amber-100 text-amber-900 ring-1 ring-amber-200' : 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-200'"
                >
                    {{ row.health_score }}
                </span>
                <span v-else class="text-xs font-semibold text-slate-400">—</span>
            </template>
            <template #actions="{ row }">
                <div class="inline-flex gap-1">
                    <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openDetail(row)">Open</button>
                </div>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" :subtitle="slideSubtitle" eyebrow="Moderation panel" @close="slideOpen = false">
            <div v-if="detailLoading" class="py-10 text-center text-sm font-semibold text-slate-500">Loading detail…</div>
            <div v-else-if="detail" class="space-y-4">
                <section
                    v-if="publicPreviewLinks.length"
                    class="rounded-xl border border-primary-200 bg-gradient-to-br from-primary-50/90 via-white to-teal-50/60 p-4 shadow-sm ring-1 ring-primary-100"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-800">
                        Public preview
                    </p>
                    <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-600">
                        Open the live pages exactly as clients and freelancers see them on HustleSafe.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a
                            v-for="link in publicPreviewLinks"
                            :key="link.href"
                            :href="link.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-white px-3.5 py-2 text-[11px] font-black uppercase tracking-wide text-primary-900 shadow-sm transition hover:border-primary-400 hover:bg-primary-50"
                        >
                            {{ link.label }}
                            <span aria-hidden="true">↗</span>
                        </a>
                    </div>
                </section>

                <OperationsContextStats :heading="slideTitle" :stats="moderationStats" :chips="moderationChips" :links="moderationLinks" />

                <section
                    v-if="activeModule === 'quests' && detail.health"
                    class="rounded-xl border border-slate-200 bg-white p-4"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Quest health</p>
                    <p class="mt-2 text-sm font-bold text-slate-900">
                        Score
                        <span
                            class="ml-2 rounded-full px-2 py-0.5 text-xs font-black tabular-nums"
                            :class="detail.health.low ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-900'"
                        >
                            {{ detail.health.score ?? '—' }}
                        </span>
                    </p>
                    <p v-if="detail.health.updated_at" class="mt-1 text-xs font-semibold text-slate-500">
                        Updated {{ formatDateTime(detail.health.updated_at) }}
                    </p>
                </section>

                <section
                    v-if="activeModule === 'quests' && detail.nudge_logs?.length"
                    class="rounded-xl border border-slate-200 bg-white p-4"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Auto-nudge trail</p>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="log in detail.nudge_logs"
                            :key="log.id"
                            class="rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2 text-xs"
                        >
                            <p class="font-black text-slate-900">{{ log.subject }}</p>
                            <p class="mt-0.5 font-semibold text-slate-600">
                                {{ log.nudge_type }} · {{ log.recipient || 'Unknown' }} · {{ formatDateTime(log.sent_at) }}
                            </p>
                        </li>
                    </ul>
                </section>

                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Actions</p>

                <OperationsExpandableAction
                    v-if="activeModule === 'quests'"
                    title="Contact client"
                    hint="Email, in-app message, or open a CS ticket."
                    icon="✉"
                    tone="sky"
                    submit-label="Send to client"
                    :busy="busy.contact"
                    @submit="contactQuest('client')"
                >
                    <div class="space-y-4">
                        <div class="moderation-field">
                            <label class="moderation-label">Subject</label>
                            <input v-model="contactForm.subject" class="form-input" placeholder="Subject" />
                        </div>
                        <div class="moderation-field">
                            <label class="moderation-label">Message</label>
                            <textarea v-model="contactForm.body" class="form-input min-h-24" placeholder="Message body" />
                        </div>
                        <div class="moderation-field">
                            <label class="moderation-label">Delivery</label>
                            <select v-model="contactForm.channel" class="form-input">
                                <option value="both">Email + in-app</option>
                                <option value="email">Email only</option>
                                <option value="in_app">In-app only</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input v-model="contactForm.open_cs_ticket" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                            Also open a CS support ticket
                        </label>
                    </div>
                </OperationsExpandableAction>

                <OperationsExpandableAction
                    v-if="activeModule === 'quests'"
                    title="Contact freelancer"
                    hint="Only freelancers who have submitted a proposal on this quest."
                    icon="💬"
                    tone="sky"
                    submit-label="Send to freelancer"
                    :busy="busy.contact"
                    :disabled="!contactForm.freelancer_id"
                    @submit="contactQuest('freelancer')"
                >
                    <div class="space-y-4">
                        <div class="moderation-field">
                            <label class="moderation-label">Freelancer</label>
                            <select
                                v-model="contactForm.freelancer_id"
                                class="form-input"
                                :disabled="!questProposalFreelancers.length"
                            >
                                <option value="">
                                    {{ questProposalFreelancers.length ? 'Select a freelancer…' : 'No freelancers with proposals' }}
                                </option>
                                <option v-for="freelancer in questProposalFreelancers" :key="freelancer.id" :value="freelancer.id">
                                    {{ freelancer.label }}
                                </option>
                            </select>
                            <p v-if="!questProposalFreelancers.length" class="mt-2 text-xs font-semibold text-slate-500">
                                This quest has no proposals yet, so there is no freelancer to contact.
                            </p>
                        </div>

                        <template v-if="contactForm.freelancer_id !== '' && contactForm.freelancer_id != null">
                            <div class="moderation-field">
                                <label class="moderation-label">Subject</label>
                                <input v-model="contactForm.subject" class="form-input" placeholder="Subject" />
                            </div>
                            <div class="moderation-field">
                                <label class="moderation-label">Message</label>
                                <textarea v-model="contactForm.body" class="form-input min-h-24" placeholder="Message body" />
                            </div>
                            <div class="moderation-field">
                                <label class="moderation-label">Delivery</label>
                                <select v-model="contactForm.channel" class="form-input">
                                    <option value="both">Email + in-app</option>
                                    <option value="email">Email only</option>
                                    <option value="in_app">In-app only</option>
                                </select>
                            </div>
                        </template>
                    </div>
                </OperationsExpandableAction>

                <OperationsExpandableAction
                    v-if="activeModule === 'proposals'"
                    title="Contact freelancer"
                    hint="Email, in-app message, or CS ticket."
                    icon="✉"
                    tone="sky"
                    submit-label="Send message"
                    :busy="busy.contact"
                    @submit="contactProposal"
                >
                    <input v-model="contactForm.subject" class="form-input" placeholder="Subject" />
                    <textarea v-model="contactForm.body" class="form-input mt-3 min-h-20" placeholder="Message body" />
                    <select v-model="contactForm.channel" class="form-input mt-3">
                        <option value="both">Email + in-app</option>
                        <option value="email">Email only</option>
                        <option value="in_app">In-app only</option>
                    </select>
                    <label class="mt-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input v-model="contactForm.open_cs_ticket" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                        Also open a CS support ticket
                    </label>
                </OperationsExpandableAction>

                <OperationsExpandableAction v-if="activeModule === 'quests'" title="Edit quest" hint="Non-critical fields; submit major edits for approval." icon="✎" tone="slate">
                    <div class="space-y-5">
                        <div class="moderation-field">
                            <label class="moderation-label" for="edit-quest-title">Title</label>
                            <input id="edit-quest-title" v-model="editForm.title" class="form-input" placeholder="Title" />
                        </div>
                        <div class="moderation-field">
                            <label class="moderation-label">Description</label>
                            <QuestRichDescriptionEditor
                                :key="`quest-edit-desc-${selectedRow?.id ?? 'new'}`"
                                v-model="editForm.description"
                                placeholder="Quest description as posted by the client…"
                            />
                        </div>
                        <div class="moderation-field">
                            <label class="moderation-label" for="edit-quest-category">Category</label>
                            <select id="edit-quest-category" v-model="editForm.quest_category_id" class="form-input">
                                <option value="">Category unchanged</option>
                                <option v-for="cat in categoryOptions" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                            </select>
                        </div>
                        <div class="moderation-field">
                            <label class="moderation-label" for="edit-quest-max-offers">Max proposals</label>
                            <input id="edit-quest-max-offers" v-model.number="editForm.max_offers" type="number" min="1" class="form-input" placeholder="e.g. 10" />
                        </div>
                        <div class="moderation-field">
                            <label class="moderation-label" for="edit-quest-city">City</label>
                            <input id="edit-quest-city" v-model="editForm.city" class="form-input" placeholder="City" />
                        </div>
                        <div class="moderation-field">
                            <label class="moderation-label" for="edit-quest-reason">Audit reason</label>
                            <textarea id="edit-quest-reason" v-model="editForm.reason" class="form-input min-h-24" placeholder="Required — explain what you changed and why" />
                        </div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input v-model="editForm.notify_client" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                            Notify client after save
                        </label>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50" :disabled="busy.edit" @click="saveQuestEdit(false)">
                                <span v-if="busy.edit" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                Save now
                            </button>
                            <button type="button" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm font-black text-primary-900 disabled:opacity-50" :disabled="busy.edit" @click="saveQuestEdit(true)">Submit for approval</button>
                        </div>
                    </div>
                </OperationsExpandableAction>

                <OperationsExpandableAction v-if="activeModule === 'quests' && mediaItems.length" :title="`Media (${mediaItems.length})`" hint="Preview or remove attachments." icon="🖼" tone="slate">
                    <article v-for="media in mediaItems" :key="media.id" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-white p-3">
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ media.name }}</p>
                            <p class="text-xs text-slate-500">{{ media.size }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a :href="media.url" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 px-2 py-1 text-[10px] font-black uppercase text-slate-700">Preview</a>
                            <button type="button" class="rounded-lg bg-rose-700 px-2 py-1 text-[10px] font-black uppercase text-white disabled:opacity-40" :disabled="busy.media" @click="removeMedia(media)">Remove</button>
                        </div>
                    </article>
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Admin status" hint="Update moderation state with a required reason." icon="⚖" submit-label="Update status" :busy="busy.adminStatus" @submit="saveAdminStatus">
                    <select v-model="actionForm.admin_status" class="form-input">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <textarea v-model="actionForm.reason" class="form-input mt-3 min-h-24" placeholder="Reason (required)" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Post notice" hint="Warning or informational notice on the record." icon="📣" submit-label="Post notice" :busy="busy.notice" @submit="postNotice">
                    <select v-model="noticeForm.type" class="form-input">
                        <option value="warning">Warning</option>
                        <option value="informational">Informational</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <textarea v-model="noticeForm.body" class="form-input mt-3 min-h-20" placeholder="Notice to users" />
                    <label class="mt-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input v-model="noticeForm.visible_to_users" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                        Visible to client & freelancer
                    </label>
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Internal note" hint="Staff-only note on this record." icon="📝" submit-label="Save note" :busy="busy.note" @submit="postNote">
                    <textarea v-model="noteForm.body" class="form-input min-h-20" placeholder="Internal note (@mention admins in text)" />
                </OperationsExpandableAction>

                <OperationsExpandableAction v-if="activeModule === 'quests'" title="Flag quest" hint="Creates a tracked moderation flag." icon="🚩" tone="amber" submit-label="Create flag" :busy="busy.questFlag" @submit="postQuestFlag">
                    <select v-model="flagForm.type" class="form-input">
                        <option value="policy_violation">Policy violation</option>
                        <option value="suspicious_content">Suspicious content</option>
                        <option value="off_platform_solicitation">Off-platform solicitation</option>
                        <option value="fraudulent_posting">Fraudulent posting</option>
                        <option value="other">Other</option>
                    </select>
                    <select v-model="flagForm.priority" class="form-input mt-3">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                    <textarea v-model="flagForm.description" class="form-input mt-3 min-h-20" placeholder="Flag description (min 30 chars)" />
                </OperationsExpandableAction>

                <OperationsExpandableAction v-if="activeModule === 'proposals'" title="Flag proposal" hint="Escalate policy or fraud concerns." icon="🚩" tone="amber" submit-label="Create flag" :busy="busy.proposalFlag" @submit="postProposalFlag">
                    <select v-model="proposalFlagForm.type" class="form-input">
                        <option v-for="t in proposalFlagTypes" :key="t" :value="t">{{ labelize(t) }}</option>
                    </select>
                    <select v-model="proposalFlagForm.priority" class="form-input mt-3">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                    <textarea v-model="proposalFlagForm.description" class="form-input mt-3 min-h-20" placeholder="Flag description (min 30 chars)" />
                    <label class="mt-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input v-model="proposalFlagForm.notify_freelancer" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                        Notify freelancer
                    </label>
                </OperationsExpandableAction>

                <OperationsExpandableAction v-if="activeModule === 'proposals'" title="Policy removal" hint="Permanently remove after typing REMOVE." icon="🗑" tone="rose" submit-label="Remove proposal" :busy="busy.remove" @submit="removeProposal">
                    <textarea v-model="removeForm.reason" class="form-input min-h-20" placeholder="Removal reason" />
                    <input v-model="removeForm.confirmation" class="form-input mt-3" placeholder="Type REMOVE to confirm" />
                </OperationsExpandableAction>
            </div>
        </OperationsSlideOver>
    </component>
</template>

<script setup>
import OperationsContextStats from '@/Pages/Operations/Components/OperationsContextStats.vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import QuestRichDescriptionEditor from '@/Components/Quests/QuestRichDescriptionEditor.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsToast } from '@/composables/useOperationsToast';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const { toast } = useOperationsToast();

const props = defineProps({
    quest_queues: { type: Array, required: true },
    proposal_queues: { type: Array, required: true },
    options: { type: Object, required: true },
    capabilities: { type: Object, required: true },
    route_prefix: { type: String, default: 'operations' },
    use_admin_shell: { type: Boolean, default: false },
});

const shellComponent = computed(() => (props.use_admin_shell ? AdminShell : OperationsShell));

function apiRoute(name, params = undefined) {
    const prefix = props.route_prefix === 'admin' ? 'admin.api.moderation' : 'operations.api.moderation';

    return route(`${prefix}.${name}`, params);
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return value;
    }
}

function usersIndexUrl(email) {
    return props.route_prefix === 'admin'
        ? route('admin.users.index', { q: email })
        : route('operations.users.index', { q: email });
}

const modules = [
    { key: 'quests', label: 'Quests' },
    { key: 'proposals', label: 'Proposals' },
];

const activeModule = ref(new URLSearchParams(window.location.search).get('module') === 'proposals' ? 'proposals' : 'quests');
const activeQueue = ref('');
const rawItems = ref([]);
const loading = ref(false);
const slideOpen = ref(false);
const detail = ref(null);
const detailLoading = ref(false);
const selectedRow = ref(null);
const busy = reactive({
    adminStatus: false,
    notice: false,
    note: false,
    questFlag: false,
    proposalFlag: false,
    contact: false,
    edit: false,
    remove: false,
    media: false,
});

const queue = useClientQueue(() => rawItems.value, {
    defaultSortKey: 'id',
    searchFields: ['id', 'title', 'reference_code', 'admin_status', 'status', 'excerpt', 'client.name', 'client.email', 'freelancer.name', 'freelancer.email'],
});

const currentQueues = computed(() => (activeModule.value === 'quests' ? props.quest_queues : props.proposal_queues));

const tableColumns = computed(() =>
    activeModule.value === 'quests'
        ? [
              { key: 'id', label: 'ID', sortable: true },
              { key: 'title', label: 'Quest', sortable: true },
              { key: 'health_score', label: 'Health', sortable: true },
              { key: 'admin_status', label: 'Admin status', sortable: true, path: 'admin_status.value' },
              { key: 'status', label: 'Status', sortable: true },
              { key: 'created_at', label: 'Created', sortable: true, format: 'date' },
          ]
        : [
              { key: 'id', label: 'ID', sortable: true },
              { key: 'title', label: 'Proposal', sortable: true },
              { key: 'admin_status', label: 'Admin status', sortable: true },
              { key: 'status', label: 'Status', sortable: true },
              { key: 'created_at', label: 'Created', sortable: true, format: 'date' },
          ],
);

const statusOptions = computed(() =>
    activeModule.value === 'quests' ? props.options.quest_admin_statuses : props.options.proposal_admin_statuses,
);

const emptyMessage = computed(() =>
    activeQueue.value ? 'No items in this queue. Try “All quests” or “All proposals”.' : 'Select a queue tab to load items.',
);

const slideTitle = computed(() => selectedRow.value?.title || selectedRow.value?.reference_code || (selectedRow.value ? `#${selectedRow.value.id}` : ''));
const slideSubtitle = computed(() => (activeModule.value === 'quests' ? 'Quest moderation' : 'Proposal moderation'));

const actionForm = reactive({ admin_status: '', reason: '' });
const noticeForm = reactive({ type: 'warning', body: '', visible_to_users: true });
const noteForm = reactive({ body: '' });
const flagForm = reactive({ type: 'policy_violation', priority: 'medium', description: '' });
const proposalFlagForm = reactive({ type: 'policy_violation', priority: 'medium', description: '', notify_freelancer: true });
const removeForm = reactive({ reason: '', confirmation: '' });
const contactForm = reactive({
    subject: '',
    body: '',
    channel: 'both',
    recipient: 'client',
    freelancer_id: '',
    open_cs_ticket: false,
});
const editForm = reactive({
    title: '',
    description: '',
    quest_category_id: '',
    max_offers: null,
    city: '',
    reason: '',
    notify_client: true,
    submit_for_approval: false,
});
const moderationStats = computed(() => {
    if (!detail.value) return [];

    if (activeModule.value === 'quests') {
        const q = detail.value.overview?.quest;
        const proposals = detail.value.proposals?.summary;
        const client = detail.value.overview?.client_context;

        return [
            { label: 'Admin status', value: labelize(q?.admin_status?.value || q?.admin_status) },
            { label: 'Quest status', value: labelize(q?.status) },
            { label: 'Budget', value: q?.budget || '—' },
            { label: 'Proposals', value: String(proposals?.total ?? q?.proposals_count ?? 0) },
            { label: 'Client quests', value: String(client?.quests_posted ?? 0), hint: client?.amount_spent },
            { label: 'Location', value: q?.location || '—' },
        ];
    }

    const p = detail.value.overview?.proposal;
    const risk = detail.value.risk;

    return [
        { label: 'Admin status', value: labelize(p?.admin_status?.value || p?.admin_status) },
        { label: 'Proposal status', value: labelize(p?.status) },
        { label: 'Quoted', value: detail.value.content?.proposed_amount || '—' },
        { label: 'Risk score', value: risk?.score != null ? String(risk.score) : '—' },
        { label: 'Freelancer', value: p?.freelancer?.name || detail.value.freelancer?.name || '—' },
        { label: 'Quest', value: p?.quest?.title || detail.value.quest?.title || '—' },
    ];
});

const moderationChips = computed(() => {
    if (!detail.value) return [];
    const chips = [];

    if (activeModule.value === 'quests') {
        const flags = detail.value.flags?.active?.length ?? detail.value.flags?.items?.length ?? 0;
        if (flags > 0) chips.push({ label: `${flags} active flag(s)`, tone: 'warn' });
        if (detail.value.escrow?.has_contract) chips.push({ label: 'Contract active', tone: 'warn' });
    } else if ((detail.value.flags?.active?.length ?? 0) > 0) {
        chips.push({ label: 'Flagged proposal', tone: 'warn' });
    }

    return chips;
});

const questProposalFreelancers = computed(() => {
    const items = detail.value?.proposals?.items ?? [];
    const excluded = new Set(['withdrawn', 'declined']);
    const byId = new Map();

    for (const item of items) {
        const freelancer = item?.freelancer;
        if (!freelancer?.id || excluded.has(String(item?.status ?? ''))) {
            continue;
        }

        const name = freelancer.first_name || freelancer.name || freelancer.email || `Freelancer #${freelancer.id}`;
        const statusLabel = item.status ? String(item.status).replace(/_/g, ' ') : '';
        byId.set(freelancer.id, {
            id: freelancer.id,
            slug: freelancer.slug ?? null,
            name,
            label: statusLabel ? `${name} · ${statusLabel}` : name,
        });
    }

    return [...byId.values()].sort((a, b) => a.label.localeCompare(b.label));
});

const publicPreviewLinks = computed(() => {
    if (!detail.value) {
        return [];
    }

    const links = [];

    if (activeModule.value === 'quests') {
        const routeKey = detail.value.overview?.quest?.route_key;
        if (routeKey) {
            links.push({
                label: 'View public quest',
                href: route('quests.show', routeKey),
            });
        }

        for (const freelancer of questProposalFreelancers.value.slice(0, 6)) {
            if (!freelancer.slug) {
                continue;
            }
            links.push({
                label: `Profile · ${freelancer.name}`,
                href: route('freelancers.public', freelancer.slug),
            });
            links.push({
                label: `Portfolio · ${freelancer.name}`,
                href: route('freelancers.public.portfolios', freelancer.slug),
            });
        }
    } else {
        const quest = detail.value.overview?.proposal?.quest ?? detail.value.quest;
        if (quest?.route_key) {
            links.push({
                label: 'View public quest',
                href: route('quests.show', quest.route_key),
            });
        }

        const slug =
            detail.value.overview?.proposal?.freelancer?.slug
            ?? detail.value.communications?.freelancer?.slug
            ?? detail.value.freelancer?.profile?.slug
            ?? detail.value.freelancer?.slug;

        if (slug) {
            links.push({
                label: 'View freelancer profile',
                href: route('freelancers.public', slug),
            });
            links.push({
                label: 'View portfolio gallery',
                href: route('freelancers.public.portfolios', slug),
            });
        }
    }

    return links;
});

const moderationLinks = computed(() => {
    if (!detail.value) return [];
    const links = [];

    if (activeModule.value === 'quests') {
        const q = detail.value.overview?.quest;
        const client = detail.value.overview?.client_context;

        if (q?.route_key) {
            links.push({
                label: 'Public quest',
                title: q.title || q.reference_code,
                preview: q.description_excerpt,
                href: route('quests.show', q.route_key),
                external: true,
            });
        }

        if (client?.email) {
            links.push({
                label: 'Client account',
                title: client.name,
                preview: client.email,
                href: usersIndexUrl(client.email),
            });
        }

        const lastNotice = detail.value.notices?.items?.[0];
        if (lastNotice?.body) {
            links.push({
                label: 'Latest notice',
                title: labelize(lastNotice.type),
                preview: String(lastNotice.body).slice(0, 140),
            });
        }
    } else {
        const p = detail.value.overview?.proposal;
        const freelancer = p?.freelancer || detail.value.freelancer;
        const quest = p?.quest || detail.value.quest;

        if (quest?.route_key || quest?.id) {
            links.push({
                label: 'Parent quest',
                title: quest.title || `Quest #${quest.id}`,
                preview: quest.reference_code,
                href: quest.route_key ? route('quests.show', quest.route_key) : (props.route_prefix === 'admin' ? route('admin.moderation.index', { module: 'quests' }) : route('operations.moderation.index', { module: 'quests' })),
                external: Boolean(quest.route_key),
            });
        }

        if (freelancer?.slug) {
            links.push({
                label: 'Public profile',
                title: freelancer.name,
                preview: 'Freelancer marketplace profile',
                href: route('freelancers.public', freelancer.slug),
                external: true,
            });
            links.push({
                label: 'Portfolio gallery',
                title: freelancer.name,
                preview: 'Public portfolio pieces',
                href: route('freelancers.public.portfolios', freelancer.slug),
                external: true,
            });
        }

        if (freelancer?.email) {
            links.push({
                label: 'Freelancer account',
                title: freelancer.name,
                preview: freelancer.email,
                href: usersIndexUrl(freelancer.email),
            });
        }

        if (detail.value.content?.pitch) {
            links.push({
                label: 'Proposal pitch',
                title: 'Opening lines',
                preview: String(detail.value.content.pitch).slice(0, 140),
            });
        }
    }

    return links;
});

const mediaItems = computed(() => detail.value?.media?.items ?? []);

const categoryOptions = computed(() => detail.value?.edit_options?.categories ?? props.options?.quest_edit?.categories ?? []);

const proposalFlagTypes = computed(() => props.options?.proposal_flag_types ?? ['policy_violation', 'other']);

onMounted(() => {
    const first = currentQueues.value[0];
    if (first) {
        loadQueue(first);
    }
});

watch(activeModule, () => {
    const first = currentQueues.value[0];
    if (first) {
        loadQueue(first);
    }
});

function switchModule(key) {
    activeModule.value = key;
}

async function loadQueue(queueDef) {
    activeQueue.value = queueDef.key;
    loading.value = true;
    rawItems.value = [];

    const endpoint =
        activeModule.value === 'quests' ? apiRoute('quests') : apiRoute('proposals');

    try {
        const { data } = await window.axios.get(endpoint, { params: queueDef.filter ?? {} });
        rawItems.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
}

async function openDetail(row) {
    selectedRow.value = row;
    slideOpen.value = true;
    detailLoading.value = true;
    detail.value = null;

    const url =
        activeModule.value === 'quests'
            ? apiRoute('quests.detail', row.id)
            : apiRoute('proposals.detail', row.id);

    try {
        const { data } = await window.axios.get(url);
        detail.value = data;
        const adminStatus =
            data.overview?.quest?.admin_status ??
            data.overview?.admin_status ??
            data.overview?.proposal?.admin_status ??
            data.admin_status;
        actionForm.admin_status = adminStatus?.value ?? adminStatus ?? '';
        actionForm.reason = '';

        if (activeModule.value === 'quests' && data.overview?.quest) {
            const q = data.overview.quest;
            editForm.title = q.title ?? '';
            editForm.description = q.description ?? '';
            editForm.quest_category_id = q.quest_category_id ?? '';
            editForm.max_offers = q.proposal_capacity ?? q.max_offers ?? null;
            editForm.city = q.city ?? '';
            editForm.reason = '';
            contactForm.subject = `Regarding your quest: ${q.title}`;
            contactForm.freelancer_id = '';
            contactForm.body = '';
            contactForm.channel = 'both';
        }

        if (activeModule.value === 'proposals') {
            const f = data.communications?.freelancer ?? data.freelancer;
            contactForm.subject = f?.name ? `Regarding your proposal · ${f.name}` : 'Regarding your proposal';
        }
    } finally {
        detailLoading.value = false;
    }
}

async function runAction(key, request, successMessage, after) {
    busy[key] = true;
    try {
        const response = await request();
        toast(response?.data?.message || successMessage);
        if (after) await after(response);
    } catch (error) {
        toast(error?.response?.data?.message || 'Action failed.', 'error');
    } finally {
        busy[key] = false;
    }
}

async function saveAdminStatus() {
    if (!selectedRow.value) return;
    const url =
        activeModule.value === 'quests'
            ? apiRoute('quests.admin-status', selectedRow.value.id)
            : apiRoute('proposals.admin-status', selectedRow.value.id);

    await runAction('adminStatus', () => window.axios.patch(url, { ...actionForm }), 'Admin status updated.', async () => {
        await openDetail(selectedRow.value);
        await reloadActiveQueue();
    });
}

async function postNotice() {
    if (!selectedRow.value) return;
    const url =
        activeModule.value === 'quests'
            ? apiRoute('quests.notices', selectedRow.value.id)
            : apiRoute('proposals.notices', selectedRow.value.id);

    await runAction('notice', () => window.axios.post(url, { ...noticeForm }), 'Notice posted.', async () => {
        noticeForm.body = '';
        await openDetail(selectedRow.value);
    });
}

async function postNote() {
    if (!selectedRow.value) return;
    const url =
        activeModule.value === 'quests'
            ? apiRoute('quests.notes', selectedRow.value.id)
            : apiRoute('proposals.notes', selectedRow.value.id);

    await runAction('note', () => window.axios.post(url, { ...noteForm }), 'Note saved.', async () => {
        noteForm.body = '';
        await openDetail(selectedRow.value);
    });
}

async function postQuestFlag() {
    if (!selectedRow.value || activeModule.value !== 'quests') return;
    await runAction('questFlag', () => window.axios.post(apiRoute('quests.flags', selectedRow.value.id), { ...flagForm }), 'Flag created.', async () => {
        flagForm.description = '';
        await openDetail(selectedRow.value);
        await reloadActiveQueue();
    });
}

async function postProposalFlag() {
    if (!selectedRow.value || activeModule.value !== 'proposals') return;
    await runAction('proposalFlag', () => window.axios.post(apiRoute('proposals.flags', selectedRow.value.id), { ...proposalFlagForm }), 'Flag created.', async () => {
        proposalFlagForm.description = '';
        await openDetail(selectedRow.value);
        await reloadActiveQueue();
    });
}

async function saveQuestEdit(submitForApproval) {
    if (!selectedRow.value) return;
    await runAction('edit', () => window.axios.patch(apiRoute('quests.update', selectedRow.value.id), {
        ...editForm,
        quest_category_id: editForm.quest_category_id || null,
        submit_for_approval: submitForApproval,
    }), submitForApproval ? 'Submitted for approval.' : 'Quest updated.', async (response) => {
        detail.value = response?.data?.quest ?? detail.value;
        if (!submitForApproval) {
            await openDetail(selectedRow.value);
        }
    });
}

async function removeMedia(media) {
    if (!selectedRow.value || !media?.id) return;
    const reason = window.prompt('Why is this media being removed? (min 10 characters)');
    if (!reason || reason.length < 10) return;

    await runAction('media', () => window.axios.delete(apiRoute('quests.files.destroy', [selectedRow.value.id, media.id]), { data: { reason } }), 'Media removed.', (response) => {
        detail.value = response?.data?.quest ?? detail.value;
    });
}

watch(
    () => contactForm.freelancer_id,
    (id) => {
        if (id === '' || id == null || activeModule.value !== 'quests') {
            return;
        }
        const match = questProposalFreelancers.value.find((f) => Number(f.id) === Number(id));
        if (match) {
            const questTitle = detail.value?.overview?.quest?.title ?? 'your quest';
            contactForm.subject = `Regarding ${questTitle}`;
        }
    },
);

async function contactQuest(recipient) {
    if (!selectedRow.value) return;

    if (recipient === 'freelancer' && (contactForm.freelancer_id === '' || contactForm.freelancer_id == null)) {
        toast('Select a freelancer who has proposed on this quest.', 'error');

        return;
    }

    contactForm.recipient = recipient;
    const payload = {
        subject: contactForm.subject,
        body: contactForm.body,
        channel: contactForm.channel,
        recipient,
        open_cs_ticket: contactForm.open_cs_ticket,
    };

    if (recipient === 'freelancer') {
        payload.freelancer_id = contactForm.freelancer_id;
    }

    await runAction('contact', () => window.axios.post(apiRoute('quests.contact', selectedRow.value.id), payload), 'Message sent.', async () => {
        contactForm.body = '';
    });
}

async function contactProposal() {
    if (!selectedRow.value) return;
    await runAction('contact', () => window.axios.post(apiRoute('proposals.contact', selectedRow.value.id), contactForm), 'Message sent.', async () => {
        contactForm.body = '';
    });
}

async function removeProposal() {
    if (!selectedRow.value || activeModule.value !== 'proposals') return;
    await runAction('remove', () => window.axios.delete(apiRoute('proposals.remove', selectedRow.value.id), { data: { ...removeForm } }), 'Proposal removed.', async () => {
        slideOpen.value = false;
        await reloadActiveQueue();
    });
}

async function reloadActiveQueue() {
    const queueDef = currentQueues.value.find((q) => q.key === activeQueue.value);
    if (queueDef) {
        await loadQueue(queueDef);
    }
}

function labelize(value) {
    return String(value || '').replaceAll('_', ' ');
}
</script>

<style scoped>
.form-input {
    @apply w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100;
}

.moderation-label {
    @apply mb-2 block text-[10px] font-black uppercase tracking-[0.18em] text-slate-500;
}

.moderation-field {
    @apply space-y-0;
}
</style>
