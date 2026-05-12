<template>
    <AppShell>
        <Head :title="title" />

        <div class="mx-auto max-w-3xl">
            <Link
                :href="route('dashboard')"
                class="mb-6 inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
                Back to home
            </Link>

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

            <ul class="mt-5 space-y-3">
                <li v-for="(row, i) in items" :key="row.kind + '-' + row.id + '-' + i">
                    <article
                        class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-100/90 sm:p-5"
                    >
                        <template v-if="row.kind === 'quest'">
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
                        </template>
                        <template v-else>
                            <p class="font-display text-base font-bold text-slate-900">
                                {{ row.quest_title ?? 'Quest' }}
                            </p>
                            <p v-if="row.freelancer_label" class="mt-1 text-sm font-semibold text-slate-600">
                                From {{ row.freelancer_label }}
                            </p>
                            <p class="mt-2 text-xs font-bold uppercase tracking-wide text-primary-800">
                                {{ formatStatus(row.status) }}
                            </p>
                            <p v-if="row.quest_status" class="mt-1 text-xs font-medium text-slate-500">
                                Quest · {{ formatStatus(row.quest_status) }}
                            </p>
                            <p class="mt-2 text-xs font-medium text-slate-500">
                                {{ formatWhen(row.updated_at) }}
                            </p>
                        </template>
                    </article>
                </li>
            </ul>

            <p
                v-if="items.length === 0"
                class="mt-8 rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-12 text-center text-base font-semibold text-slate-600"
            >
                {{ emptyMessage }}
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
import AppShell from '@/Layouts/AppShell.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
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
let observer;

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
