<template>
    <AppShell>
        <Head :title="`Proposals · ${quest.title}`" />

        <div class="mx-auto max-w-5xl space-y-4 px-1 sm:px-0">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <BackChevronLink :href="route('quests.show', quest.route_key)" aria-label="Back to quest" />
                <span class="rounded-full bg-slate-900 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-white">
                    {{ proposals.length }} {{ proposals.length === 1 ? 'proposal' : 'proposals' }}
                </span>
            </div>

            <header
                class="relative overflow-hidden rounded-[1.75rem] border border-primary-200/80 bg-gradient-to-br from-primary-700 via-primary-600 to-teal-600 p-6 text-white shadow-xl ring-1 ring-white/20 sm:p-8"
            >
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_80%_0%,rgba(255,255,255,0.2),transparent_50%)]" />
                <div class="relative">
                    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-white/80">Inbox</p>
                    <h1 class="font-display mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                        {{ quest.title }}
                    </h1>
                    <p class="mt-2 max-w-prose text-sm font-semibold text-white/90">
                        Shortlist up to {{ shortlistMeta.max }} favourites, then compare side by side before you award.
                    </p>
                </div>
            </header>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-full border border-slate-200 bg-white p-1 shadow-sm ring-1 ring-slate-100">
                    <button
                        type="button"
                        class="rounded-full px-4 py-2 text-xs font-black uppercase tracking-wide transition"
                        :class="activeTab === 'all' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                        @click="activeTab = 'all'"
                    >
                        All proposals
                        <span class="ml-1 opacity-80">({{ proposals.length }})</span>
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-4 py-2 text-xs font-black uppercase tracking-wide transition"
                        :class="activeTab === 'shortlist' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                        @click="activeTab = 'shortlist'"
                    >
                        Shortlist
                        <span class="ml-1 opacity-80">({{ shortlistMeta.count }}/{{ shortlistMeta.max }})</span>
                    </button>
                </div>
                <p v-if="shortlistMeta.count >= shortlistMeta.max" class="text-xs font-semibold text-amber-800">
                    Shortlist full — remove one to add another.
                </p>
            </div>

            <ListSearchSortBar
                v-if="activeTab === 'all' && proposals.length"
                v-model:search="search"
                v-model:sort="sortKey"
                placeholder="Freelancer, status, amount…"
                :sort-options="sortOptions"
            />

            <p
                v-if="activeTab === 'all' && !filtered.length && proposals.length"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/90 px-6 py-10 text-center text-sm font-semibold text-slate-600"
            >
                No proposals match your search.
            </p>

            <p
                v-if="activeTab === 'shortlist' && !shortlistedRows.length"
                class="rounded-2xl border border-dashed border-sky-200 bg-sky-50/80 px-6 py-10 text-center text-sm font-semibold text-sky-900"
            >
                No shortlisted proposals yet — tap Shortlist on any proposal in the All tab.
            </p>

            <!-- Shortlist comparison view -->
            <div v-if="activeTab === 'shortlist' && shortlistedRows.length" class="space-y-3">
                <div class="hidden overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm ring-1 ring-sky-50 md:block">
                    <div class="grid grid-cols-[minmax(0,1.4fr)_repeat(4,minmax(0,1fr))_auto] gap-3 border-b border-sky-100 bg-sky-50/80 px-4 py-3 text-[10px] font-black uppercase tracking-wide text-sky-900">
                        <span>Freelancer</span>
                        <span>Price</span>
                        <span>Timeline</span>
                        <span>Completeness</span>
                        <span>Trust tier</span>
                        <span class="sr-only">Action</span>
                    </div>
                    <div
                        v-for="p in shortlistedRows"
                        :key="p.id"
                        class="grid grid-cols-[minmax(0,1.4fr)_repeat(4,minmax(0,1fr))_auto] items-center gap-3 border-b border-slate-100 px-4 py-4 last:border-b-0"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <UserProfileAvatar
                                :href="p.freelancer?.slug ? route('freelancers.public', p.freelancer.slug) : null"
                                :src="p.freelancer?.avatar_url"
                                :name="displayName(p)"
                                :alt="displayName(p)"
                                frame-class="h-10 w-10 shrink-0 text-xs"
                            />
                            <div class="min-w-0">
                                <Link :href="p.show_url" class="truncate font-display text-sm font-black text-slate-900 hover:text-primary-800">
                                    {{ displayName(p) }}
                                </Link>
                                <p v-if="p.freelancer?.headline" class="truncate text-[11px] font-semibold text-slate-500">{{ p.freelancer.headline }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-black text-primary-800">{{ formatMoney(p.quoted_amount_minor) }}</span>
                        <span class="text-sm font-semibold text-slate-700">{{ p.timeline_label || '—' }}</span>
                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-black" :class="completenessClass(p.completeness_score)">
                            {{ p.completeness_score }}%
                        </span>
                        <span class="text-sm font-black text-slate-800">L{{ p.trust_tier }}</span>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <Link
                                :href="p.show_url"
                                class="rounded-full bg-primary-700 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800"
                            >
                                View
                            </Link>
                            <button
                                type="button"
                                class="rounded-full bg-sky-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white hover:bg-sky-700 disabled:opacity-60"
                                :disabled="shortlistCapReached && !p.is_shortlisted"
                                @click="toggleShortlist(p)"
                            >
                                {{ p.is_shortlisted ? 'Shortlisted' : 'Shortlist' }}
                            </button>
                        </div>
                    </div>
                </div>

                <ul class="space-y-3 md:hidden">
                    <li v-for="p in shortlistedRows" :key="p.id">
                        <article class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm ring-1 ring-sky-50">
                            <div class="flex items-start gap-3">
                                <UserProfileAvatar
                                    :href="p.freelancer?.slug ? route('freelancers.public', p.freelancer.slug) : null"
                                    :src="p.freelancer?.avatar_url"
                                    :name="displayName(p)"
                                    :alt="displayName(p)"
                                    frame-class="h-12 w-12 shrink-0 text-xs"
                                />
                                <div class="min-w-0 flex-1">
                                    <Link :href="p.show_url" class="font-display text-base font-black text-slate-900">{{ displayName(p) }}</Link>
                                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                        <div><dt class="font-black uppercase tracking-wide text-slate-400">Price</dt><dd class="mt-0.5 font-black text-primary-800">{{ formatMoney(p.quoted_amount_minor) }}</dd></div>
                                        <div><dt class="font-black uppercase tracking-wide text-slate-400">Timeline</dt><dd class="mt-0.5 font-semibold text-slate-800">{{ p.timeline_label || '—' }}</dd></div>
                                        <div><dt class="font-black uppercase tracking-wide text-slate-400">Complete</dt><dd class="mt-0.5"><span class="rounded-full px-2 py-0.5 font-black" :class="completenessClass(p.completeness_score)">{{ p.completeness_score }}%</span></dd></div>
                                        <div><dt class="font-black uppercase tracking-wide text-slate-400">Trust</dt><dd class="mt-0.5 font-black text-slate-800">L{{ p.trust_tier }}</dd></div>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link
                                    :href="p.show_url"
                                    class="rounded-full bg-primary-700 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800"
                                >
                                    View proposal
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-full bg-sky-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white disabled:opacity-60"
                                    @click="toggleShortlist(p)"
                                >
                                    {{ p.is_shortlisted ? 'Shortlisted' : 'Shortlist' }}
                                </button>
                            </div>
                        </article>
                    </li>
                </ul>
            </div>

            <!-- All proposals list -->
            <ul v-if="activeTab === 'all'" class="space-y-3">
                <li v-for="p in visibleRows" :key="p.id">
                    <article class="group flex gap-4 rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md sm:p-5">
                        <Link :href="p.show_url" class="shrink-0">
                            <UserProfileAvatar
                                :href="p.freelancer?.slug ? route('freelancers.public', p.freelancer.slug) : null"
                                :src="p.freelancer?.avatar_url"
                                :name="displayName(p)"
                                :alt="displayName(p)"
                                frame-class="h-14 w-14 text-sm shadow-md ring-2 ring-white"
                            />
                        </Link>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <Link :href="p.show_url" class="font-display text-base font-black text-slate-900 group-hover:text-primary-800">
                                        {{ displayName(p) }}
                                    </Link>
                                    <p v-if="p.freelancer?.headline" class="mt-0.5 line-clamp-1 text-xs font-semibold text-slate-500">
                                        {{ p.freelancer.headline }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide text-white"
                                    :class="statusClass(p.status)"
                                >
                                    {{ formatStatus(p.status) }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-semibold text-slate-600">
                                <span class="font-black text-primary-800">{{ formatMoney(p.quoted_amount_minor) }}</span>
                                <span v-if="p.timeline_label">{{ p.timeline_label }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 font-black text-slate-700">{{ p.completeness_score }}% complete</span>
                                <span>L{{ p.trust_tier }} trust</span>
                                <span>Submitted {{ formatWhen(p.created_at) }}</span>
                            </div>
                            <div v-if="canShortlist(p)" class="mt-3 flex flex-wrap gap-2">
                                <Link
                                    :href="p.show_url"
                                    class="rounded-full bg-primary-700 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800"
                                >
                                    View proposal
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-full px-4 py-2 text-[10px] font-black uppercase tracking-wide shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="p.is_shortlisted
                                        ? 'bg-sky-600 text-white ring-2 ring-sky-300 hover:bg-sky-700'
                                        : 'border border-sky-200 bg-white text-sky-900 hover:bg-sky-50'"
                                    :disabled="shortlistCapReached && !p.is_shortlisted"
                                    @click="toggleShortlist(p)"
                                >
                                    {{ p.is_shortlisted ? 'Shortlisted' : 'Shortlist' }}
                                </button>
                            </div>
                            <div v-else class="mt-3">
                                <Link
                                    :href="p.show_url"
                                    class="inline-flex rounded-full bg-primary-700 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800"
                                >
                                    View proposal
                                </Link>
                            </div>
                        </div>
                    </article>
                </li>
            </ul>

            <p v-if="!proposals.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center text-sm font-semibold text-slate-600">
                No proposals yet — when freelancers pitch, they will land here.
            </p>

            <div v-if="activeTab === 'all' && filtered.length > visibleCount" class="flex justify-center">
                <button
                    type="button"
                    class="rounded-full border border-slate-200 bg-white px-6 py-2.5 text-xs font-black uppercase tracking-wide text-slate-800 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                    @click="visibleCount += pageSize"
                >
                    Load more
                </button>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import ListSearchSortBar from '@/Components/Ui/ListSearchSortBar.vue';
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import AppShell from '@/Layouts/AppShell.vue';
import axios from 'axios';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    proposals: { type: Array, default: () => [] },
    shortlist_meta: { type: Object, default: () => ({ max: 5, count: 0 }) },
});

const page = usePage();
const activeTab = ref('all');
const search = ref('');
const sortKey = ref('submitted_desc');
const visibleCount = ref(12);
const pageSize = 12;
const shortlistMeta = ref({ ...props.shortlist_meta });
const localProposals = ref(props.proposals.map((p) => ({ ...p })));

watch(
    () => props.proposals,
    (rows) => {
        localProposals.value = rows.map((p) => ({ ...p }));
    },
    { deep: true },
);

watch(
    () => props.shortlist_meta,
    (m) => {
        shortlistMeta.value = { ...m };
    },
    { deep: true },
);

watch(
    () => page.url,
    (url) => {
        if (/\btab=shortlist\b/.test(url || '')) {
            activeTab.value = 'shortlist';
        }
    },
    { immediate: true },
);

const sortOptions = [
    { value: 'submitted_desc', label: 'Newest submitted' },
    { value: 'shortlisted_first', label: 'Shortlisted first' },
    { value: 'amount_desc', label: 'Highest quote' },
    { value: 'completeness_desc', label: 'Most complete' },
    { value: 'trust_desc', label: 'Highest trust tier' },
    { value: 'name_asc', label: 'Freelancer A–Z' },
];

const shortlistCapReached = computed(() => shortlistMeta.value.count >= shortlistMeta.value.max);

function canShortlist(p) {
    return ['submitted', 'shortlisted'].includes(p.status);
}

function toggleShortlist(p) {
    if (!canShortlist(p)) {
        return;
    }

    const wasShortlisted = Boolean(p.is_shortlisted);
    if (!wasShortlisted && shortlistCapReached.value) {
        return;
    }

    p.is_shortlisted = !wasShortlisted;
    p.status = p.is_shortlisted ? 'shortlisted' : 'submitted';
    shortlistMeta.value = {
        ...shortlistMeta.value,
        count: Math.max(0, shortlistMeta.value.count + (p.is_shortlisted ? 1 : -1)),
    };

    axios
        .post(route('quests.proposals.toggle-shortlist', [props.quest.route_key, p.id]), {}, {
            headers: { Accept: 'application/json' },
        })
        .then(({ data }) => {
            p.is_shortlisted = Boolean(data.shortlisted);
            p.status = data.status || (p.is_shortlisted ? 'shortlisted' : 'submitted');
            if (typeof data.shortlist_count === 'number') {
                shortlistMeta.value = {
                    ...shortlistMeta.value,
                    count: data.shortlist_count,
                    max: data.shortlist_max ?? shortlistMeta.value.max,
                };
            }
        })
        .catch(() => {
            p.is_shortlisted = wasShortlisted;
            p.status = wasShortlisted ? 'shortlisted' : 'submitted';
            shortlistMeta.value = {
                ...shortlistMeta.value,
                count: Math.max(0, shortlistMeta.value.count + (wasShortlisted ? 1 : -1)),
            };
        });
}

function ts(iso) {
    if (!iso) {
        return 0;
    }
    const n = Date.parse(iso);

    return Number.isFinite(n) ? n : 0;
}

function displayName(p) {
    const f = p.freelancer;
    if (!f) {
        return 'Freelancer';
    }
    const full = String(f.name || '').trim();
    if (full) {
        return full;
    }
    const parts = [f.first_name, f.last_name].map((x) => String(x || '').trim()).filter(Boolean);

    return parts.length ? parts.join(' ') : 'Freelancer';
}

function formatStatus(s) {
    return String(s || '').replace(/_/g, ' ');
}

function statusClass(status) {
    if (status === 'accepted') {
        return 'bg-emerald-600';
    }
    if (status === 'declined' || status === 'withdrawn') {
        return 'bg-slate-500';
    }
    if (status === 'shortlisted') {
        return 'bg-sky-600';
    }

    return 'bg-primary-700';
}

function completenessClass(score) {
    if (score >= 80) {
        return 'bg-emerald-100 text-emerald-900';
    }
    if (score >= 60) {
        return 'bg-amber-100 text-amber-900';
    }

    return 'bg-slate-100 text-slate-700';
}

function formatMoney(minor) {
    const n = Math.round(Number(minor) || 0) / 100;

    return `₦${n.toLocaleString('en-NG')}`;
}

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString('en-NG', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return '';
    }
}

const shortlistedRows = computed(() => localProposals.value.filter((p) => p.is_shortlisted));

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    let rows = localProposals.value.slice();
    if (q) {
        rows = rows.filter((p) => {
            const blob = [
                displayName(p),
                p.freelancer?.headline,
                p.status,
                formatMoney(p.quoted_amount_minor),
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return blob.includes(q);
        });
    }
    const sk = sortKey.value;
    rows.sort((a, b) => {
        if (sk === 'shortlisted_first') {
            const aS = a.is_shortlisted ? 1 : 0;
            const bS = b.is_shortlisted ? 1 : 0;
            if (aS !== bS) {
                return bS - aS;
            }
        }
        if (sk === 'amount_desc') {
            return (Number(b.quoted_amount_minor) || 0) - (Number(a.quoted_amount_minor) || 0);
        }
        if (sk === 'completeness_desc') {
            return (Number(b.completeness_score) || 0) - (Number(a.completeness_score) || 0);
        }
        if (sk === 'trust_desc') {
            return (Number(b.trust_tier) || 0) - (Number(a.trust_tier) || 0);
        }
        if (sk === 'name_asc') {
            return displayName(a).localeCompare(displayName(b));
        }

        return ts(b.created_at) - ts(a.created_at);
    });

    return rows;
});

const visibleRows = computed(() => filtered.value.slice(0, visibleCount.value));
</script>
