<template>
    <OperationsShell title="Moderation centre" subtitle="Tabbed quest and proposal queues with slide-in review panels. Search and sort happen on the loaded queue without extra page reloads.">
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
            <template #actions="{ row }">
                <div class="inline-flex gap-1">
                    <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openDetail(row)">Open</button>
                </div>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" :subtitle="slideSubtitle" eyebrow="Moderation panel" @close="slideOpen = false">
            <div v-if="detailLoading" class="py-10 text-center text-sm font-semibold text-slate-500">Loading detail…</div>
            <div v-else-if="detail" class="space-y-4">
                <OperationsContextStats :heading="slideTitle" :stats="moderationStats" :chips="moderationChips" :links="moderationLinks" />
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

                <OperationsExpandableAction
                    v-if="activeModule === 'quests'"
                    title="Contact freelancer"
                    hint="Reach the freelancer on this quest."
                    icon="💬"
                    tone="sky"
                    submit-label="Send to freelancer"
                    :busy="busy.contact"
                    @submit="contactQuest('freelancer')"
                >
                    <input v-model="contactForm.subject" class="form-input" placeholder="Subject" />
                    <textarea v-model="contactForm.body" class="form-input mt-3 min-h-20" placeholder="Message body" />
                    <select v-model="contactForm.channel" class="form-input mt-3">
                        <option value="both">Email + in-app</option>
                        <option value="email">Email only</option>
                        <option value="in_app">In-app only</option>
                    </select>
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
                    <input v-model="editForm.title" class="form-input" placeholder="Title" />
                    <textarea v-model="editForm.description" class="form-input min-h-28" placeholder="Description" />
                    <select v-model="editForm.quest_category_id" class="form-input">
                        <option value="">Category unchanged</option>
                        <option v-for="cat in categoryOptions" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                    </select>
                    <input v-model.number="editForm.max_offers" type="number" min="1" class="form-input" placeholder="Max proposals" />
                    <input v-model="editForm.city" class="form-input" placeholder="City" />
                    <textarea v-model="editForm.reason" class="form-input min-h-20" placeholder="Audit reason (required)" />
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input v-model="editForm.notify_client" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                        Notify client after save
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50" :disabled="busy.edit" @click="saveQuestEdit(false)">
                            <span v-if="busy.edit" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                            Save now
                        </button>
                        <button type="button" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm font-black text-primary-900 disabled:opacity-50" :disabled="busy.edit" @click="saveQuestEdit(true)">Submit for approval</button>
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
    </OperationsShell>
</template>

<script setup>
import OperationsContextStats from '@/Pages/Operations/Components/OperationsContextStats.vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsToast } from '@/composables/useOperationsToast';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const { toast } = useOperationsToast();

const props = defineProps({
    quest_queues: { type: Array, required: true },
    proposal_queues: { type: Array, required: true },
    options: { type: Object, required: true },
    capabilities: { type: Object, required: true },
});

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

const emptyMessage = computed(() => 'Select a queue tab to load items.');

const slideTitle = computed(() => selectedRow.value?.title || selectedRow.value?.reference_code || (selectedRow.value ? `#${selectedRow.value.id}` : ''));
const slideSubtitle = computed(() => (activeModule.value === 'quests' ? 'Quest moderation' : 'Proposal moderation'));

const actionForm = reactive({ admin_status: '', reason: '' });
const noticeForm = reactive({ type: 'warning', body: '', visible_to_users: true });
const noteForm = reactive({ body: '' });
const flagForm = reactive({ type: 'policy_violation', priority: 'medium', description: '' });
const proposalFlagForm = reactive({ type: 'policy_violation', priority: 'medium', description: '', notify_freelancer: true });
const removeForm = reactive({ reason: '', confirmation: '' });
const contactForm = reactive({ subject: '', body: '', channel: 'both', recipient: 'client', open_cs_ticket: false });
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
                href: route('operations.users.index', { q: client.email }),
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
                href: quest.route_key ? route('quests.show', quest.route_key) : route('operations.moderation.index', { module: 'quests' }),
                external: Boolean(quest.route_key),
            });
        }

        if (freelancer?.email) {
            links.push({
                label: 'Freelancer account',
                title: freelancer.name,
                preview: freelancer.email,
                href: route('operations.users.index', { q: freelancer.email }),
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
        activeModule.value === 'quests' ? route('operations.api.moderation.quests') : route('operations.api.moderation.proposals');

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
            ? route('operations.api.moderation.quests.detail', row.id)
            : route('operations.api.moderation.proposals.detail', row.id);

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
            ? route('operations.api.moderation.quests.admin-status', selectedRow.value.id)
            : route('operations.api.moderation.proposals.admin-status', selectedRow.value.id);

    await runAction('adminStatus', () => window.axios.patch(url, { ...actionForm }), 'Admin status updated.', async () => {
        await openDetail(selectedRow.value);
        await reloadActiveQueue();
    });
}

async function postNotice() {
    if (!selectedRow.value) return;
    const url =
        activeModule.value === 'quests'
            ? route('operations.api.moderation.quests.notices', selectedRow.value.id)
            : route('operations.api.moderation.proposals.notices', selectedRow.value.id);

    await runAction('notice', () => window.axios.post(url, { ...noticeForm }), 'Notice posted.', async () => {
        noticeForm.body = '';
        await openDetail(selectedRow.value);
    });
}

async function postNote() {
    if (!selectedRow.value) return;
    const url =
        activeModule.value === 'quests'
            ? route('operations.api.moderation.quests.notes', selectedRow.value.id)
            : route('operations.api.moderation.proposals.notes', selectedRow.value.id);

    await runAction('note', () => window.axios.post(url, { ...noteForm }), 'Note saved.', async () => {
        noteForm.body = '';
        await openDetail(selectedRow.value);
    });
}

async function postQuestFlag() {
    if (!selectedRow.value || activeModule.value !== 'quests') return;
    await runAction('questFlag', () => window.axios.post(route('operations.api.moderation.quests.flags', selectedRow.value.id), { ...flagForm }), 'Flag created.', async () => {
        flagForm.description = '';
        await openDetail(selectedRow.value);
        await reloadActiveQueue();
    });
}

async function postProposalFlag() {
    if (!selectedRow.value || activeModule.value !== 'proposals') return;
    await runAction('proposalFlag', () => window.axios.post(route('operations.api.moderation.proposals.flags', selectedRow.value.id), { ...proposalFlagForm }), 'Flag created.', async () => {
        proposalFlagForm.description = '';
        await openDetail(selectedRow.value);
        await reloadActiveQueue();
    });
}

async function saveQuestEdit(submitForApproval) {
    if (!selectedRow.value) return;
    await runAction('edit', () => window.axios.patch(route('operations.api.moderation.quests.update', selectedRow.value.id), {
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

    await runAction('media', () => window.axios.delete(route('operations.api.moderation.quests.files.destroy', [selectedRow.value.id, media.id]), { data: { reason } }), 'Media removed.', (response) => {
        detail.value = response?.data?.quest ?? detail.value;
    });
}

async function contactQuest(recipient) {
    if (!selectedRow.value) return;
    contactForm.recipient = recipient;
    await runAction('contact', () => window.axios.post(route('operations.api.moderation.quests.contact', selectedRow.value.id), { ...contactForm, recipient }), 'Message sent.', async () => {
        contactForm.body = '';
    });
}

async function contactProposal() {
    if (!selectedRow.value) return;
    await runAction('contact', () => window.axios.post(route('operations.api.moderation.proposals.contact', selectedRow.value.id), contactForm), 'Message sent.', async () => {
        contactForm.body = '';
    });
}

async function removeProposal() {
    if (!selectedRow.value || activeModule.value !== 'proposals') return;
    await runAction('remove', () => window.axios.delete(route('operations.api.moderation.proposals.remove', selectedRow.value.id), { data: { ...removeForm } }), 'Proposal removed.', async () => {
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
</style>
