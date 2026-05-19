<template>
    <OperationsShell title="Users" subtitle="Full profile review, notes, warnings, tags, messaging, and 72-hour suspensions for member accounts.">
        <div class="mb-4 flex justify-end">
            <a :href="exportUrl" class="inline-flex rounded-xl bg-primary-700 px-4 py-2 text-sm font-black uppercase tracking-wide text-white hover:bg-primary-800">Export CSV</a>
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
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openUser(row)">Manage</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="selected?.name || ''" :subtitle="selected?.email" eyebrow="User profile" @close="slideOpen = false">
            <div v-if="profileLoading" class="py-8 text-center text-sm font-semibold text-slate-500">Loading profile…</div>
            <div v-else-if="profile" class="space-y-5">
                <p v-if="profileMessage" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-900">{{ profileMessage }}</p>
                <div class="flex flex-wrap gap-2">
                    <button v-for="t in profileTabs" :key="t" type="button" class="rounded-lg px-3 py-1.5 text-xs font-black uppercase" :class="profileTab === t ? 'bg-primary-700 text-white' : 'border border-slate-200 text-slate-700'" @click="loadProfileTab(t)">
                        {{ t }}
                    </button>
                </div>

                <section class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm font-semibold text-slate-700">
                    Trust {{ profile.overview?.user?.trust_score }} · {{ profile.overview?.user?.role_label }}
                </section>

                <section class="space-y-3 rounded-2xl border border-slate-100 p-4">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Account tags</h3>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="tag in tags"
                            :key="tag.id"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold"
                            :class="selectedTagIds.includes(tag.id) ? 'border-primary-600 bg-primary-50 text-primary-900' : 'border-slate-200 bg-white text-slate-700'"
                        >
                            <input v-model="selectedTagIds" type="checkbox" class="rounded border-slate-300 text-primary-600" :value="tag.id" />
                            <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: tag.color || '#94a3b8' }" />
                            {{ tag.name }}
                        </label>
                    </div>
                    <p v-if="!tags.length" class="text-sm text-slate-500">No tags configured yet.</p>
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-800" @click="saveTags">Save tags</button>
                </section>

                <section class="space-y-2">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Internal note</h3>
                    <textarea v-model="noteForm.body" class="form-input min-h-20" />
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-800" @click="saveNote">Save note</button>
                </section>

                <section class="space-y-2">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Send message</h3>
                    <input v-model="messageForm.subject" class="form-input" placeholder="Subject" />
                    <textarea v-model="messageForm.body" class="form-input min-h-20" placeholder="Email body" />
                    <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white" @click="sendMessage">Send email</button>
                </section>

                <section class="space-y-2">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Warning</h3>
                    <select v-model="warningForm.reason_code" class="form-input">
                        <option value="policy_violation">Policy violation</option>
                        <option value="fraud_risk">Fraud risk</option>
                        <option value="abuse_or_harassment">Abuse / harassment</option>
                    </select>
                    <textarea v-model="warningForm.notes" class="form-input min-h-16" />
                    <button type="button" class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-black text-white" @click="issueWarning">Issue warning</button>
                </section>

                <section class="space-y-2">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">72-hour suspension</h3>
                    <button type="button" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-black text-white" @click="suspendUser">Suspend up to 72h</button>
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-800" @click="unsuspendUser">Clear suspension</button>
                </section>

                <section class="space-y-2">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Flag for Super Admin</h3>
                    <textarea v-model="flagForm.reason" class="form-input min-h-16" />
                    <button type="button" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-black text-primary-900" @click="flagUser">Flag account</button>
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
import { computed, reactive, ref, toRef } from 'vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
});

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
const profileTab = ref('overview');
const profileTabs = ['overview', 'activity', 'contracts', 'disputes', 'reviews', 'notes'];

const noteForm = reactive({ body: '' });
const messageForm = reactive({ subject: '', body: '' });
const warningForm = reactive({ reason_code: 'policy_violation', notes: '' });
const flagForm = reactive({ reason: '' });
const selectedTagIds = ref([]);
const profileMessage = ref('');

async function openUser(row) {
    selected.value = row;
    slideOpen.value = true;
    await loadProfileTab('overview');
}

async function loadProfileTab(tab) {
    if (!selected.value) return;
    profileTab.value = tab;
    profileLoading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.users.profile', selected.value.id), { params: { tab } });
        profile.value = data;
        if (tab === 'overview') {
            selectedTagIds.value = (data.overview?.user?.tags ?? []).map((t) => t.id);
        }
    } finally {
        profileLoading.value = false;
    }
}

async function saveTags() {
    await window.axios.patch(route('operations.api.users.tags', selected.value.id), { tag_ids: selectedTagIds.value });
    profileMessage.value = 'Tags updated.';
    await loadProfileTab('overview');
}

async function saveNote() {
    await window.axios.post(route('operations.api.users.notes', selected.value.id), noteForm);
    noteForm.body = '';
    await loadProfileTab('notes');
}

async function sendMessage() {
    await window.axios.post(route('operations.api.users.message', selected.value.id), messageForm);
    messageForm.subject = '';
    messageForm.body = '';
}

async function issueWarning() {
    await window.axios.post(route('operations.api.users.warnings', selected.value.id), warningForm);
}

async function suspendUser() {
    await window.axios.post(route('operations.api.users.suspend', selected.value.id), { reason_code: 'policy_violation' });
}

async function unsuspendUser() {
    await window.axios.post(route('operations.api.users.unsuspend', selected.value.id));
}

async function flagUser() {
    await window.axios.post(route('operations.api.users.flag', selected.value.id), flagForm);
    flagForm.reason = '';
}
</script>

<style scoped>
.form-input {
    @apply w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100;
}
</style>
