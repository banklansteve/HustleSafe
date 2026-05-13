<template>
    <Head :title="profile.name + ' · Portfolio'" />

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50">
        <header class="border-b border-slate-200/90 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6">
                <Link :href="route('freelancers.public', profile.slug)" class="inline-flex items-center gap-2 text-sm font-bold text-primary-700 hover:underline">
                    <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
                    Back to profile
                </Link>
                <Link href="/" class="font-display text-sm font-bold text-slate-600 hover:text-slate-900">
                    HustleSafe
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-8 px-4 py-10 sm:px-6 sm:py-12">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-center gap-4">
                    <img
                        v-if="profile.avatar_url"
                        :src="profile.avatar_url"
                        :alt="profile.name"
                        class="h-14 w-14 rounded-2xl border border-slate-200 object-cover shadow-sm"
                    />
                    <div
                        v-else
                        class="flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 text-lg font-black text-slate-600"
                    >
                        {{ initials }}
                    </div>
                    <div>
                        <h1 class="font-display text-2xl font-black text-slate-900 sm:text-3xl">
                            Portfolio
                        </h1>
                        <p class="mt-1 text-sm font-semibold text-slate-600">
                            {{ profile.name }}
                        </p>
                    </div>
                </div>
            </div>

            <form
                class="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100 sm:flex-row sm:items-end"
                @submit.prevent="applyFilters"
            >
                <div class="min-w-[12rem] flex-1">
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
                    <input
                        v-model="form.q"
                        type="search"
                        placeholder="Title or description…"
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Sort</label>
                    <select
                        v-model="form.sort"
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="latest">
                            Newest
                        </option>
                        <option value="oldest">
                            Oldest
                        </option>
                        <option value="popular">
                            Most liked
                        </option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-slate-800"
                    >
                        Apply
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>

            <div v-if="portfolios.data.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-16 text-center">
                <p class="font-display text-lg font-bold text-slate-800">
                    No portfolio pieces found
                </p>
                <p class="mt-2 text-sm font-medium text-slate-600">
                    Try another search term.
                </p>
            </div>

            <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="p in portfolios.data"
                    :key="p.slug"
                    :href="route('portfolio.show', p.slug)"
                    class="group overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100 transition hover:-translate-y-1 hover:shadow-xl"
                >
                    <div class="aspect-[16/10] overflow-hidden bg-slate-100">
                        <img
                            v-if="p.cover_url"
                            :src="p.cover_url"
                            :alt="p.title"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                        />
                        <div v-else class="flex h-full items-center justify-center text-slate-400">
                            <PhotoIcon class="h-12 w-12" aria-hidden="true" />
                        </div>
                    </div>
                    <div class="p-5">
                        <p class="font-display text-lg font-bold text-slate-900 line-clamp-2">
                            {{ p.title }}
                        </p>
                        <p v-if="p.description_excerpt" class="mt-2 line-clamp-2 text-sm font-medium text-slate-600">
                            {{ p.description_excerpt }}
                        </p>
                        <div class="mt-4 flex flex-wrap items-center gap-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            <span>{{ formatCompact(p.favorites_count) }} likes</span>
                            <span v-if="p.published_at">{{ formatWhen(p.published_at) }}</span>
                        </div>
                    </div>
                </Link>
            </div>

            <nav v-if="portfolios.links.length > 3" class="flex flex-wrap justify-center gap-2 pt-4" aria-label="Pagination">
                <template v-for="(link, i) in portfolios.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="min-w-[2.5rem] rounded-xl px-3 py-2 text-center text-sm font-bold transition"
                        :class="
                            link.active
                                ? 'bg-primary-600 text-white shadow-md'
                                : 'border border-slate-200 bg-white text-slate-800 hover:border-primary-200 hover:bg-primary-50'
                        "
                        preserve-scroll
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="min-w-[2.5rem] cursor-not-allowed rounded-xl px-3 py-2 text-center text-sm font-bold text-slate-400"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </main>
    </div>
</template>

<script setup>
import { formatCompactCount } from '@/utils/formatCompactCount';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { PhotoIcon } from '@heroicons/vue/24/solid';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    profile: { type: Object, required: true },
    portfolios: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const form = reactive({
    q: props.filters.q || '',
    sort: props.filters.sort || 'latest',
});

const initials = computed(() => {
    const n = props.profile.name || '';
    const parts = n.trim().split(/\s+/);

    return ((parts[0]?.[0] || 'H') + (parts[1]?.[0] || '')).toUpperCase();
});

function applyFilters() {
    router.get(
        route('freelancers.public.portfolios', props.profile.slug),
        {
            q: form.q || undefined,
            sort: form.sort,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetFilters() {
    form.q = '';
    form.sort = 'latest';
    applyFilters();
}

function formatCompact(n) {
    return formatCompactCount(Number(n) || 0);
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleDateString('en-NG', { month: 'short', year: 'numeric' });
    } catch {
        return '';
    }
}
</script>
