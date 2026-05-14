<template>
    <Head :title="profile.name + ' · Reviews'" />

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50">
        <header class="border-b border-slate-200/90 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6">
                <Link :href="route('freelancers.public', profile.slug)" class="inline-flex items-center gap-2 text-sm font-bold text-primary-700 hover:underline">
                    <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
                    Back to profile
                </Link>
                <Link href="/" class="font-display text-sm font-bold text-slate-600 hover:text-slate-900">
                    HustleSafe
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-8 px-4 py-10 sm:px-6 sm:py-12">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-center gap-4">
                    <UserProfileAvatar
                        :href="route('freelancers.public', profile.slug)"
                        :src="profile.avatar_url"
                        :name="profile.name"
                        :alt="profile.name"
                        frame-class="h-14 w-14 text-lg"
                        radius-class="rounded-2xl border border-slate-200 shadow-sm"
                    />
                    <div>
                        <h1 class="font-display text-2xl font-black text-slate-900 sm:text-3xl">
                            Reviews
                        </h1>
                        <p class="mt-1 text-sm font-semibold text-slate-600">
                            {{ profile.name }}
                        </p>
                    </div>
                </div>
            </div>

            <form
                class="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100 sm:flex-row sm:flex-wrap sm:items-end"
                @submit.prevent="applyFilters"
            >
                <div class="min-w-[12rem] flex-1">
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
                    <input
                        v-model="form.q"
                        type="search"
                        placeholder="Title or comment…"
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>
                <div class="w-full sm:w-44">
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Sort</label>
                    <UiSelect v-model="form.sort" class="mt-1" :options="reviewsSortOptions" placeholder="Sort" />
                </div>
                <div class="w-full sm:w-36">
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Stars</label>
                    <UiSelect v-model="form.rating" class="mt-1" :options="reviewsRatingOptions" placeholder="Any" />
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

            <div v-if="reviews.data.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-16 text-center">
                <p class="font-display text-lg font-bold text-slate-800">
                    No reviews match your filters
                </p>
                <p class="mt-2 text-sm font-medium text-slate-600">
                    Try clearing search or star filters.
                </p>
            </div>

            <ul v-else class="space-y-4">
                <li
                    v-for="r in reviews.data"
                    :key="r.id"
                    class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-50"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full bg-secondary-50 px-2.5 py-0.5 text-xs font-black text-secondary-800 ring-1 ring-secondary-100">
                            {{ r.rating ?? '—' }}/5
                        </span>
                        <span class="text-sm font-bold text-slate-900">{{ r.reviewer_label }}</span>
                        <span class="text-xs font-semibold text-slate-500">{{ formatWhen(r.created_at) }}</span>
                    </div>
                    <p v-if="r.title" class="mt-2 font-display text-lg font-bold text-slate-900">
                        {{ r.title }}
                    </p>
                    <p v-if="r.quest_title" class="mt-1 text-xs font-bold uppercase tracking-wide text-primary-700">
                        Quest: {{ r.quest_title }}
                    </p>
                    <p v-if="r.comment" class="mt-3 text-sm font-medium leading-relaxed text-slate-700">
                        {{ r.comment }}
                    </p>
                    <ul v-if="r.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                        <li v-for="(a, idx) in r.attachments" :key="idx">
                            <a
                                :href="a.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-800 ring-1 ring-slate-200/80 hover:bg-slate-200"
                            >
                                {{ a.original_name }}
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

            <nav v-if="reviews.links.length > 3" class="flex flex-wrap justify-center gap-2 pt-4" aria-label="Pagination">
                <template v-for="(link, i) in reviews.links" :key="i">
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
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const reviewsSortOptions = [
    { value: 'latest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'rating_high', label: 'Highest rating' },
    { value: 'rating_low', label: 'Lowest rating' },
];

const reviewsRatingOptions = [
    { value: '', label: 'Any' },
    { value: '1', label: '1★ only' },
    { value: '2', label: '2★ only' },
    { value: '3', label: '3★ only' },
    { value: '4', label: '4★ only' },
    { value: '5', label: '5★ only' },
];

const props = defineProps({
    profile: { type: Object, required: true },
    reviews: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const form = reactive({
    q: props.filters.q || '',
    sort: props.filters.sort || 'latest',
    rating: props.filters.rating != null && props.filters.rating !== '' ? String(props.filters.rating) : '',
});

function applyFilters() {
    router.get(
        route('freelancers.public.reviews', props.profile.slug),
        {
            q: form.q || undefined,
            sort: form.sort,
            rating: form.rating || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetFilters() {
    form.q = '';
    form.sort = 'latest';
    form.rating = '';
    applyFilters();
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return '';
    }
}
</script>
