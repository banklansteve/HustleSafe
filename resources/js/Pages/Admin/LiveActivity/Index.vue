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
                :ticket-search="ticketSearch"
                :paused="paused"
                :new-count="pendingEvents.length"
                :has-more="hasMore"
                :enable-support-tickets-tab="!!initial_support_tickets"
                :shell="shell"
                @update:category="setCategory"
                @update:search="setSearch"
                @update:ticket-search="setTicketSearch"
                @toggle-pause="paused = !paused"
                @reveal-new="revealNew"
                @load-more="loadMore"
                @inspect="inspectEntity"
                @action="runAction"
            >
                <template #support-tickets>
                    <SupportTicketLiveFeed
                        :tickets="supportTickets"
                        :has-more="supportTicketsHasMore"
                        :loading="supportTicketsLoading"
                        :shell="shell"
                        @inspect="inspectTicket"
                        @load-more="loadMoreTickets"
                    />
                </template>
            </LiveActivityFeed>
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

        <SupportTicketLiveSlideOver
            :open="ticketSlideOpen"
            :ticket="selectedTicket"
            :loading="ticketDetailLoading"
            :assignable-admins="assignableAdmins"
            :statuses="ticketStatuses"
            :current-user-id="currentUserId"
            :shell="shell"
            @close="ticketSlideOpen = false"
            @updated="handleTicketUpdated"
        />
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import LiveActivityFeed from '@/Components/Admin/LiveActivityFeed.vue';
import SupportTicketLiveFeed from '@/Components/Admin/SupportTicketLiveFeed.vue';
import SupportTicketLiveSlideOver from '@/Components/Admin/SupportTicketLiveSlideOver.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    initial_events: { type: Object, required: true },
    summary: { type: Object, required: true },
    initial_support_tickets: { type: Object, default: null },
    assignable_admins: { type: Array, default: () => [] },
    ticket_statuses: { type: Array, default: () => [] },
    current_user_id: { type: Number, default: null },
});

const { shell } = useInjectedAdminTheme();
const events = ref(props.initial_events.data || []);
const page = ref(props.initial_events.current_page || 1);
const lastPage = ref(props.initial_events.last_page || 1);
const summary = ref(props.summary);
const category = ref(sessionStorage.getItem('admin.liveActivity.category') || 'all');
const search = ref(sessionStorage.getItem('admin.liveActivity.search') || '');
const ticketSearch = ref(sessionStorage.getItem('admin.liveActivity.ticketSearch') || '');
const paused = ref(false);
const pendingEvents = ref([]);
const slideOpen = ref(false);
const slideTitle = ref('');
const slideBody = ref('');
const slideHref = ref('');
const slideRecord = ref(null);
const slideColumns = ref([]);

const supportTickets = ref(props.initial_support_tickets?.data || []);
const supportTicketsPage = ref(props.initial_support_tickets?.current_page || 1);
const supportTicketsLastPage = ref(props.initial_support_tickets?.last_page || 1);
const supportTicketsLoading = ref(false);
const ticketSlideOpen = ref(false);
const selectedTicket = ref(null);
const ticketDetailLoading = ref(false);
const assignableAdmins = ref([...props.assignable_admins]);
const ticketStatuses = ref([...props.ticket_statuses]);
const currentUserId = ref(props.current_user_id);

let searchTimer;
let ticketSearchTimer;
let summaryTimer;

const hasMore = computed(() => page.value < lastPage.value);
const supportTicketsHasMore = computed(() => supportTicketsPage.value < supportTicketsLastPage.value);
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

    if (value === 'support_tickets' && !supportTickets.value.length && props.initial_support_tickets) {
        reloadSupportTickets();
    }
}

function setSearch(value) {
    search.value = value;
    sessionStorage.setItem('admin.liveActivity.search', value);
}

function setTicketSearch(value) {
    ticketSearch.value = value;
    sessionStorage.setItem('admin.liveActivity.ticketSearch', value);
    clearTimeout(ticketSearchTimer);
    ticketSearchTimer = setTimeout(() => reloadSupportTickets(), 250);
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

async function reloadSupportTickets() {
    if (!props.initial_support_tickets) {
        return;
    }

    supportTicketsLoading.value = true;
    try {
        const { data } = await window.axios.get(route('admin.live-activity.support-tickets'), {
            params: {
                page: 1,
                per_page: 50,
                search: ticketSearch.value.trim() || undefined,
            },
        });
        supportTickets.value = data.data || [];
        supportTicketsPage.value = data.current_page || 1;
        supportTicketsLastPage.value = data.last_page || 1;
    } finally {
        supportTicketsLoading.value = false;
    }
}

async function loadMoreTickets() {
    if (!supportTicketsHasMore.value || supportTicketsLoading.value) {
        return;
    }

    supportTicketsLoading.value = true;
    try {
        const next = supportTicketsPage.value + 1;
        const { data } = await window.axios.get(route('admin.live-activity.support-tickets'), {
            params: {
                page: next,
                per_page: 50,
                search: ticketSearch.value.trim() || undefined,
            },
        });
        supportTickets.value = [...supportTickets.value, ...(data.data || [])];
        supportTicketsPage.value = data.current_page || next;
        supportTicketsLastPage.value = data.last_page || supportTicketsLastPage.value;
    } finally {
        supportTicketsLoading.value = false;
    }
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

    if (event.category === 'support_tickets' && category.value === 'support_tickets') {
        reloadSupportTickets();
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

async function inspectTicket(ticket) {
    ticketSlideOpen.value = true;
    selectedTicket.value = null;
    ticketDetailLoading.value = true;

    try {
        const { data } = await window.axios.get(route('admin.live-activity.support-tickets.show', ticket.uuid));
        selectedTicket.value = data.ticket;
        assignableAdmins.value = data.assignable_admins || assignableAdmins.value;
        ticketStatuses.value = data.statuses || ticketStatuses.value;
    } finally {
        ticketDetailLoading.value = false;
    }
}

function handleTicketUpdated(ticket) {
    selectedTicket.value = ticket;
    const index = supportTickets.value.findIndex((row) => row.uuid === ticket.uuid);
    if (index >= 0) {
        supportTickets.value[index] = {
            ...supportTickets.value[index],
            subject: ticket.subject,
            status: ticket.status,
            priority: ticket.priority,
            assigned_admin: ticket.assigned_admin,
            sla_breached: ticket.sla_breached,
        };
    }
}

async function runAction({ event, action }) {
        if (action.method === 'open') {
            if (event.category === 'support_tickets') {
                const ticketEntity = event.entities?.find((entity) => entity.type === 'support_ticket');
                if (ticketEntity?.uuid) {
                    inspectTicket({ uuid: ticketEntity.uuid });
                    return;
                }
            }

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

watch(category, (value) => {
    if (value === 'support_tickets' && props.initial_support_tickets) {
        reloadSupportTickets();
    }
});

onMounted(() => {
    refreshSummary();
    summaryTimer = setInterval(refreshSummary, 60000);

    if (category.value === 'support_tickets' && props.initial_support_tickets) {
        reloadSupportTickets();
    }

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
    clearTimeout(ticketSearchTimer);
    clearInterval(summaryTimer);
    if (window.Echo) {
        window.Echo.leave('private-admin.live-activity');
    }
});
</script>
