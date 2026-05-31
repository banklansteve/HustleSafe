<template>
    <AppShell>
        <Head :title="title" />

        <div class="mx-auto max-w-4xl">
            <div class="mb-6 flex items-center gap-3">
                <BackChevronLink :href="route('dashboard')" aria-label="Back to dashboard" />
                <span class="text-sm font-bold text-slate-600">Home</span>
            </div>

            <header class="rounded-xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/80 to-primary-50/30 p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                <h1 class="font-display text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    {{ title }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm font-medium leading-relaxed text-slate-600 sm:text-base">
                    {{ subtitle }}
                </p>
                <p v-if="meta.total != null" class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ meta.total }} {{ meta.total === 1 ? 'item' : 'items' }}
                </p>
            </header>

            <ListSearchSortBar
                v-if="items.length"
                v-model:search="search"
                v-model:sort="sortKey"
                class="mt-5"
                placeholder="Search titles, status, amounts…"
                :sort-options="sortOptions"
            />

            <ul class="mt-5 space-y-3">
                <li v-for="(row, i) in displayItems" :key="row.kind + '-' + row.id + '-' + i">
                    <Link
                        v-if="row.kind === 'quest'"
                        :href="route('quests.show', row.slug || row.uuid)"
                        class="block rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-100/90 transition hover:border-primary-200 hover:shadow-md sm:p-5"
                    >
                        <p class="font-display text-base font-bold text-slate-900">
                            {{ row.title }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold uppercase tracking-wide text-slate-600">
                            <span class="rounded-md bg-primary-50 px-2.5 py-1 text-primary-800 ring-1 ring-primary-100">
                                {{ formatStatus(row.status) }}
                            </span>
                            <span v-if="row.budget_display" class="rounded-md bg-slate-50 px-2.5 py-1 ring-1 ring-slate-100">
                                Budget {{ row.budget_display }}
                            </span>
                            <span v-if="row.paid_out_display" class="rounded-md bg-slate-50 px-2.5 py-1 ring-1 ring-slate-100">
                                Released {{ row.paid_out_display }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs font-medium text-slate-500">
                            Updated {{ formatWhen(row.updated_at) }}
                        </p>
                    </Link>
                    <article
                        v-else-if="row.href"
                        class="group flex gap-4 rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md sm:p-5"
                    >
                        <UserProfileAvatar
                            v-if="row.freelancer_avatar_url"
                            :src="row.freelancer_avatar_url"
                            :name="row.freelancer_label || row.quest_title"
                            :alt="row.freelancer_label || row.quest_title"
                            frame-class="h-14 w-14 shrink-0 text-sm shadow-md ring-2 ring-white"
                        />
                        <div
                            v-else
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-600 to-primary-800 text-sm font-black text-white shadow-md ring-2 ring-white"
                            aria-hidden="true"
                        >
                            {{ (row.quest_title || 'Q').trim().slice(0, 1).toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-display text-base font-black text-slate-900 group-hover:text-primary-800">
                                {{ row.quest_title ?? 'Quest' }}
                            </p>
                            <p v-if="row.freelancer_label" class="mt-0.5 text-sm font-semibold text-slate-600">
                                {{ row.freelancer_label }}
                            </p>
                            <p v-else class="mt-0.5 text-[11px] font-black uppercase tracking-wide text-primary-800">
                                Your proposal
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-wide">
                                <span class="rounded-md bg-primary-50 px-2.5 py-1 text-primary-800 ring-1 ring-primary-100">
                                    {{ formatStatus(row.status) }}
                                </span>
                                <span v-if="row.quoted_amount_display" class="rounded-md bg-slate-50 px-2.5 py-1 ring-1 ring-slate-100">
                                    {{ row.quoted_amount_display }}
                                </span>
                                <span v-if="row.quest_status" class="rounded-md bg-slate-50 px-2.5 py-1 font-medium normal-case text-slate-600 ring-1 ring-slate-100">
                                    Quest {{ formatStatus(row.quest_status) }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs font-medium text-slate-500">
                                {{ formatWhen(row.submitted_at || row.updated_at) }}
                            </p>
                            <Link
                                :href="row.href"
                                class="mt-3 inline-flex rounded-full bg-primary-700 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800"
                            >
                                View proposal
                            </Link>
                        </div>
                    </article>
                    <article
                        v-else
                        class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-4 text-sm font-semibold text-amber-950"
                    >
                        Missing link — please refresh.
                    </article>
                </li>
            </ul>

            <p
                v-if="items.length === 0"
                class="mt-8 rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-12 text-center text-base font-semibold text-slate-600"
            >
                {{ emptyMessage }}
            </p>
            <p
                v-else-if="items.length && !displayItems.length"
                class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-10 text-center text-sm font-semibold text-slate-600"
            >
                No rows match your search.
            </p>

            <div ref="sentinel" class="h-4 w-full" aria-hidden="true" />

            <p v-if="loadingMore" class="mt-6 text-center text-sm font-semibold text-slate-500">
                Loading more…
            </p>
            <p v-else-if="!hasMore && items.length > 0" class="mt-6 text-center text-xs font-semibold text-slate-400">
                You have reached the end.
            </p>
        </div>
    </AppShell>
</template>

<script setup>
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import ListSearchSortBar from '@/Components/Ui/ListSearchSortBar.vue';
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    listKey: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        required: true,
    },
    emptyMessage: {
        type: String,
        required: true,
    },
    items: {
        type: Array,
        required: true,
    },
    meta: {
        type: Object,
        required: true,
    },
});

const items = ref([...props.items]);
const page = ref(props.meta.current_page ?? 1);
const hasMore = ref(!!props.meta.has_more);
const loadingMore = ref(false);
const sentinel = ref(null);
const search = ref('');
const sortKey = ref(props.listKey.includes('proposals') ? 'submitted_desc' : 'updated_desc');

const sortOptions = computed(() => {
    if (props.listKey.includes('proposals')) {
        return [
            { value: 'submitted_desc', label: 'Newest submitted' },
            { value: 'updated_desc', label: 'Recently updated' },
            { value: 'title_asc', label: 'Quest title A–Z' },
            { value: 'status_asc', label: 'Status A–Z' },
        ];
    }

    return [
        { value: 'updated_desc', label: 'Recently updated' },
        { value: 'title_asc', label: 'Title A–Z' },
        { value: 'status_asc', label: 'Status A–Z' },
    ];
});

let observer;

const displayItems = computed(() => {
    const q = search.value.trim().toLowerCase();
    let rows = items.value.slice();
    if (q) {
        rows = rows.filter((row) => {
            const blob = [
                row.title,
                row.quest_title,
                row.status,
                row.quest_status,
                row.freelancer_label,
                row.budget_display,
                row.paid_out_display,
                row.quoted_amount_display,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return blob.includes(q);
        });
    }
    const sk = sortKey.value;
    rows.sort((a, b) => {
        if (sk === 'submitted_desc') {
            return ts(b.submitted_at || b.updated_at) - ts(a.submitted_at || a.updated_at);
        }
        if (sk === 'title_asc') {
            const ta = String(a.title || a.quest_title || '');

            return ta.localeCompare(String(b.title || b.quest_title || ''));
        }
        if (sk === 'status_asc') {
            return String(a.status || '').localeCompare(String(b.status || ''));
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

watch(
    () => props.items,
    (v) => {
        items.value = [...v];
        page.value = props.meta.current_page ?? 1;
        hasMore.value = !!props.meta.has_more;
    },
);

const listUrl = computed(() =>
    route('dashboard.lists.show', {
        list: props.listKey,
    }),
);

async function loadMore() {
    if (loadingMore.value || !hasMore.value) {
        return;
    }
    loadingMore.value = true;
    try {
        const nextPage = page.value + 1;
        const { data } = await axios.get(listUrl.value, {
            params: { page: nextPage },
            headers: { Accept: 'application/json' },
        });
        items.value = items.value.concat(data.items);
        page.value = data.meta.current_page;
        hasMore.value = data.meta.has_more;
    } catch {
        hasMore.value = false;
    } finally {
        loadingMore.value = false;
    }
}

onMounted(async () => {
    await nextTick();
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting && hasMore.value && !loadingMore.value) {
                loadMore();
            }
        },
        { root: null, rootMargin: '120px', threshold: 0 },
    );
    if (sentinel.value) {
        observer.observe(sentinel.value);
    }
});

onUnmounted(() => observer?.disconnect());

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        const d = new Date(iso);

        return d.toLocaleString('en-NG', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}

function formatStatus(raw) {
    if (!raw || typeof raw !== 'string') {
        return '';
    }

    return raw.replace(/_/g, ' ');
}
</script>
