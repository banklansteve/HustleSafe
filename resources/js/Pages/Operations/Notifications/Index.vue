<template>
    <OperationsShell title="Alert centre" subtitle="Your private inbox — assignments, escalations, KYC, chats, and overdue reminders.">
        <section v-if="criticalBanners.length" class="mb-4 space-y-2">
            <article v-for="banner in criticalBanners" :key="banner.id" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 ring-1 ring-rose-100">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wide text-rose-800">Critical · action required</p>
                        <p class="mt-1 font-display text-base font-black text-rose-950">{{ banner.title }}</p>
                        <p class="mt-1 text-sm font-semibold text-rose-900">{{ banner.body }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="rounded-xl bg-primary-700 px-3 py-2 text-xs font-black text-white" @click="followNotification(banner)">{{ banner.action_label || 'Open' }}</button>
                        <button type="button" class="rounded-xl border border-rose-300 bg-white px-3 py-2 text-xs font-black text-rose-900" :disabled="busy[`action-${banner.id}`]" @click="markActioned(banner)">Dismiss</button>
                    </div>
                </div>
            </article>
        </section>

        <div class="mb-4 flex flex-wrap gap-2">
            <button v-for="tab in filterTabs" :key="tab.key" type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="activeFilter === tab.key ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700'" @click="setFilter(tab.key)">{{ tab.label }}</button>
            <button type="button" class="ml-auto rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase text-slate-700" @click="showPrefs = !showPrefs">Preferences</button>
        </div>

        <section v-if="showPrefs" class="mb-4 rounded-2xl border border-primary-100 bg-primary-50/40 p-4">
            <p class="text-xs font-black uppercase text-primary-800">Delivery preferences</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label v-for="(pref, key) in prefEvents" :key="key" class="rounded-xl border border-slate-100 bg-white p-3 text-sm">
                    <span class="font-bold text-slate-900">{{ pref.label }}</span>
                    <div class="mt-2 flex gap-4">
                        <label class="flex items-center gap-2 text-xs font-semibold"><input v-model="pref.in_app" type="checkbox" class="rounded text-primary-600" /> In-app</label>
                        <label class="flex items-center gap-2 text-xs font-semibold"><input v-model="pref.email" type="checkbox" class="rounded text-primary-600" /> Email</label>
                    </div>
                </label>
            </div>
            <button type="button" class="mt-3 rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white" :disabled="busy.prefs" @click="savePrefs">Save preferences</button>
        </section>

        <div class="mb-3 flex gap-2 overflow-x-auto pb-1">
            <button v-for="cat in categories" :key="cat.key" type="button" class="shrink-0 rounded-xl px-3 py-2 text-xs font-black uppercase" :class="activeCategory === cat.key ? 'bg-primary-100 text-primary-900 ring-1 ring-primary-200' : 'bg-white text-slate-600 ring-1 ring-slate-200'" @click="setCategory(cat.key)">{{ cat.label }}</button>
        </div>

        <OperationsQueueTable
            :columns="columns"
            :rows="queue.pageItems.value"
            :loading="loading"
            v-model:search="queue.search.value"
            v-model:per-page="queue.perPage.value"
            :page="queue.page.value"
            :total="queue.total.value"
            :total-pages="queue.totalPages.value"
            :sort-key="queue.sortKey.value"
            :sort-dir="queue.sortDir.value"
            empty-message="Inbox is clear."
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="followNotification"
        >
            <template #cell-title="{ row }">
                <span class="font-semibold text-slate-950">{{ row.title }}</span>
                <span v-if="!row.read_at" class="ml-2 inline-block h-2 w-2 rounded-full bg-primary-600" />
            </template>
            <template #cell-priority="{ row }">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="row.priority === 'critical' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'">{{ row.priority }}</span>
            </template>
            <template #cell-created_at="{ row }">
                <span class="text-sm font-semibold text-slate-600" :title="row.created_at">{{ formatHumanDateTime(row.created_at) }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="followNotification(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="selected?.title || 'Alert'" subtitle="Notification detail" eyebrow="Alerts" @close="slideOpen = false">
            <div v-if="selected" class="space-y-4">
                <p class="text-sm font-semibold text-slate-700">{{ selected.body }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="selected.action_url"
                        type="button"
                        class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white"
                        @click="followActionUrl(selected.action_url)"
                    >
                        {{ selected.action_label || 'Take action' }}
                    </button>
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black" :disabled="busy.read" @click="markRead(selected)">Mark read</button>
                    <button type="button" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-black text-rose-900" :disabled="busy.action" @click="markActioned(selected)">Mark actioned</button>
                </div>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { useStaffNotificationVisit } from '@/composables/useStaffNotificationVisit';
import { router } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';

const { busyId, visit: visitStaffAlert } = useStaffNotificationVisit('operations.notifications.open');
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';
import { formatHumanDateTime } from '@/utils/formatHumanDateTime';

const columns = [
    { key: 'title', label: 'Alert' },
    { key: 'category', label: 'Category' },
    { key: 'priority', label: 'Priority' },
    { key: 'created_at', label: 'When' },
];

const filterTabs = [
    { key: 'inbox', label: 'Inbox' },
    { key: 'unread', label: 'Unread' },
    { key: 'critical', label: 'Critical' },
];

const rawItems = ref([]);
const criticalBanners = ref([]);
const categories = ref([{ key: '', label: 'All' }]);
const loading = ref(false);
const activeFilter = ref('inbox');
const activeCategory = ref('');
const showPrefs = ref(false);
const prefEvents = reactive({});
const slideOpen = ref(false);
const selected = ref(null);

const queue = useClientQueue(() => rawItems.value, { searchFields: ['title', 'body', 'category'] });
const { busy, runAction } = useOperationsAction();

onMounted(() => {
    reload();
    loadPrefs();
});

function setFilter(key) {
    activeFilter.value = key;
    reload();
}

function setCategory(key) {
    activeCategory.value = key;
    reload();
}

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.notifications.listing'), {
            params: { filter: activeFilter.value, category: activeCategory.value || undefined },
        });
        rawItems.value = data.items ?? [];
        criticalBanners.value = data.critical_banners ?? [];
        categories.value = [{ key: '', label: 'All' }, ...(data.categories ?? [])];
    } finally {
        loading.value = false;
    }
}

async function loadPrefs() {
    const { data } = await window.axios.get(route('operations.api.notifications.preferences'));
    Object.keys(data.events ?? {}).forEach((key) => {
        prefEvents[key] = { ...data.events[key] };
    });
}

async function savePrefs() {
    await runAction('prefs', () => window.axios.patch(route('operations.api.notifications.preferences.update'), { preferences: prefEvents }), 'Preferences saved.');
}

function followNotification(row) {
    if (!row?.id) {
        return;
    }
    void visitStaffAlert(row.id);
}

function followActionUrl(url) {
    if (!url) {
        return;
    }
    slideOpen.value = false;
    router.visit(url, { preserveScroll: true, preserveState: true });
}

function openItem(row) {
    selected.value = row;
    slideOpen.value = true;
}

async function markRead(row) {
    await runAction('read', () => window.axios.patch(route('operations.api.notifications.read', row.id)), 'Marked read.', reload);
}

async function markActioned(row) {
    await runAction(`action-${row.id}`, () => window.axios.patch(route('operations.api.notifications.actioned', row.id)), 'Actioned.', () => {
        slideOpen.value = false;
        reload();
    });
}
</script>
