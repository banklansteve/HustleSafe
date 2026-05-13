<template>
    <AppShell>
        <Head title="Portfolio gallery" />

        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-primary-700">
                    Showcase
                </p>
                <h1 class="font-display mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    Portfolio gallery
                </h1>
                <p class="mt-2 max-w-xl text-sm font-medium text-slate-600">
                    Real work from Safe Hustlers — filter by recency or popularity.
                </p>
                <div v-if="isFreelancer" class="mt-4 flex flex-wrap gap-3 text-xs font-bold">
                    <Link
                        :href="route('portfolio.manage')"
                        class="text-primary-700 underline-offset-4 hover:underline"
                    >
                        Manage your portfolio
                    </Link>
                    <span class="text-slate-300" aria-hidden="true">·</span>
                    <Link
                        :href="route('account.credentials.index')"
                        class="text-primary-700 underline-offset-4 hover:underline"
                    >
                        Credentials &amp; proof
                    </Link>
                </div>
            </div>
            <Link
                v-if="isFreelancer"
                :href="route('portfolio.manage')"
                class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                Manage yours
            </Link>
        </div>

        <form
            class="mb-8 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-100"
            @submit.prevent="apply"
        >
            <div class="min-w-[12rem] flex-1">
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
                <input
                    v-model="localQ"
                    type="search"
                    class="mt-1 w-full rounded-lg border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    placeholder="Title or description…"
                />
            </div>
            <div class="w-full sm:w-44">
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Sort</label>
                <select
                    v-model="localSort"
                    class="mt-1 w-full rounded-lg border-slate-200 text-sm font-bold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="latest">
                        Newest
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
                class="rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-primary-800"
            >
                Apply
            </button>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="p in portfolios.data"
                :key="p.id"
                :href="route('portfolio.show', p.slug)"
                class="group overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-lg"
            >
                <div class="relative aspect-[16/10] overflow-hidden bg-slate-100">
                    <img
                        v-if="p.cover_url"
                        :src="p.cover_url"
                        :alt="p.title"
                        class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                    />
                    <div v-else class="flex h-full items-center justify-center text-slate-400">
                        <span class="text-xs font-bold uppercase tracking-wide">No cover</span>
                    </div>
                    <span
                        class="absolute bottom-2 right-2 rounded-md bg-black/60 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white backdrop-blur-sm"
                    >
                        {{ formatCompactCount(p.favorites_count) }} ♥
                    </span>
                </div>
                <div class="p-4">
                    <p class="font-display text-base font-bold text-slate-900 group-hover:text-primary-800">
                        {{ p.title }}
                    </p>
                    <p class="mt-1 line-clamp-2 text-xs font-medium text-slate-600">
                        {{ p.description_excerpt }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span
                            v-if="p.category"
                            class="rounded-md bg-primary-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-800 ring-1 ring-primary-100"
                        >
                            {{ p.category.name }}
                        </span>
                        <span
                            v-if="p.subcategory"
                            class="rounded-md bg-slate-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700 ring-1 ring-slate-100"
                        >
                            {{ p.subcategory.name }}
                        </span>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-teal-600 text-xs font-black text-white"
                        >
                            {{ initials(p.owner?.name) }}
                        </span>
                        <span class="text-xs font-bold text-slate-700">{{ p.owner?.name }}</span>
                    </div>
                </div>
            </Link>
        </div>

        <p v-if="portfolios.data.length === 0" class="mt-10 rounded-xl border border-dashed border-slate-200 bg-slate-50 py-12 text-center text-sm font-semibold text-slate-600">
            No published portfolios match your filters yet.
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
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { formatCompactCount } from '@/utils/formatCompactCount';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();

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

const isFreelancer = computed(() => page.props.auth?.user?.role?.slug === 'freelancer');

function apply() {
    router.get(
        route('portfolio.index'),
        { q: localQ.value || undefined, sort: localSort.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function initials(name) {
    if (!name || typeof name !== 'string') {
        return '?';
    }
    const p = name.trim().split(/\s+/);

    return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?';
}
</script>
