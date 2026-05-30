<template>
    <AppShell>
        <Head :title="`Proposals · ${quest.title}`" />

        <div class="mx-auto max-w-4xl space-y-4 px-1 sm:px-0">
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
                        Search and sort entirely in your browser — nothing leaves this page until you open a proposal.
                    </p>
                </div>
            </header>

            <ListSearchSortBar
                v-if="proposals.length"
                v-model:search="search"
                v-model:sort="sortKey"
                placeholder="Freelancer, status, amount…"
                :sort-options="sortOptions"
            />

            <p
                v-if="!filtered.length && proposals.length"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/90 px-6 py-10 text-center text-sm font-semibold text-slate-600"
            >
                No proposals match your search.
            </p>

            <ul class="space-y-3">
                <li v-for="p in visibleRows" :key="p.id">
                    <Link
                        :href="p.show_url"
                        class="group flex gap-4 rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md sm:p-5"
                    >
                        <UserProfileAvatar
                            :href="p.freelancer?.slug ? route('freelancers.public', p.freelancer.slug) : null"
                            :src="p.freelancer?.avatar_url"
                            :name="displayName(p)"
                            :alt="displayName(p)"
                            frame-class="h-14 w-14 shrink-0 text-sm shadow-md ring-2 ring-white"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-display text-base font-black text-slate-900 group-hover:text-primary-800">
                                        {{ displayName(p) }}
                                    </p>
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
                                <span>Submitted {{ formatWhen(p.created_at) }}</span>
                                <span v-if="p.client_pinned_at" class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-black uppercase text-violet-900">Pinned</span>
                                <span v-if="p.shortlisted_at" class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-black uppercase text-sky-900">Shortlisted</span>
                            </div>
                            <div v-if="p.status === 'submitted'" class="mt-3 flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-full bg-sky-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white hover:bg-sky-700"
                                    @click.prevent="shortlistProposal(p)"
                                >
                                    Shortlist
                                </button>
                            </div>
                        </div>
                    </Link>
                </li>
            </ul>

            <p v-if="!proposals.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center text-sm font-semibold text-slate-600">
                No proposals yet — when freelancers pitch, they will land here.
            </p>

            <div v-if="filtered.length > visibleCount" class="flex justify-center">
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
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    proposals: { type: Array, default: () => [] },
});

const search = ref('');
const sortKey = ref('submitted_desc');
const visibleCount = ref(12);
const pageSize = 12;

const sortOptions = [
    { value: 'submitted_desc', label: 'Newest submitted' },
    { value: 'shortlisted_first', label: 'Shortlisted first' },
    { value: 'amount_desc', label: 'Highest quote' },
    { value: 'status_asc', label: 'Status A–Z' },
    { value: 'name_asc', label: 'Freelancer A–Z' },
];

function shortlistProposal(p) {
    router.post(route('quests.proposals.shortlist', [props.quest.route_key, p.id]), { confirm: true }, { preserveScroll: true });
}

function ts(iso) {
    if (!iso) {
        return 0;
    }
    const n = Date.parse(iso);

    return Number.isFinite(n) ? n : 0;
}

function displayName(p) {
    return p.freelancer?.first_name || p.freelancer?.name || 'Freelancer';
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
        return 'bg-violet-600';
    }

    return 'bg-primary-700';
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

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    let rows = props.proposals.slice();
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
            const aS = a.status === 'shortlisted' || a.shortlisted_at ? 1 : 0;
            const bS = b.status === 'shortlisted' || b.shortlisted_at ? 1 : 0;
            if (aS !== bS) {
                return bS - aS;
            }
        }
        if (sk === 'amount_desc') {
            return (Number(b.quoted_amount_minor) || 0) - (Number(a.quoted_amount_minor) || 0);
        }
        if (sk === 'status_asc') {
            return String(a.status || '').localeCompare(String(b.status || ''));
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
