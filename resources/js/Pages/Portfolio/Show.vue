<template>
    <AppShell>
        <Head :title="portfolio.title" />

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link :href="route('portfolio.index')" class="text-xs font-bold uppercase tracking-wide text-primary-700 hover:text-primary-800">
                    ← Gallery
                </Link>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span
                        class="rounded-md px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ring-1"
                        :class="
                            portfolio.status === 'published'
                                ? 'bg-emerald-50 text-emerald-800 ring-emerald-100'
                                : 'bg-amber-50 text-amber-900 ring-amber-100'
                        "
                    >
                        {{ portfolio.status }}
                    </span>
                    <span
                        v-if="portfolio.category"
                        class="rounded-md bg-primary-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-primary-800 ring-1 ring-primary-100"
                    >
                        {{ portfolio.category.name }}
                    </span>
                    <span
                        v-if="portfolio.subcategory"
                        class="rounded-md bg-slate-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700 ring-1 ring-slate-100"
                    >
                        {{ portfolio.subcategory.name }}
                    </span>
                </div>
                <h1 class="font-display mt-3 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl lg:text-4xl">
                    {{ portfolio.title }}
                </h1>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-teal-600 text-sm font-black text-white shadow-md"
                        >
                            {{ initials(portfolio.owner?.name) }}
                        </span>
                        <div>
                            <p class="text-sm font-bold text-slate-900">
                                {{ portfolio.owner?.name }}
                            </p>
                            <Link
                                v-if="portfolio.owner?.slug"
                                :href="route('freelancers.public', portfolio.owner.slug)"
                                class="text-xs font-bold text-primary-700 hover:text-primary-800"
                            >
                                View public profile
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <template v-if="isOwner">
                    <Link
                        :href="route('portfolio.edit', portfolio.slug)"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-slate-50"
                    >
                        Edit
                    </Link>
                    <button
                        type="button"
                        class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-rose-700"
                        @click="showDelete = true"
                    >
                        Delete
                    </button>
                </template>
                <PortfolioFavoriteButton
                    v-if="portfolio.status === 'published'"
                    :portfolio-slug="portfolio.slug"
                    :initial-favorited="favorited"
                    :initial-count="portfolio.favorites_count"
                    :disabled="isOwner"
                    :is-authenticated="isAuthed"
                />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start lg:gap-8">
            <div class="min-w-0 space-y-6">
                <div v-if="portfolio.files?.length" class="overflow-hidden rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-100 sm:p-5">
                    <h2 class="font-display text-sm font-bold uppercase tracking-wide text-slate-500">
                        Media
                    </h2>
                    <PortfolioGallery :files="portfolio.files" class="mt-4" />
                </div>
                <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        About this work
                    </h2>
                    <p class="mt-3 whitespace-pre-line text-sm font-medium leading-relaxed text-slate-700">
                        {{ portfolio.description }}
                    </p>
                </div>
                <div
                    v-if="portfolio.review"
                    class="rounded-xl border border-secondary-100 bg-gradient-to-br from-secondary-50/80 to-white p-5 shadow-sm ring-1 ring-secondary-100/80 sm:p-6"
                >
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Client review
                    </h2>
                    <p class="mt-1 text-xs font-bold uppercase tracking-wide text-secondary-800">
                        From {{ portfolio.review.reviewer_label }} on HustleSafe
                    </p>
                    <div v-if="portfolio.review.rating != null" class="mt-3 text-lg font-black text-secondary-700">
                        {{ '★'.repeat(portfolio.review.rating) }}{{ '☆'.repeat(5 - portfolio.review.rating) }}
                    </div>
                    <p v-if="portfolio.review.title" class="mt-2 font-bold text-slate-900">
                        {{ portfolio.review.title }}
                    </p>
                    <p v-if="portfolio.review.comment" class="mt-2 text-sm font-medium leading-relaxed text-slate-700">
                        {{ portfolio.review.comment }}
                    </p>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-xl border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/80 p-5 shadow-sm ring-1 ring-slate-100">
                    <h3 class="font-display text-sm font-bold text-slate-900">
                        Snapshot
                    </h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                            <dt class="font-semibold text-slate-500">
                                Timeline
                            </dt>
                            <dd class="text-right font-bold text-slate-800">
                                <span v-if="portfolio.started_at">{{ formatWhen(portfolio.started_at) }}</span>
                                <span v-else>—</span>
                                <span class="text-slate-400"> → </span>
                                <span v-if="portfolio.completed_at">{{ formatWhen(portfolio.completed_at) }}</span>
                                <span v-else>—</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                            <dt class="font-semibold text-slate-500">
                                Showcase budget
                            </dt>
                            <dd class="text-right font-bold text-slate-800">
                                {{ portfolio.project_cost_display }}
                            </dd>
                        </div>
                        <div v-if="portfolio.quest" class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                            <dt class="font-semibold text-slate-500">
                                Linked quest
                            </dt>
                            <dd class="text-right text-xs font-bold text-slate-800">
                                {{ portfolio.quest.title }}
                            </dd>
                        </div>
                        <div v-if="portfolio.quest?.budget_display" class="flex justify-between gap-2 pb-1">
                            <dt class="font-semibold text-slate-500">
                                Quest budget
                            </dt>
                            <dd class="text-right font-bold text-slate-800">
                                {{ portfolio.quest.budget_display }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div
                    v-if="portfolio.status === 'draft' && isOwner"
                    class="rounded-xl border border-amber-200 bg-amber-50/90 p-4 text-sm font-semibold text-amber-950 ring-1 ring-amber-100"
                >
                    Only you can see this draft. Publish from the editor when you are ready.
                </div>
            </aside>
        </div>

        <PortfolioConfirmModal
            :open="showDelete"
            title="Delete portfolio?"
            message="All files and like counts for this piece will be removed permanently."
            confirm-label="Delete"
            :processing="deleting"
            @cancel="showDelete = false"
            @confirm="doDelete"
        />
    </AppShell>
</template>

<script setup>
import PortfolioConfirmModal from '@/Components/Portfolio/PortfolioConfirmModal.vue';
import PortfolioFavoriteButton from '@/Components/Portfolio/PortfolioFavoriteButton.vue';
import PortfolioGallery from '@/Components/Portfolio/PortfolioGallery.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    portfolio: {
        type: Object,
        required: true,
    },
    isOwner: {
        type: Boolean,
        default: false,
    },
    favorited: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const isAuthed = computed(() => !!page.props.auth?.user);

const showDelete = ref(false);
const deleting = ref(false);

function doDelete() {
    deleting.value = true;
    router.delete(route('portfolio.destroy', props.portfolio.slug), {
        onFinish: () => {
            deleting.value = false;
            showDelete.value = false;
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

function initials(name) {
    if (!name || typeof name !== 'string') {
        return '?';
    }
    const p = name.trim().split(/\s+/);

    return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?';
}
</script>
