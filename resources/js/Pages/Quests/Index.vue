<template>
    <AppShell>
        <Head title="Your quests" />

        <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">
                        Client workspace
                    </p>
                    <h1 class="font-display mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Your quests
                    </h1>
                    <p class="mt-3 max-w-xl text-sm font-semibold leading-relaxed text-teal-50/95 sm:text-base">
                        Search and sort locally, then open a quest to manage proposals, files, and updates.
                    </p>
                </div>
                <Link
                    :href="route('quests.create')"
                    class="inline-flex items-center rounded-full bg-white px-5 py-2.5 text-sm font-black text-primary-900 shadow-lg shadow-slate-950/30 ring-1 ring-white/40 transition hover:bg-teal-50"
                >
                    New quest
                </Link>
            </div>
        </div>

        <div class="mt-8">
            <ListSearchSortBar
                v-if="allQuests.length"
                v-model:search="search"
                v-model:sort="sortKey"
                class="mb-6"
                placeholder="Search title, code, category, location…"
                :sort-options="sortOptions"
            />

            <div v-if="sortedFiltered.length" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="q in sortedFiltered"
                    :key="q.slug || q.uuid"
                    class="group relative flex flex-col overflow-hidden rounded-[1.35rem] border border-slate-100/90 bg-white shadow-lg shadow-slate-900/5 ring-1 ring-slate-100 transition hover:-translate-y-0.5 hover:border-primary-200/80 hover:shadow-xl"
                >
                    <div class="relative aspect-[16/10] w-full overflow-hidden bg-slate-100">
                        <Link :href="showUrl(q)" class="block h-full w-full">
                            <img
                                :src="q.cover_url"
                                alt=""
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                            />
                        </Link>
                        <Link
                            v-if="q.can_client_edit"
                            :href="editUrl(q)"
                            class="absolute right-3 top-3 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg shadow-primary-900/30 ring-2 ring-white/90 transition duration-200 hover:scale-105 hover:bg-primary-700 max-sm:opacity-100 sm:opacity-0 sm:transition-opacity sm:duration-200 sm:group-hover:opacity-100"
                            title="Edit listing"
                            aria-label="Edit listing"
                            @click.stop
                        >
                            <PencilSquareIcon class="h-5 w-5" aria-hidden="true" />
                        </Link>
                        <span
                            class="absolute left-3 top-3 inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ring-1 backdrop-blur-md"
                            :class="statusClass(q.status)"
                        >
                            {{ formatStatus(q.status) }}
                        </span>
                    </div>

                    <Link :href="showUrl(q)" class="flex flex-1 flex-col p-5 sm:p-6">
                        <p class="font-display line-clamp-2 min-w-0 text-lg font-bold leading-snug text-slate-900 transition group-hover:text-primary-800">
                            {{ q.title }}
                        </p>
                        <p class="mt-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                            {{ q.reference_code }}
                        </p>
                        <p class="mt-3 text-xs font-semibold text-slate-500">
                            <span class="font-bold text-slate-700">Published</span>
                            {{ formatDate(q.published_at) }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-if="q.parent_category || q.subcategory"
                                class="rounded-full bg-primary-50 px-2.5 py-1 text-[11px] font-bold text-primary-900 ring-1 ring-primary-100"
                            >
                                <template v-if="q.parent_category && q.subcategory">{{ q.parent_category }} · {{ q.subcategory }}</template>
                                <template v-else>{{ q.subcategory || q.parent_category }}</template>
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                {{ [q.city, q.state].filter(Boolean).join(' · ') || 'Location TBC' }}
                            </span>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-3 text-xs font-bold text-slate-600">
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 ring-1 ring-slate-100">
                                <span class="font-black text-primary-700">{{ q.proposals_count }}</span>
                                proposals
                            </span>
                            <span class="text-slate-400">·</span>
                            <span>{{ formatBudget(q.budget_minor) }}</span>
                        </div>
                        <p class="mt-auto pt-4 text-[11px] font-semibold text-slate-400">
                            Updated {{ formatWhen(q.updated_at) }}
                        </p>
                    </Link>
                </article>
            </div>

            <p
                v-else-if="allQuests.length === 0"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-14 text-center text-base font-semibold text-slate-600"
            >
                No quests yet — start with a polished brief and we will alert the right freelancers.
            </p>
            <p
                v-else-if="allQuests.length && !sortedFiltered.length"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center text-sm font-semibold text-slate-600"
            >
                No quests match your search.
            </p>

            <div v-if="hasMore || loadingMore" class="mt-10 flex justify-center">
                <button
                    type="button"
                    class="inline-flex min-h-[48px] items-center justify-center rounded-full border border-slate-200 bg-white px-8 py-3 text-sm font-black text-slate-800 shadow-sm transition hover:border-primary-300 hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="loadingMore || !hasMore"
                    @click="loadMore"
                >
                    <span v-if="loadingMore">Loading…</span>
                    <span v-else-if="hasMore">Load more</span>
                    <span v-else>All loaded</span>
                </button>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import ListSearchSortBar from '@/Components/Ui/ListSearchSortBar.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { PencilSquareIcon } from '@heroicons/vue/24/solid';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    quests: {
        type: Object,
        required: true,
    },
});

const allQuests = ref([...(props.quests?.data ?? [])]);
const currentPage = ref(props.quests?.meta?.current_page ?? 1);
const lastPage = ref(props.quests?.meta?.last_page ?? 1);
const hasMore = ref((props.quests?.meta?.current_page ?? 1) < (props.quests?.meta?.last_page ?? 1));
const loadingMore = ref(false);
const search = ref('');
const sortKey = ref('updated_desc');

const sortOptions = [
    { value: 'updated_desc', label: 'Recently updated' },
    { value: 'published_desc', label: 'Newest listed' },
    { value: 'title_asc', label: 'Title A–Z' },
    { value: 'proposals_desc', label: 'Most proposals' },
    { value: 'budget_desc', label: 'Highest budget' },
];

watch(
    () => props.quests,
    (q) => {
        allQuests.value = [...(q?.data ?? [])];
        currentPage.value = q?.meta?.current_page ?? 1;
        lastPage.value = q?.meta?.last_page ?? 1;
        hasMore.value = currentPage.value < lastPage.value;
    },
    { deep: true },
);

const indexJsonUrl = computed(() => route('quests.index'));

const sortedFiltered = computed(() => {
    const q = search.value.trim().toLowerCase();
    let rows = allQuests.value.slice();
    if (q) {
        rows = rows.filter((row) => {
            const blob = [
                row.title,
                row.reference_code,
                row.parent_category,
                row.subcategory,
                row.category,
                row.city,
                row.state,
                row.status,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return blob.includes(q);
        });
    }
    const sk = sortKey.value;
    rows.sort((a, b) => {
        if (sk === 'title_asc') {
            return String(a.title || '').localeCompare(String(b.title || ''));
        }
        if (sk === 'budget_desc') {
            return (Number(b.budget_minor) || 0) - (Number(a.budget_minor) || 0);
        }
        if (sk === 'proposals_desc') {
            return (Number(b.proposals_count) || 0) - (Number(a.proposals_count) || 0);
        }
        if (sk === 'published_desc') {
            return ts(b.published_at) - ts(a.published_at);
        }

        return ts(b.updated_at) - ts(a.updated_at);
    });

    return rows;
});

function ts(iso) {
    if (!iso) {
        return 0;
    }
    const n = Date.parse(iso);

    return Number.isFinite(n) ? n : 0;
}

async function loadMore() {
    if (loadingMore.value || !hasMore.value) {
        return;
    }
    loadingMore.value = true;
    try {
        const next = currentPage.value + 1;
        const { data } = await axios.get(indexJsonUrl.value, {
            params: { page: next },
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const chunk = data?.data ?? [];
        allQuests.value = allQuests.value.concat(chunk);
        currentPage.value = data?.meta?.current_page ?? next;
        lastPage.value = data?.meta?.last_page ?? lastPage.value;
        hasMore.value = !!data?.meta?.has_more;
    } catch {
        hasMore.value = false;
    } finally {
        loadingMore.value = false;
    }
}

function showUrl(row) {
    return route('quests.show', row.slug || row.uuid);
}

function editUrl(row) {
    const base = route('quests.show', row.slug || row.uuid);
    const join = base.includes('?') ? '&' : '?';

    return `${base}${join}edit=1`;
}

function statusClass(s) {
    if (s === 'open') {
        return 'bg-emerald-500/90 text-white ring-emerald-300/50';
    }
    if (s === 'draft') {
        return 'bg-amber-400/95 text-amber-950 ring-amber-200/80';
    }

    return 'bg-slate-900/80 text-white ring-slate-600/50';
}

function formatStatus(s) {
    if (!s) {
        return '';
    }

    return String(s).replace(/_/g, ' ');
}

function formatBudget(minor) {
    const n = Number(minor) / 100;

    return `₦${n.toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}

function formatDate(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleDateString('en-NG', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}
</script>
