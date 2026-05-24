<template>
    <AdminShell
        title="Quest completion events"
        subtitle="Immutable audit trail for escrow funding, delivery acknowledgement, release holds, and fund releases."
    >
        <div class="mb-6 rounded-2xl border p-4" :class="shell.card">
            <form class="grid gap-3 md:grid-cols-2 lg:grid-cols-4" @submit.prevent="applyFilters">
                <input
                    v-model="localFilters.q"
                    type="search"
                    placeholder="Quest, actor email, event type…"
                    class="rounded-xl border px-3 py-2.5 text-sm font-semibold md:col-span-2 lg:col-span-2"
                    :class="shell.input"
                />
                <select v-model="localFilters.event_type" class="rounded-xl border px-3 py-2.5 text-sm font-bold" :class="shell.input">
                    <option value="">All event types</option>
                    <option v-for="type in event_types" :key="type" :value="type">{{ labelize(type) }}</option>
                </select>
                <input v-model="localFilters.from" type="date" class="rounded-xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input" />
                <input v-model="localFilters.to" type="date" class="rounded-xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input" />
                <div class="flex flex-wrap items-center gap-2 md:col-span-2 lg:col-span-4">
                    <button type="submit" class="rounded-xl bg-teal-500 px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-950 hover:bg-teal-400">
                        Apply filters
                    </button>
                    <button type="button" class="rounded-xl border px-4 py-2 text-xs font-black uppercase tracking-wide" :class="shell.cardMuted" @click="clearFilters">
                        Clear
                    </button>
                    <select v-model="localFilters.per_page" class="ml-auto rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input" @change="applyFilters">
                        <option :value="15">15 / page</option>
                        <option :value="25">25 / page</option>
                        <option :value="50">50 / page</option>
                        <option :value="100">100 / page</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto rounded-2xl border" :class="shell.card">
            <table class="min-w-full text-left text-sm" :class="['divide-y', shell.tableDivide]">
                <thead class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.tableHead">
                    <tr>
                        <th class="px-4 py-3">
                            <button type="button" class="inline-flex items-center gap-1 hover:text-teal-600" @click="toggleSort('occurred_at')">
                                When
                                <span class="text-[10px]" :class="filters.sort === 'occurred_at' ? 'text-teal-500' : 'opacity-30'">{{ filters.direction === 'asc' ? '▲' : '▼' }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" class="inline-flex items-center gap-1 hover:text-teal-600" @click="toggleSort('event_type')">
                                Event
                                <span class="text-[10px]" :class="filters.sort === 'event_type' ? 'text-teal-500' : 'opacity-30'">{{ filters.direction === 'asc' ? '▲' : '▼' }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" class="inline-flex items-center gap-1 hover:text-teal-600" @click="toggleSort('actor')">
                                Actor
                                <span class="text-[10px]" :class="filters.sort === 'actor' ? 'text-teal-500' : 'opacity-30'">{{ filters.direction === 'asc' ? '▲' : '▼' }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" class="inline-flex items-center gap-1 hover:text-teal-600" @click="toggleSort('quest')">
                                Quest
                                <span class="text-[10px]" :class="filters.sort === 'quest' ? 'text-teal-500' : 'opacity-30'">{{ filters.direction === 'asc' ? '▲' : '▼' }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3">Details</th>
                    </tr>
                </thead>
                <tbody :class="['divide-y', shell.tableDivide]">
                    <tr v-for="event in events.data" :key="event.id" class="align-top transition hover:bg-slate-50 dark:hover:bg-white/5">
                        <td class="px-4 py-3 text-xs whitespace-nowrap" :class="shell.cardMuted">{{ formatDate(event.occurred_at) }}</td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs font-bold text-primary-700 dark:text-teal-200">{{ event.event_type }}</span>
                            <p class="mt-0.5 text-[10px] capitalize text-slate-500">{{ event.event_label }}</p>
                        </td>
                        <td class="px-4 py-3" :class="shell.tableRow">
                            <p class="font-bold">{{ event.actor?.name ?? 'System' }}</p>
                            <p v-if="event.actor?.email" class="text-xs text-slate-500">{{ event.actor.email }}</p>
                            <p v-if="event.ip_address" class="mt-1 font-mono text-[10px] text-slate-400">{{ event.ip_address }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <template v-if="event.quest">
                                <Link
                                    :href="route('admin.quests.index', { q: event.quest.title })"
                                    class="font-bold text-primary-700 hover:underline dark:text-teal-200"
                                >
                                    {{ event.quest.title }}
                                </Link>
                                <p class="mt-1 text-[10px] font-bold capitalize text-slate-500">
                                    {{ event.quest.status?.replace(/_/g, ' ') }} · {{ event.quest.escrow_status }}
                                </p>
                            </template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-xs" :class="shell.cardMuted">
                            <p v-if="event.amount_minor" class="font-black text-slate-700 dark:text-slate-200">{{ event.amount_minor }}</p>
                            <p v-if="event.meta && Object.keys(event.meta).length" class="mt-1 max-w-xs break-words font-mono text-[10px]">{{ metaPreview(event.meta) }}</p>
                        </td>
                    </tr>
                    <tr v-if="!events.data?.length">
                        <td colspan="5" class="px-4 py-12 text-center text-sm font-semibold text-slate-500">No completion events match your filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="events.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in events.links"
                :key="String(link.label) + (link.url || 'x')"
                :href="link.url || undefined"
                prefetch="false"
                class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                :class="[link.active ? 'bg-teal-500 text-slate-950' : 'border border-white/10 text-slate-200', !link.url ? 'pointer-events-none opacity-40' : '']"
                preserve-state
            >
                <span v-html="link.label" />
            </component>
        </nav>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    events: { type: Object, required: true },
    filters: { type: Object, required: true },
    event_types: { type: Array, default: () => [] },
});

const { shell } = useInjectedAdminTheme();

const localFilters = reactive({
    q: props.filters.q ?? '',
    event_type: props.filters.event_type ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    per_page: props.filters.per_page ?? 25,
});

function params(extra = {}) {
    return {
        q: localFilters.q,
        event_type: localFilters.event_type,
        from: localFilters.from || undefined,
        to: localFilters.to || undefined,
        per_page: localFilters.per_page,
        sort: props.filters.sort ?? 'occurred_at',
        direction: props.filters.direction ?? 'desc',
        ...extra,
    };
}

function applyFilters() {
    router.get(route('admin.quest-completion-events.index'), params(), { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    localFilters.q = '';
    localFilters.event_type = '';
    localFilters.from = '';
    localFilters.to = '';
    applyFilters();
}

function toggleSort(column) {
    const nextDirection = props.filters.sort === column && props.filters.direction === 'desc' ? 'asc' : 'desc';
    router.get(route('admin.quest-completion-events.index'), params({ sort: column, direction: nextDirection }), {
        preserveState: true,
        preserveScroll: true,
    });
}

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', { timeZone: 'Africa/Lagos' });
    } catch {
        return iso;
    }
}

function labelize(value) {
    return String(value || '').replace(/_/g, ' ');
}

function metaPreview(meta) {
    try {
        const copy = { ...meta };
        delete copy.actor_user_id;
        return JSON.stringify(copy);
    } catch {
        return '';
    }
}

</script>
