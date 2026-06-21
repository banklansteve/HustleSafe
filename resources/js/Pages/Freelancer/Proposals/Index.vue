<template>
    <AppShell>
        <Head title="My proposals" />

        <div class="mx-auto max-w-6xl space-y-6 px-1 sm:px-0">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <BackChevronLink :href="route('dashboard')" aria-label="Back to dashboard" />
                <Link
                    :href="route('quests.browse')"
                    class="inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-4 py-2 text-xs font-black uppercase tracking-wide text-primary-900 shadow-sm hover:bg-primary-100"
                >
                    Find quests
                </Link>
            </div>

            <header
                class="relative overflow-hidden rounded-[1.75rem] border border-primary-200/80 bg-gradient-to-br from-primary-800 via-primary-700 to-teal-600 p-6 text-white shadow-xl ring-1 ring-white/15 sm:p-8"
            >
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_8%,rgba(255,255,255,0.18),transparent_45%),radial-gradient(circle_at_88%_92%,rgba(20,184,166,0.35),transparent_50%)]" />
                <div class="relative flex flex-wrap items-end justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-white/75">Freelancer workspace</p>
                        <h1 class="font-display mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                            My proposals
                        </h1>
                        <p class="mt-2 max-w-prose text-sm font-semibold leading-relaxed text-white/90">
                            Every pitch you have sent — track client views, shortlists, and award progress in one place.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-white/25 bg-white/10 px-4 py-3 text-center backdrop-blur-sm">
                        <p class="text-[10px] font-black uppercase tracking-wide text-white/80">Total sent</p>
                        <p class="font-display mt-1 text-3xl font-black">{{ stats.total }}</p>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                <button
                    v-for="chip in statChips"
                    :key="chip.key"
                    type="button"
                    class="rounded-2xl border px-3 py-3 text-left shadow-sm transition sm:px-4"
                    :class="statusFilter === chip.key
                        ? 'border-primary-300 bg-primary-50 ring-2 ring-primary-200'
                        : 'border-slate-200/90 bg-white ring-1 ring-slate-100 hover:border-primary-200 hover:bg-primary-50/40'"
                    @click="statusFilter = chip.key"
                >
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ chip.label }}</p>
                    <p class="font-display mt-1 text-xl font-black text-slate-900">{{ chip.count }}</p>
                </button>
            </div>

            <ListSearchSortBar
                v-if="proposals.length"
                v-model:search="search"
                v-model:sort="sortKey"
                placeholder="Search quest, status, location, amount…"
                :sort-options="sortOptions"
            />

            <p
                v-if="proposals.length && !filteredRows.length"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/90 px-6 py-10 text-center text-sm font-semibold text-slate-600"
            >
                No proposals match your filters.
            </p>

            <ul v-if="filteredRows.length" class="space-y-4">
                <li v-for="p in visibleRows" :key="p.id">
                    <article
                        class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md"
                    >
                        <div class="flex min-w-0 flex-col gap-4 p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <Link
                                        v-if="p.show_url"
                                        :href="p.show_url"
                                        class="font-display text-lg font-black text-slate-900 hover:text-primary-800 sm:text-xl"
                                    >
                                        {{ p.quest?.title ?? 'Quest' }}
                                    </Link>
                                    <p v-else class="font-display text-lg font-black text-slate-900">
                                        {{ p.quest?.title ?? 'Quest' }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] font-bold uppercase tracking-wide">
                                        <span class="rounded-full px-2.5 py-1 ring-1" :class="statusPillClass(p.status)">
                                            {{ statusLabel(p.status) }}
                                        </span>
                                        <span
                                            v-if="p.quest?.status"
                                            class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600 ring-1 ring-slate-200"
                                        >
                                            Quest {{ formatStatus(p.quest.status) }}
                                        </span>
                                        <span
                                            v-if="p.quest?.parent_category || p.quest?.category"
                                            class="rounded-full bg-primary-50 px-2.5 py-1 text-primary-900 ring-1 ring-primary-100"
                                        >
                                            <template v-if="p.quest.parent_category && p.quest.category">
                                                {{ p.quest.parent_category }} · {{ p.quest.category }}
                                            </template>
                                            <template v-else>{{ p.quest.category || p.quest.parent_category }}</template>
                                        </span>
                                    </div>
                                </div>
                                <div class="w-full rounded-xl border border-primary-100 bg-primary-50/60 px-3 py-2 text-left sm:w-auto sm:min-w-[10rem] sm:text-right">
                                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Your quote</p>
                                    <p class="font-display text-xl font-black text-primary-800 sm:text-2xl">
                                        {{ formatMoney(p.quoted_amount_minor) }}
                                    </p>
                                    <p v-if="p.quest?.budget_minor" class="mt-0.5 text-[11px] font-semibold text-slate-500">
                                        Brief {{ formatMoney(p.quest.budget_minor) }}
                                    </p>
                                </div>
                            </div>

                            <p v-if="p.pitch_preview" class="line-clamp-2 text-sm font-medium leading-relaxed text-slate-600">
                                {{ p.pitch_preview }}
                            </p>

                            <dl class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 ring-1 ring-slate-100">
                                    <dt class="font-black uppercase tracking-wide text-slate-400">Submitted</dt>
                                    <dd class="mt-0.5 font-semibold text-slate-800">{{ formatWhen(p.submitted_at) }}</dd>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 ring-1 ring-slate-100">
                                    <dt class="font-black uppercase tracking-wide text-slate-400">Timeline</dt>
                                    <dd class="mt-0.5 font-semibold text-slate-800">{{ p.timeline_label || '—' }}</dd>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 ring-1 ring-slate-100">
                                    <dt class="font-black uppercase tracking-wide text-slate-400">Client views</dt>
                                    <dd class="mt-0.5 font-semibold text-slate-800">
                                        {{ p.client_view_count }}
                                        <span v-if="p.last_client_view_at" class="block text-[10px] font-medium text-slate-500">
                                            Last {{ formatWhen(p.last_client_view_at) }}
                                        </span>
                                    </dd>
                                </div>
                                <div
                                    v-if="p.quest?.location"
                                    class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 ring-1 ring-slate-100"
                                >
                                    <dt class="font-black uppercase tracking-wide text-slate-400">Location</dt>
                                    <dd class="mt-0.5 font-semibold text-slate-800">{{ p.quest.location }}</dd>
                                </div>
                            </dl>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-if="p.show_url"
                                    :href="p.show_url"
                                    class="inline-flex items-center rounded-full bg-primary-700 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800"
                                >
                                    Open proposal
                                </Link>
                                <Link
                                    v-if="p.edit_url"
                                    :href="p.edit_url"
                                    class="inline-flex items-center rounded-full border border-primary-200 bg-white px-5 py-2.5 text-xs font-black uppercase tracking-wide text-primary-900 hover:bg-primary-50"
                                >
                                    Edit quote
                                </Link>
                                <Link
                                    v-if="p.quest?.show_url"
                                    :href="p.quest.show_url"
                                    class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-50"
                                >
                                    View quest
                                </Link>
                            </div>
                        </div>
                    </article>
                </li>
            </ul>

            <div
                v-if="!proposals.length"
                class="rounded-2xl border border-dashed border-slate-200 bg-gradient-to-br from-slate-50 via-white to-primary-50/40 px-6 py-14 text-center shadow-sm ring-1 ring-slate-100"
            >
                <p class="font-display text-lg font-black text-slate-900">No proposals yet</p>
                <p class="mx-auto mt-2 max-w-md text-sm font-semibold leading-relaxed text-slate-600">
                    When you pitch on open quests, they appear here with live status and client view counts.
                </p>
                <Link
                    :href="route('quests.browse')"
                    class="mt-6 inline-flex items-center rounded-full bg-primary-600 px-6 py-3 text-sm font-black text-white shadow-lg hover:bg-primary-700"
                >
                    Browse open quests
                </Link>
                <Link
                    :href="route('quests.explore')"
                    class="mt-3 inline-flex items-center text-sm font-bold text-primary-700 underline decoration-primary-300 underline-offset-4 hover:text-primary-900"
                >
                    Or see matched quests for you
                </Link>
            </div>

            <div v-if="filteredRows.length > visibleLimit" class="flex justify-center pt-2">
                <button
                    type="button"
                    class="inline-flex min-h-[48px] w-full max-w-sm items-center justify-center rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-black text-slate-800 shadow-sm transition hover:border-primary-300 hover:bg-primary-50 sm:w-auto"
                    @click="visibleLimit += 12"
                >
                    Load more ({{ visibleLimit }} / {{ filteredRows.length }})
                </button>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import ListSearchSortBar from '@/Components/Ui/ListSearchSortBar.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    proposals: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            submitted: 0,
            shortlisted: 0,
            pending_award: 0,
            accepted: 0,
            declined: 0,
            withdrawn: 0,
        }),
    },
});

const search = ref('');
const sortKey = ref('submitted_desc');
const statusFilter = ref('all');
const visibleLimit = ref(12);

const sortOptions = [
    { value: 'submitted_desc', label: 'Newest submitted' },
    { value: 'submitted_asc', label: 'Oldest submitted' },
    { value: 'updated_desc', label: 'Recently updated' },
    { value: 'amount_desc', label: 'Highest quote' },
    { value: 'amount_asc', label: 'Lowest quote' },
    { value: 'title_asc', label: 'Quest title A–Z' },
    { value: 'status_asc', label: 'Status A–Z' },
];

const statChips = computed(() => [
    { key: 'all', label: 'All', count: props.stats.total ?? 0 },
    { key: 'active', label: 'In play', count: props.stats.active ?? 0 },
    { key: 'shortlisted', label: 'Shortlisted', count: props.stats.shortlisted ?? 0 },
    { key: 'pending_award', label: 'Awaiting award', count: props.stats.pending_award ?? 0 },
    { key: 'accepted', label: 'Accepted', count: props.stats.accepted ?? 0 },
    { key: 'closed', label: 'Closed', count: (props.stats.declined ?? 0) + (props.stats.withdrawn ?? 0) },
]);

watch([search, sortKey, statusFilter], () => {
    visibleLimit.value = 12;
});

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    let rows = props.proposals.slice();

    if (statusFilter.value !== 'all') {
        rows = rows.filter((row) => {
            const status = row.status;
            if (statusFilter.value === 'active') {
                return ['submitted', 'shortlisted', 'pending_award'].includes(status);
            }
            if (statusFilter.value === 'closed') {
                return ['declined', 'withdrawn'].includes(status);
            }

            return status === statusFilter.value;
        });
    }

    if (q) {
        rows = rows.filter((row) => {
            const blob = [
                row.quest?.title,
                row.quest?.category,
                row.quest?.parent_category,
                row.quest?.location,
                row.quest?.status,
                row.status,
                row.pitch_preview,
                formatMoney(row.quoted_amount_minor),
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return blob.includes(q);
        });
    }

    const sk = sortKey.value;
    rows.sort((a, b) => {
        if (sk === 'submitted_asc') {
            return ts(a.submitted_at) - ts(b.submitted_at);
        }
        if (sk === 'submitted_desc') {
            return ts(b.submitted_at) - ts(a.submitted_at);
        }
        if (sk === 'updated_desc') {
            return ts(b.updated_at) - ts(a.updated_at);
        }
        if (sk === 'amount_desc') {
            return (Number(b.quoted_amount_minor) || 0) - (Number(a.quoted_amount_minor) || 0);
        }
        if (sk === 'amount_asc') {
            return (Number(a.quoted_amount_minor) || 0) - (Number(b.quoted_amount_minor) || 0);
        }
        if (sk === 'title_asc') {
            return String(a.quest?.title || '').localeCompare(String(b.quest?.title || ''));
        }
        if (sk === 'status_asc') {
            return String(a.status || '').localeCompare(String(b.status || ''));
        }

        return ts(b.submitted_at) - ts(a.submitted_at);
    });

    return rows;
});

const visibleRows = computed(() => filteredRows.value.slice(0, visibleLimit.value));

function ts(iso) {
    if (!iso) {
        return 0;
    }
    const n = Date.parse(iso);

    return Number.isFinite(n) ? n : 0;
}

function formatMoney(minor) {
    if (minor === undefined || minor === null) {
        return '—';
    }
    const n = Math.round(Number(minor) || 0) / 100;

    return `₦${n.toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}

function formatStatus(raw) {
    if (!raw) {
        return '';
    }

    return String(raw).replace(/_/g, ' ');
}

const statusLabels = {
    submitted: 'Submitted',
    shortlisted: 'Shortlisted',
    pending_award: 'Awaiting award',
    accepted: 'Accepted',
    declined: 'Declined',
    withdrawn: 'Withdrawn',
};

function statusLabel(status) {
    return statusLabels[status] || formatStatus(status);
}

function statusPillClass(status) {
    if (status === 'accepted') {
        return 'bg-emerald-100 text-emerald-900 ring-emerald-200';
    }
    if (status === 'shortlisted') {
        return 'bg-sky-100 text-sky-900 ring-sky-200';
    }
    if (status === 'pending_award') {
        return 'bg-amber-100 text-amber-950 ring-amber-200';
    }
    if (status === 'declined' || status === 'withdrawn') {
        return 'bg-slate-200 text-slate-700 ring-slate-300';
    }

    return 'bg-primary-100 text-primary-900 ring-primary-200';
}
</script>
