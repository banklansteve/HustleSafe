<template>
    <AdminShell
        title="Live Activity"
        subtitle="A real-time operational window into platform events, risks, disputes, and financial activity."
    >
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="tile in summaryTiles" :key="tile.label" class="rounded-2xl border p-4" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">{{ tile.label }}</p>
                <p class="mt-2 text-2xl font-black" :class="shell.cardTitle">{{ tile.value }}</p>
                <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ tile.hint }}</p>
            </div>
        </div>

        <AdminPanel eyebrow="Real-time stream" title="Platform event feed">
            <LiveActivityFeed
                :events="filteredEvents"
                :active-category="category"
                :search="search"
                :paused="paused"
                :new-count="pendingEvents.length"
                :has-more="hasMore"
                :shell="shell"
                @update:category="setCategory"
                @update:search="setSearch"
                @toggle-pause="paused = !paused"
                @reveal-new="revealNew"
                @load-more="loadMore"
                @inspect="inspectEntity"
                @action="runAction"
            />
        </AdminPanel>

        <AdminSlideOver :open="slideOpen" :title="slideTitle" eyebrow="Entity preview" @close="slideOpen = false">
            <div class="space-y-3">
                <p class="text-sm font-semibold" :class="shell.cardMuted">{{ slideBody }}</p>
                <dl v-if="slideRecord" class="grid gap-2 text-sm sm:grid-cols-2">
                    <div v-for="column in slideColumns" :key="column" class="rounded-xl border p-3" :class="shell.card">
                        <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">{{ column.replace(/_/g, ' ') }}</dt>
                        <dd class="mt-1 break-words font-semibold" :class="shell.cardTitle">{{ displayValue(slideRecord[column]) }}</dd>
                    </div>
                </dl>
                <a v-if="slideHref" :href="slideHref" class="inline-flex rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary">
                    Open full record
                </a>
            </div>
        </AdminSlideOver>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import LiveActivityFeed from '@/Components/Admin/LiveActivityFeed.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    initial_events: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const { shell } = useInjectedAdminTheme();
const events = ref(props.initial_events.data || []);
const page = ref(props.initial_events.current_page || 1);
const lastPage = ref(props.initial_events.last_page || 1);
const summary = ref(props.summary);
const category = ref(sessionStorage.getItem('admin.liveActivity.category') || 'all');
const search = ref(sessionStorage.getItem('admin.liveActivity.search') || '');
const paused = ref(false);
const pendingEvents = ref([]);
const slideOpen = ref(false);
const slideTitle = ref('');
const slideBody = ref('');
const slideHref = ref('');
const slideRecord = ref(null);
const slideColumns = ref([]);
let searchTimer;
let summaryTimer;

const hasMore = computed(() => page.value < lastPage.value);
const filteredEvents = computed(() => {
    const term = search.value.trim().toLowerCase();

    return events.value.filter((event) => {
        const categoryMatch = category.value === 'all' || event.category === category.value;
        const searchMatch = term === '' || JSON.stringify(event).toLowerCase().includes(term);

        return categoryMatch && searchMatch;
    });
});
const summaryTiles = computed(() => [
    { label: 'Events in 24h', value: summary.value.events_24h, hint: 'Rolling event volume' },
    { label: 'Open disputes', value: summary.value.open_disputes, hint: 'Needs operational attention' },
    { label: 'Transactions today', value: `${summary.value.transactions_today?.count || 0} · ₦${(Number(summary.value.transactions_today?.value || 0) / 100).toLocaleString()}`, hint: 'Processed today' },
    { label: 'New signups today', value: summary.value.new_signups_today, hint: 'Acquisition today' },
]);

function setCategory(value) {
    category.value = value;
    sessionStorage.setItem('admin.liveActivity.category', value);
}

function setSearch(value) {
    search.value = value;
    sessionStorage.setItem('admin.liveActivity.search', value);
}

async function reload() {
    const { data } = await window.axios.get(route('admin.live-activity.events'), { params: params(1, 200) });
    events.value = data.data || [];
    page.value = data.current_page || 1;
    lastPage.value = data.last_page || 1;
}

async function loadMore() {
    if (!hasMore.value) return;
    const next = page.value + 1;
    const { data } = await window.axios.get(route('admin.live-activity.events'), { params: params(next, 50) });
    events.value = [...events.value, ...(data.data || [])];
    page.value = data.current_page || next;
    lastPage.value = data.last_page || lastPage.value;
}

function params(nextPage, perPage) {
    return {
        page: nextPage,
        per_page: perPage,
    };
}

function revealNew() {
    events.value = [...pendingEvents.value.reverse(), ...events.value].slice(0, 200);
    pendingEvents.value = [];
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function addRealtimeEvent(event) {
    if (paused.value || window.scrollY > 240) {
        pendingEvents.value.unshift(event);
    } else {
        events.value = [event, ...events.value].slice(0, 200);
    }
}

async function inspectEntity(part) {
    slideTitle.value = part.text;
    slideBody.value = 'Loading full record details...';
    slideHref.value = part.href || '';
    slideRecord.value = null;
    slideColumns.value = [];
    slideOpen.value = true;

    if (!part.entity?.type || !part.entity?.id) {
        slideBody.value = 'No linked record is available for this event.';
        return;
    }

    const { data } = await window.axios.get(route('admin.live-activity.entity'), {
        params: { type: part.entity.type, id: part.entity.id },
    });
    slideTitle.value = `${data.label}: ${part.text}`;
    slideBody.value = 'Full detail preview. Use Open full record to edit or manage this item.';
    slideRecord.value = data.record;
    slideColumns.value = data.columns;
    slideHref.value = data.href;
}

async function runAction({ event, action }) {
    if (action.method === 'open') {
        const first = event.entities?.find((entity) => entity.href);
        if (first) inspectEntity({ text: first.label, href: first.href, entity: first });
        return;
    }

    await window.axios.post(route('admin.live-activity.action', event.id), { action: action.key });
    await reload();
}

async function refreshSummary() {
    const { data } = await window.axios.get(route('admin.live-activity.summary'));
    summary.value = data;
}

function displayValue(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    if (typeof value === 'object' && value.label) {
        return value.label;
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return value;
}

onMounted(() => {
    refreshSummary();
    summaryTimer = setInterval(refreshSummary, 60000);

    if (window.Echo) {
        window.Echo.private('admin.live-activity')
            .listen('.event.created', (payload) => {
                if (!payload?.event) return;
                addRealtimeEvent(payload.event);
                refreshSummary();
            });
    }
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearInterval(summaryTimer);
    if (window.Echo) {
        window.Echo.leave('private-admin.live-activity');
    }
});
</script>
