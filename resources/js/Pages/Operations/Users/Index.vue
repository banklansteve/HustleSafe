<template>
    <OperationsShell title="Users" subtitle="Full profile review, notes, warnings, tags, messaging, and 72-hour suspensions for member accounts.">
        <div class="mb-4 flex justify-end">
            <a :href="exportUrl" class="inline-flex rounded-xl bg-primary-700 px-4 py-2 text-sm font-black uppercase tracking-wide text-white shadow-md hover:bg-primary-800">Export CSV</a>
        </div>

        <OperationsQueueTable
            :columns="columns"
            :rows="queue.pageItems.value"
            v-model:search="queue.search.value"
            v-model:per-page="queue.perPage.value"
            :page="queue.page.value"
            :total="queue.total.value"
            :total-pages="queue.totalPages.value"
            :sort-key="queue.sortKey.value"
            :sort-dir="queue.sortDir.value"
            search-placeholder="Search loaded users (name, email, role, trust)…"
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openUser"
        >
            <template #cell-name="{ row }">
                <span class="font-semibold text-slate-950">{{ row.name }}</span>
                <span class="mt-0.5 block text-xs text-slate-500">{{ row.email }}</span>
            </template>
            <template #cell-account_status="{ row }">
                <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-700">{{ row.account_status }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white shadow-sm" @click.stop="openUser(row)">Manage</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="selected?.name || ''" :subtitle="selected?.email" eyebrow="User profile" @close="slideOpen = false">
            <div v-if="!profile && profileLoading" class="py-8 text-center text-sm font-semibold text-slate-500">Loading profile…</div>
            <div v-else-if="profile" class="space-y-4">
                <div class="flex flex-wrap gap-2 border-b border-slate-100 pb-3">
                    <button
                        v-for="t in profileTabs"
                        :key="t.key"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-black uppercase transition"
                        :class="profileTab === t.key ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700 hover:bg-primary-50'"
                        :disabled="tabLoading && profileTab !== t.key"
                        @click="loadProfileTab(t.key)"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <div v-if="tabLoading" class="flex items-center justify-center gap-2 rounded-2xl border border-slate-100 bg-white py-10 text-sm font-semibold text-slate-500">
                    <span class="h-5 w-5 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
                    Loading {{ activeTabLabel }}…
                </div>

                <template v-else>
                    <!-- Overview -->
                    <template v-if="profileTab === 'overview'">
                        <OperationsContextStats :heading="selected?.name" :stats="userStats" :chips="userChips" :links="userLinks" />

                        <section v-if="loginSummary" class="rounded-2xl border border-slate-100 bg-white px-4 py-3 text-sm ring-1 ring-slate-100">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Last login</p>
                            <p class="mt-1 font-bold text-slate-900">{{ loginSummary.when }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-600">{{ loginSummary.device }}</p>
                            <p v-if="loginSummary.ip" class="mt-0.5 text-xs text-slate-500">IP {{ loginSummary.ip }}</p>
                        </section>

                        <section class="rounded-2xl border border-slate-100 bg-white p-4 ring-1 ring-slate-100">
                            <h4 class="text-sm font-black text-slate-950">Trust breakdown</h4>
                            <div class="mt-3 space-y-3">
                                <div v-for="item in profile.overview?.trust ?? []" :key="item.label">
                                    <div class="flex justify-between text-xs font-black text-slate-600">
                                        <span>{{ item.label }} · {{ item.weight }}%</span>
                                        <span>{{ item.score }}/100</span>
                                    </div>
                                    <div class="mt-1 h-2 rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-primary-600 transition-all" :style="{ width: `${item.score}%` }" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <OperationsListBlock title="Verification checks" :items="verificationItems" empty="No verification records yet." />
                        <OperationsListBlock title="Sanctions history" :items="sanctionItems" empty="No sanctions on this account." />

                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Staff actions</p>

                        <OperationsExpandableAction title="Account tags" hint="Label the account for queue routing." icon="🏷" tone="slate" submit-label="Save tags" :busy="busy.tags" @submit="saveTags">
                            <div class="flex flex-wrap gap-2">
                                <label v-for="tag in tags" :key="tag.id" class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold" :class="selectedTagIds.includes(tag.id) ? 'border-primary-600 bg-primary-50 text-primary-900' : 'border-slate-200 bg-white text-slate-700'">
                                    <input v-model="selectedTagIds" type="checkbox" class="rounded border-slate-300 text-primary-600" :value="tag.id" />
                                    <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: tag.color || '#94a3b8' }" />
                                    {{ tag.name }}
                                </label>
                            </div>
                        </OperationsExpandableAction>

                        <OperationsExpandableAction title="Send email" hint="Email notification to the member." icon="✉" tone="sky" submit-label="Send email" :busy="busy.message" @submit="sendMessage">
                            <input v-model="messageForm.subject" class="form-input" placeholder="Subject" />
                            <textarea v-model="messageForm.body" class="form-input mt-3 min-h-24" placeholder="Email body" />
                        </OperationsExpandableAction>

                        <OperationsExpandableAction title="Issue warning" hint="Recorded without suspending access." icon="⚠" tone="amber" submit-label="Issue warning" :busy="busy.warning" @submit="issueWarning">
                            <select v-model="warningForm.reason_code" class="form-input">
                                <option value="policy_violation">Policy violation</option>
                                <option value="fraud_risk">Fraud risk</option>
                                <option value="abuse_or_harassment">Abuse / harassment</option>
                            </select>
                            <textarea v-model="warningForm.notes" class="form-input mt-3 min-h-20" placeholder="Warning details" />
                        </OperationsExpandableAction>

                        <OperationsExpandableAction title="Suspension" :hint="sidebar?.history?.currently_suspended ? 'Currently suspended' : 'Up to 72 hours'" icon="⏸" tone="rose" submit-label="Suspend up to 72h" secondary-label="Clear suspension" :busy="busy.suspend" @submit="suspendUser" @secondary="unsuspendUser">
                            <p class="text-sm font-semibold text-slate-600">Super Admin approval is required beyond 72 hours.</p>
                        </OperationsExpandableAction>

                        <OperationsExpandableAction title="Flag for Super Admin" hint="Marks account for senior review." icon="🚩" submit-label="Flag account" :busy="busy.flag" @submit="flagUser">
                            <textarea v-model="flagForm.reason" class="form-input min-h-20" placeholder="Reason for review…" />
                        </OperationsExpandableAction>
                    </template>

                    <!-- Activity -->
                    <template v-else-if="profileTab === 'activity'">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input v-model="tabSearch" type="search" placeholder="Search activity…" class="form-input" />
                            <select v-model="tabCategory" class="form-input">
                                <option value="">All event types</option>
                                <option value="admin">Admin</option>
                                <option value="users">Users</option>
                                <option value="quests">Quests</option>
                                <option value="financial">Financial</option>
                                <option value="disputes">Disputes</option>
                            </select>
                        </div>
                        <OperationsListBlock title="Activity feed" :items="filteredTabItems" empty="No activity recorded for this member yet." />
                    </template>

                    <!-- Contracts -->
                    <template v-else-if="profileTab === 'contracts'">
                        <OperationsListBlock title="Quests & contracts" :items="contractItems" empty="No quests linked to this account yet." />
                    </template>

                    <!-- Disputes -->
                    <template v-else-if="profileTab === 'disputes'">
                        <OperationsListBlock title="Disputes" :items="disputeItems" empty="No disputes involving this member." />
                    </template>

                    <!-- Reviews -->
                    <template v-else-if="profileTab === 'reviews'">
                        <OperationsListBlock title="Reviews" :items="reviewItems" empty="No reviews given or received yet." />
                    </template>

                    <!-- Notes -->
                    <template v-else-if="profileTab === 'notes'">
                        <OperationsExpandableAction
                            title="Add internal note"
                            hint="Visible to staff admins on this account."
                            icon="📝"
                            submit-label="Save note"
                            :busy="busy.note"
                            default-open
                            @submit="saveNote"
                        >
                            <textarea v-model="noteForm.body" class="form-input min-h-24" placeholder="Note for the admin team…" />
                        </OperationsExpandableAction>
                        <OperationsListBlock title="Admin notes" :items="noteItems" empty="No admin notes yet." />
                    </template>
                </template>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import OperationsContextStats from '@/Pages/Operations/Components/OperationsContextStats.vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsListBlock from '@/Pages/Operations/Components/OperationsListBlock.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsToast } from '@/composables/useOperationsToast';
import { computed, onMounted, reactive, ref, toRef } from 'vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
});

const { toast } = useOperationsToast();

const profileTabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'activity', label: 'Activity' },
    { key: 'contracts', label: 'Contracts' },
    { key: 'disputes', label: 'Disputes' },
    { key: 'reviews', label: 'Reviews' },
    { key: 'notes', label: 'Notes' },
];

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'name', label: 'User', sortable: true },
    { key: 'role', label: 'Role', sortable: true, path: 'role_label' },
    { key: 'trust_score', label: 'Trust', sortable: true },
    { key: 'account_status', label: 'Status', sortable: true },
];

const queue = useClientQueue(toRef(props, 'users'), {
    defaultSortKey: 'id',
    searchFields: ['id', 'name', 'email', 'role_label', 'account_status', 'trust_score'],
});

const exportUrl = computed(() => route('operations.users.export'));
const slideOpen = ref(false);
const selected = ref(null);
const profile = ref(null);
const profileLoading = ref(false);
const tabLoading = ref(false);
const profileTab = ref('overview');
const tabSearch = ref('');
const tabCategory = ref('');

const noteForm = reactive({ body: '' });
const messageForm = reactive({ subject: '', body: '' });
const warningForm = reactive({ reason_code: 'policy_violation', notes: '' });
const flagForm = reactive({ reason: '' });
const selectedTagIds = ref([]);
const busy = reactive({ tags: false, note: false, message: false, warning: false, suspend: false, flag: false });

const sidebar = computed(() => profile.value?.operations_sidebar ?? null);
const overviewUser = computed(() => profile.value?.overview?.user ?? null);
const overviewProfile = computed(() => profile.value?.overview?.profile ?? null);
const activeTabLabel = computed(() => profileTabs.find((t) => t.key === profileTab.value)?.label ?? profileTab.value);

const tabData = computed(() => (Array.isArray(profile.value?.tabData) ? profile.value.tabData : []));

const filteredTabItems = computed(() => {
    const q = tabSearch.value.trim().toLowerCase();
    return tabData.value.filter((item) => {
        const matchesSearch = !q || JSON.stringify(item).toLowerCase().includes(q);
        const matchesCategory = !tabCategory.value || item.category === tabCategory.value || item.type === tabCategory.value;
        return matchesSearch && matchesCategory;
    });
});

const verificationItems = computed(() =>
    (profile.value?.overview?.verification ?? []).map((v) => ({
        id: v.id,
        title: `${labelize(v.category)} · ${labelize(v.status)}`,
        summary: v.rejection_reason || (v.reviewer ? `Reviewed by ${v.reviewer}` : ''),
        status: v.status,
        created_at: v.submitted_at,
    })),
);

const sanctionItems = computed(() =>
    (profile.value?.overview?.sanctions ?? []).map((s) => ({
        id: s.id,
        title: labelize(s.type),
        summary: s.notes,
        type: s.reason_code,
        admin: s.admin,
        created_at: s.starts_at,
    })),
);

const contractItems = computed(() =>
    tabData.value.map((q) => ({
        ...q,
        title: q.title,
        summary: [q.client, q.freelancer].filter(Boolean).join(' · '),
        href: q.id ? route('operations.moderation.index', { module: 'quests' }) : undefined,
    })),
);

const disputeItems = computed(() =>
    tabData.value.map((d) => ({
        ...d,
        title: d.quest || `Dispute #${d.id}`,
        summary: d.amount,
        href: route('operations.disputes.index', { q: d.uuid || d.id }),
    })),
);

const reviewItems = computed(() =>
    tabData.value.map((r) => ({
        ...r,
        title: `${r.direction === 'given' ? 'Given to' : 'Received from'} ${r.direction === 'given' ? r.reviewee : r.reviewer}`,
        summary: r.content,
    })),
);

const noteItems = computed(() =>
    tabData.value.map((n) => ({
        ...n,
        title: n.admin ? `Note by ${n.admin}` : 'Staff note',
        summary: n.body,
    })),
);

const userStats = computed(() => {
    const sb = sidebar.value;
    const user = overviewUser.value;
    if (!sb || !user) return [];

    return [
        { label: 'Member for', value: sb.tenure?.label || '—', hint: sb.tenure?.years ? `${sb.tenure.years} yrs` : '' },
        { label: 'Account type', value: sb.role_label || user.role_label },
        { label: sb.activity_metric_label, value: String(sb.activity_metric_count ?? 0) },
        { label: 'Status', value: sb.account_status || user.account_status },
        { label: 'Trust', value: String(sb.trust_score ?? user.trust_score ?? '—') },
        { label: 'Location', value: sb.location || '—', hint: (sb.categories || []).slice(0, 2).join(', ') || undefined },
    ];
});

const userChips = computed(() => {
    const sb = sidebar.value;
    const history = sb?.history;
    const pending = sb?.pending;
    const chips = [];

    if (history?.ever_banned) chips.push({ label: 'Banned before', tone: 'danger' });
    if (history?.ever_suspended) chips.push({ label: 'Suspended before', tone: 'warn' });
    if (history?.currently_suspended) chips.push({ label: 'Suspended now', tone: 'danger' });
    if ((history?.warnings_count ?? 0) > 0) chips.push({ label: `${history.warnings_count} warning(s)`, tone: 'warn' });
    if (pending?.under_review) chips.push({ label: 'Under review', tone: 'warn' });
    if (pending?.is_flagged) chips.push({ label: 'Flagged', tone: 'danger' });
    if ((pending?.open_disputes ?? 0) > 0) chips.push({ label: `${pending.open_disputes} dispute(s)`, tone: 'warn' });
    if ((pending?.open_support_tickets ?? 0) > 0) chips.push({ label: `${pending.open_support_tickets} open ticket(s)`, tone: 'warn' });

    return chips;
});

const userLinks = computed(() => {
    const links = [...(sidebar.value?.links ?? [])];
    const email = selected.value?.email;
    if (email) {
        links.unshift({
            label: 'Support hub search',
            title: email,
            preview: 'Find tickets, chats, and related cases',
            href: route('operations.support.index', { q: email }),
        });
    }
    return links;
});

const loginSummary = computed(() => {
    const p = overviewProfile.value;
    if (!p?.last_login_at) return null;
    return {
        when: formatDateTime(p.last_login_at),
        device: truncateDevice(p.last_login_device),
        ip: p.last_login_ip,
    };
});

onMounted(() => {
    const q = new URLSearchParams(window.location.search).get('q');
    if (q) queue.search.value = q;
});

async function openUser(row) {
    selected.value = row;
    slideOpen.value = true;
    profileTab.value = 'overview';
    tabSearch.value = '';
    tabCategory.value = '';
    await loadProfileTab('overview', true);
}

async function loadProfileTab(tab, initial = false) {
    if (!selected.value) return;
    profileTab.value = tab;
    if (initial) profileLoading.value = true;
    else tabLoading.value = true;

    try {
        const { data } = await window.axios.get(route('operations.api.users.profile', selected.value.id), { params: { tab } });
        profile.value = data;
        if (tab === 'overview') {
            selectedTagIds.value = (data.overview?.user?.tags ?? []).map((t) => t.id);
        }
    } catch (error) {
        toast(extractError(error), 'error');
    } finally {
        profileLoading.value = false;
        tabLoading.value = false;
    }
}

async function runAction(key, request, successMessage, after) {
    busy[key] = true;
    try {
        await request();
        toast(successMessage);
        if (after) await after();
    } catch (error) {
        toast(extractError(error), 'error');
    } finally {
        busy[key] = false;
    }
}

function saveTags() {
    return runAction('tags', () => window.axios.patch(route('operations.api.users.tags', selected.value.id), { tag_ids: selectedTagIds.value }), 'Tags updated.', () => loadProfileTab('overview'));
}

function saveNote() {
    return runAction('note', async () => {
        await window.axios.post(route('operations.api.users.notes', selected.value.id), noteForm);
        noteForm.body = '';
    }, 'Note saved.', () => loadProfileTab('notes'));
}

function sendMessage() {
    return runAction('message', async () => {
        await window.axios.post(route('operations.api.users.message', selected.value.id), messageForm);
        messageForm.subject = '';
        messageForm.body = '';
    }, 'Email sent.');
}

function issueWarning() {
    return runAction('warning', () => window.axios.post(route('operations.api.users.warnings', selected.value.id), warningForm), 'Warning recorded.', () => loadProfileTab('overview'));
}

function suspendUser() {
    return runAction('suspend', () => window.axios.post(route('operations.api.users.suspend', selected.value.id), { reason_code: 'policy_violation' }), 'User suspended.', () => loadProfileTab('overview'));
}

function unsuspendUser() {
    return runAction('suspend', () => window.axios.post(route('operations.api.users.unsuspend', selected.value.id)), 'Suspension cleared.', () => loadProfileTab('overview'));
}

function flagUser() {
    return runAction('flag', async () => {
        await window.axios.post(route('operations.api.users.flag', selected.value.id), flagForm);
        flagForm.reason = '';
    }, 'Account flagged for Super Admin.', () => loadProfileTab('overview'));
}

function extractError(error) {
    return error?.response?.data?.message || error?.response?.data?.errors?.user?.[0] || 'Something went wrong.';
}

function formatDateTime(value) {
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

function truncateDevice(ua) {
    if (!ua) return 'Unknown device';
    return ua.length > 90 ? `${ua.slice(0, 90)}…` : ua;
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
