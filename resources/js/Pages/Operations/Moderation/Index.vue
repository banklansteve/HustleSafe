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
            <div v-else-if="detail" class="space-y-5">
                <section class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-bold text-slate-600">{{ questSummary }}</p>
                </section>

                <p v-if="actionMessage" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-900">{{ actionMessage }}</p>

                <section class="flex flex-wrap gap-2 border-b border-slate-100 pb-4">
                    <template v-if="activeModule === 'quests'">
                        <button type="button" class="rounded-lg bg-primary-700 px-3 py-1.5 text-[10px] font-black uppercase text-white" :disabled="actionBusy" @click="contactQuest('client')">Email client</button>
                        <button type="button" class="rounded-lg border border-primary-200 bg-primary-50 px-3 py-1.5 text-[10px] font-black uppercase text-primary-900" :disabled="actionBusy" @click="contactQuest('freelancer')">Email freelancer</button>
                    </template>
                    <button v-else type="button" class="rounded-lg bg-primary-700 px-3 py-1.5 text-[10px] font-black uppercase text-white" :disabled="actionBusy" @click="contactProposal">Email freelancer</button>
                </section>

                <section v-if="activeModule === 'quests'" class="space-y-3 rounded-2xl border border-slate-100 p-4">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Contact</h3>
                    <input v-model="contactForm.subject" class="form-input" placeholder="Subject" />
                    <textarea v-model="contactForm.body" class="form-input min-h-20" placeholder="Message body" />
                    <select v-model="contactForm.channel" class="form-input">
                        <option value="both">Email + in-app</option>
                        <option value="email">Email only</option>
                        <option value="in_app">In-app only</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input v-model="contactForm.open_cs_ticket" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                        Also open a CS support ticket
                    </label>
                </section>

                <section v-if="activeModule === 'quests'" class="space-y-3 rounded-2xl border border-slate-100 p-4">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Edit quest (non-critical fields)</h3>
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
                        <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white disabled:opacity-50" :disabled="actionBusy" @click="saveQuestEdit(false)">Save changes now</button>
                        <button type="button" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-black text-primary-900 disabled:opacity-50" :disabled="actionBusy" @click="saveQuestEdit(true)">Submit for approval</button>
                    </div>
                </section>

                <section v-if="activeModule === 'quests' && mediaItems.length" class="space-y-3 rounded-2xl border border-slate-100 p-4">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Media</h3>
                    <article v-for="media in mediaItems" :key="media.id" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-white p-3">
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ media.name }}</p>
                            <p class="text-xs text-slate-500">{{ media.size }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a :href="media.url" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 px-2 py-1 text-[10px] font-black uppercase text-slate-700">Preview</a>
                            <button type="button" class="rounded-lg bg-rose-700 px-2 py-1 text-[10px] font-black uppercase text-white disabled:opacity-40" :disabled="actionBusy" @click="removeMedia(media)">Remove</button>
                        </div>
                    </article>
                </section>

                <section class="space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Admin status</h3>
                    <select v-model="actionForm.admin_status" class="form-input">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <textarea v-model="actionForm.reason" class="form-input min-h-24" placeholder="Reason (required)" />
                    <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white disabled:opacity-50" :disabled="actionBusy" @click="saveAdminStatus">
                        Update admin status
                    </button>
                </section>

                <section class="space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Post notice</h3>
                    <select v-model="noticeForm.type" class="form-input">
                        <option value="warning">Warning</option>
                        <option value="informational">Informational</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <textarea v-model="noticeForm.body" class="form-input min-h-20" placeholder="Notice to users" />
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input v-model="noticeForm.visible_to_users" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                        Visible to client & freelancer
                    </label>
                    <button type="button" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-black text-primary-900 disabled:opacity-50" :disabled="actionBusy" @click="postNotice">
                        Post notice
                    </button>
                </section>

                <section class="space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Internal note</h3>
                    <textarea v-model="noteForm.body" class="form-input min-h-20" placeholder="Internal note (@mention admins in text)" />
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-800 disabled:opacity-50" :disabled="actionBusy" @click="postNote">
                        Save note
                    </button>
                </section>

                <section v-if="activeModule === 'quests'" class="space-y-3 rounded-2xl border border-slate-100 p-4">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Flag quest</h3>
                    <select v-model="flagForm.type" class="form-input">
                        <option value="policy_violation">Policy violation</option>
                        <option value="suspicious_content">Suspicious content</option>
                        <option value="off_platform_solicitation">Off-platform solicitation</option>
                        <option value="fraudulent_posting">Fraudulent posting</option>
                        <option value="other">Other</option>
                    </select>
                    <select v-model="flagForm.priority" class="form-input">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                    <textarea v-model="flagForm.description" class="form-input min-h-20" placeholder="Flag description (min 30 chars)" />
                    <button type="button" class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-black text-white disabled:opacity-50" :disabled="actionBusy" @click="postQuestFlag">
                        Create flag
                    </button>
                </section>

                <section v-if="activeModule === 'proposals'" class="space-y-3 rounded-2xl border border-slate-100 p-4">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Flag proposal</h3>
                    <select v-model="proposalFlagForm.type" class="form-input">
                        <option v-for="t in proposalFlagTypes" :key="t" :value="t">{{ labelize(t) }}</option>
                    </select>
                    <select v-model="proposalFlagForm.priority" class="form-input">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                    <textarea v-model="proposalFlagForm.description" class="form-input min-h-20" placeholder="Flag description (min 30 chars)" />
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input v-model="proposalFlagForm.notify_freelancer" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                        Notify freelancer
                    </label>
                    <button type="button" class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-black text-white disabled:opacity-50" :disabled="actionBusy" @click="postProposalFlag">
                        Create flag
                    </button>
                </section>

                <section v-if="activeModule === 'proposals'" class="space-y-3 rounded-2xl border border-slate-100 p-4">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Contact freelancer</h3>
                    <input v-model="contactForm.subject" class="form-input" placeholder="Subject" />
                    <textarea v-model="contactForm.body" class="form-input min-h-20" placeholder="Message body" />
                    <select v-model="contactForm.channel" class="form-input">
                        <option value="both">Email + in-app</option>
                        <option value="email">Email only</option>
                        <option value="in_app">In-app only</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input v-model="contactForm.open_cs_ticket" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                        Also open a CS support ticket
                    </label>
                    <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white disabled:opacity-50" :disabled="actionBusy" @click="contactProposal">Send message</button>
                </section>

                <section v-if="activeModule === 'proposals'" class="space-y-3 rounded-2xl border border-slate-100 p-4">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Policy removal</h3>
                    <textarea v-model="removeForm.reason" class="form-input min-h-20" placeholder="Removal reason" />
                    <input v-model="removeForm.confirmation" class="form-input" placeholder="Type REMOVE to confirm" />
                    <button type="button" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-black text-white disabled:opacity-50" :disabled="actionBusy" @click="removeProposal">
                        Remove proposal
                    </button>
                </section>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { computed, onMounted, reactive, ref, watch } from 'vue';

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
const actionBusy = ref(false);

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
const actionMessage = ref('');

const questSummary = computed(() => {
    if (!detail.value) return '';
    if (activeModule.value === 'quests') {
        return detail.value.overview?.quest?.description_excerpt || detail.value.overview?.quest?.title || '';
    }
    return detail.value.overview?.excerpt || detail.value.content?.pitch || '';
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
        actionMessage.value = '';
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

async function saveAdminStatus() {
    if (!selectedRow.value) return;
    actionBusy.value = true;
    const url =
        activeModule.value === 'quests'
            ? route('operations.api.moderation.quests.admin-status', selectedRow.value.id)
            : route('operations.api.moderation.proposals.admin-status', selectedRow.value.id);

    try {
        await window.axios.patch(url, { ...actionForm });
        await openDetail(selectedRow.value);
        await reloadActiveQueue();
    } finally {
        actionBusy.value = false;
    }
}

async function postNotice() {
    if (!selectedRow.value) return;
    actionBusy.value = true;
    const url =
        activeModule.value === 'quests'
            ? route('operations.api.moderation.quests.notices', selectedRow.value.id)
            : route('operations.api.moderation.proposals.notices', selectedRow.value.id);

    try {
        await window.axios.post(url, { ...noticeForm });
        noticeForm.body = '';
        await openDetail(selectedRow.value);
    } finally {
        actionBusy.value = false;
    }
}

async function postNote() {
    if (!selectedRow.value) return;
    actionBusy.value = true;
    const url =
        activeModule.value === 'quests'
            ? route('operations.api.moderation.quests.notes', selectedRow.value.id)
            : route('operations.api.moderation.proposals.notes', selectedRow.value.id);

    try {
        await window.axios.post(url, { ...noteForm });
        noteForm.body = '';
        await openDetail(selectedRow.value);
    } finally {
        actionBusy.value = false;
    }
}

async function postQuestFlag() {
    if (!selectedRow.value || activeModule.value !== 'quests') return;
    actionBusy.value = true;
    try {
        const { data } = await window.axios.post(route('operations.api.moderation.quests.flags', selectedRow.value.id), { ...flagForm });
        actionMessage.value = data.message || 'Flag created.';
        flagForm.description = '';
        await openDetail(selectedRow.value);
        await reloadActiveQueue();
    } finally {
        actionBusy.value = false;
    }
}

async function postProposalFlag() {
    if (!selectedRow.value || activeModule.value !== 'proposals') return;
    actionBusy.value = true;
    try {
        const { data } = await window.axios.post(route('operations.api.moderation.proposals.flags', selectedRow.value.id), { ...proposalFlagForm });
        actionMessage.value = data.message || 'Flag created.';
        proposalFlagForm.description = '';
        await openDetail(selectedRow.value);
        await reloadActiveQueue();
    } finally {
        actionBusy.value = false;
    }
}

async function saveQuestEdit(submitForApproval) {
    if (!selectedRow.value) return;
    actionBusy.value = true;
    try {
        const { data } = await window.axios.patch(route('operations.api.moderation.quests.update', selectedRow.value.id), {
            ...editForm,
            quest_category_id: editForm.quest_category_id || null,
            submit_for_approval: submitForApproval,
        });
        actionMessage.value = data.message || 'Quest updated.';
        detail.value = data.quest ?? detail.value;
        if (!submitForApproval) {
            await openDetail(selectedRow.value);
        }
    } finally {
        actionBusy.value = false;
    }
}

async function removeMedia(media) {
    if (!selectedRow.value || !media?.id) return;
    const reason = window.prompt('Why is this media being removed? (min 10 characters)');
    if (!reason || reason.length < 10) return;

    actionBusy.value = true;
    try {
        const { data } = await window.axios.delete(route('operations.api.moderation.quests.files.destroy', [selectedRow.value.id, media.id]), {
            data: { reason },
        });
        actionMessage.value = data.message || 'Media removed.';
        detail.value = data.quest ?? detail.value;
    } finally {
        actionBusy.value = false;
    }
}

async function contactQuest(recipient) {
    if (!selectedRow.value) return;
    contactForm.recipient = recipient;
    actionBusy.value = true;
    try {
        const { data } = await window.axios.post(route('operations.api.moderation.quests.contact', selectedRow.value.id), { ...contactForm, recipient });
        actionMessage.value = data.message || 'Message sent.';
        contactForm.body = '';
    } finally {
        actionBusy.value = false;
    }
}

async function contactProposal() {
    if (!selectedRow.value) return;
    actionBusy.value = true;
    try {
        const { data } = await window.axios.post(route('operations.api.moderation.proposals.contact', selectedRow.value.id), contactForm);
        actionMessage.value = data.message || 'Message sent.';
        contactForm.body = '';
    } finally {
        actionBusy.value = false;
    }
}

async function removeProposal() {
    if (!selectedRow.value || activeModule.value !== 'proposals') return;
    actionBusy.value = true;
    try {
        await window.axios.delete(route('operations.api.moderation.proposals.remove', selectedRow.value.id), { data: { ...removeForm } });
        slideOpen.value = false;
        await reloadActiveQueue();
    } finally {
        actionBusy.value = false;
    }
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
