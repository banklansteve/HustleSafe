<template>
    <AppShell>
        <Head title="Your portfolio" />

        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <Link
                    :href="route('portfolio.index')"
                    class="text-xs font-bold uppercase tracking-wide text-primary-700 hover:text-primary-800"
                >
                    ← Public gallery
                </Link>
                <h1 class="font-display mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    Your portfolio
                </h1>
                <p class="mt-2 max-w-xl text-sm font-medium text-slate-600">
                    Drafts stay private. Published pieces appear in the gallery and on your profile trail.
                </p>
                <div class="mt-4 flex flex-wrap gap-3 text-xs font-bold">
                    <Link
                        :href="route('portfolio.index')"
                        class="text-primary-700 underline-offset-4 hover:underline"
                    >
                        Public gallery
                    </Link>
                    <span class="text-slate-300" aria-hidden="true">·</span>
                    <Link
                        :href="route('account.credentials.index')"
                        class="text-primary-700 underline-offset-4 hover:underline"
                    >
                        Credentials &amp; proof
                    </Link>
                    <span class="text-slate-300" aria-hidden="true">·</span>
                    <Link
                        :href="route('account.show', { tab: 'portfolio' })"
                        class="text-primary-700 underline-offset-4 hover:underline"
                    >
                        Account portfolio tab
                    </Link>
                </div>
            </div>
            <Link
                :href="route('portfolio.create')"
                class="inline-flex items-center rounded-xl bg-primary-700 px-5 py-3 text-sm font-bold text-white shadow-md transition hover:bg-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                New piece
            </Link>
        </div>

        <form
            class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-100"
            @submit.prevent="apply"
        >
            <div class="min-w-[10rem] flex-1">
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
                <input
                    v-model="localQ"
                    type="search"
                    class="mt-1 w-full rounded-lg border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    placeholder="Search titles…"
                />
            </div>
            <div class="w-full sm:w-36">
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>
                <select
                    v-model="localStatus"
                    class="mt-1 w-full rounded-lg border-slate-200 text-sm font-bold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="all">
                        All
                    </option>
                    <option value="draft">
                        Draft
                    </option>
                    <option value="published">
                        Published
                    </option>
                </select>
            </div>
            <div class="w-full sm:w-40">
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Sort</label>
                <select
                    v-model="localSort"
                    class="mt-1 w-full rounded-lg border-slate-200 text-sm font-bold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="latest">
                        Recently updated
                    </option>
                    <option value="popular">
                        Most liked
                    </option>
                    <option value="oldest">
                        Oldest
                    </option>
                </select>
            </div>
            <button
                type="submit"
                class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800"
            >
                Apply
            </button>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="p in portfolios.data"
                :key="p.id"
                class="group relative overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-100"
            >
                <div class="relative aspect-[16/10] overflow-hidden bg-slate-100">
                    <img
                        v-if="p.cover_url"
                        :src="p.cover_url"
                        :alt="p.title"
                        class="h-full w-full object-cover"
                    />
                    <div v-else class="flex h-full items-center justify-center text-slate-400">
                        <span class="text-xs font-bold uppercase">No cover</span>
                    </div>
                    <div
                        class="absolute inset-0 flex items-center justify-center gap-2 bg-slate-900/0 opacity-0 transition group-hover:bg-slate-900/45 group-hover:opacity-100"
                    >
                        <Link
                            :href="route('portfolio.edit', p.slug)"
                            class="rounded-lg bg-white px-4 py-2 text-xs font-bold text-slate-900 shadow-lg ring-1 ring-slate-200 transition hover:bg-primary-50"
                        >
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-lg transition hover:bg-rose-700"
                            @click="openDelete(p)"
                        >
                            Delete
                        </button>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1"
                            :class="
                                p.status === 'published'
                                    ? 'bg-emerald-50 text-emerald-800 ring-emerald-100'
                                    : 'bg-slate-100 text-slate-700 ring-slate-200'
                            "
                        >
                            {{ p.status }}
                        </span>
                        <span
                            v-if="p.category"
                            class="rounded-md bg-primary-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-800 ring-1 ring-primary-100"
                        >
                            {{ p.category }}
                        </span>
                        <span
                            v-if="p.subcategory"
                            class="rounded-md bg-slate-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700 ring-1 ring-slate-100"
                        >
                            {{ p.subcategory }}
                        </span>
                    </div>
                    <Link
                        :href="route('portfolio.show', p.slug)"
                        class="mt-2 block font-display text-base font-bold text-slate-900 hover:text-primary-800"
                    >
                        {{ p.title }}
                    </Link>
                    <p class="mt-1 text-[11px] font-semibold text-slate-500">
                        {{ formatCompactCount(p.favorites_count) }} likes · updated {{ formatWhen(p.updated_at) }}
                    </p>
                </div>
            </article>
        </div>

        <p v-if="portfolios.data.length === 0" class="mt-10 rounded-xl border border-dashed border-slate-200 bg-slate-50 py-12 text-center text-sm font-semibold text-slate-600">
            Nothing here yet — craft your first showcase.
        </p>

        <nav v-if="portfolios.links.length > 3" class="mt-10 flex flex-wrap justify-center gap-2">
            <template v-for="(link, i) in portfolios.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="min-w-[2.5rem] rounded-lg px-3 py-2 text-center text-sm font-bold transition"
                    :class="
                        link.active
                            ? 'bg-primary-700 text-white'
                            : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50'
                    "
                >
                    <span v-html="link.label" />
                </Link>
                <span
                    v-else
                    class="min-w-[2.5rem] cursor-default rounded-lg px-3 py-2 text-center text-sm font-bold text-slate-400"
                    v-html="link.label"
                />
            </template>
        </nav>

        <PortfolioConfirmModal
            :open="deleteTarget !== null"
            title="Delete this portfolio?"
            message="This removes all uploaded files and likes. This cannot be undone."
            confirm-label="Delete"
            :processing="deleting"
            @cancel="deleteTarget = null"
            @confirm="confirmDelete"
        />
    </AppShell>
</template>

<script setup>
import PortfolioConfirmModal from '@/Components/Portfolio/PortfolioConfirmModal.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { formatCompactCount } from '@/utils/formatCompactCount';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    portfolios: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const localQ = ref(props.filters.q ?? '');
const localSort = ref(props.filters.sort ?? 'latest');
const localStatus = ref(props.filters.status ?? 'all');

const deleteTarget = ref(null);
const deleting = ref(false);

function apply() {
    router.get(
        route('portfolio.manage'),
        {
            q: localQ.value || undefined,
            sort: localSort.value,
            status: localStatus.value === 'all' ? undefined : localStatus.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function openDelete(p) {
    deleteTarget.value = p;
}

function confirmDelete() {
    if (!deleteTarget.value) {
        return;
    }
    deleting.value = true;
    router.delete(route('portfolio.destroy', deleteTarget.value.slug), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleDateString('en-NG', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return '';
    }
}
</script>
