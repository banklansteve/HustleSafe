<template>
    <AppShell>
        <Head title="Help centre" />

        <div class="mx-auto max-w-5xl space-y-8 pb-12">
            <section class="rounded-[2rem] border border-primary-100 bg-gradient-to-br from-primary-50 via-white to-teal-50/80 p-6 shadow-sm sm:p-8">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Help centre</p>
                <h1 class="mt-3 font-display text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                    Answers for your first quest, proposal, or payout.
                </h1>
                <p class="mt-3 max-w-2xl text-sm font-semibold leading-relaxed text-slate-600">
                    Step-by-step guides written for Nigerian sponsors and hustlers — plain language, no jargon. Search or pick a topic below.
                </p>
                <form class="mt-6 flex flex-col gap-3 sm:flex-row" @submit.prevent="search">
                    <input
                        v-model="searchQuery"
                        type="search"
                        class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold shadow-sm focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100"
                        placeholder="Try “fund escrow”, “withdraw”, “dispute”…"
                    />
                    <button type="submit" class="rounded-2xl bg-primary-700 px-6 py-3 text-sm font-black text-white shadow-sm shadow-primary-900/20">
                        Search
                    </button>
                </form>
            </section>

            <section v-if="!query && featuredArticles.length" class="space-y-4">
                <h2 class="font-display text-lg font-black text-slate-900">Start here</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="article in featuredArticles"
                        :key="article.slug"
                        :href="route('help.show', article.slug)"
                        class="group rounded-2xl border border-primary-100 bg-white p-5 shadow-sm ring-1 ring-primary-50 transition hover:border-primary-200 hover:shadow-md"
                    >
                        <p class="text-[10px] font-black uppercase tracking-wide text-primary-700">{{ article.audience_label }}</p>
                        <h3 class="mt-1 font-display text-lg font-black text-slate-950 group-hover:text-primary-800">{{ article.title }}</h3>
                        <p class="mt-2 line-clamp-2 text-sm font-semibold text-slate-600">{{ article.summary }}</p>
                        <p class="mt-3 text-xs font-bold text-primary-700">{{ article.read_minutes }} min read →</p>
                    </Link>
                </div>
            </section>

            <section v-if="articles.length" class="space-y-4">
                <h2 class="font-display text-lg font-black text-slate-900">
                    {{ query ? `Results for “${query}”` : 'All guides' }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="article in articles"
                        :key="article.slug"
                        :href="route('help.show', article.slug)"
                        class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-primary-200 hover:shadow-md"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-600">
                                {{ article.audience_label }}
                            </span>
                            <span class="text-[10px] font-semibold text-slate-400">{{ article.read_minutes }} min</span>
                        </div>
                        <h3 class="mt-3 font-display text-lg font-black leading-snug text-slate-950 group-hover:text-primary-800">
                            {{ article.title }}
                        </h3>
                        <p class="mt-2 flex-1 text-sm font-semibold leading-relaxed text-slate-600">{{ article.summary }}</p>
                        <span class="mt-4 text-xs font-black uppercase tracking-wide text-primary-700">Read guide →</span>
                    </Link>
                </div>
            </section>

            <section v-else class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-950">
                <p class="font-black">No guides matched “{{ query }}”.</p>
                <p class="mt-2 text-sm font-semibold">Try another search or browse all articles above. We log searches to improve this page.</p>
                <button type="button" class="mt-4 text-sm font-black text-primary-800 underline" @click="clearSearch">Clear search</button>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5 text-sm font-semibold text-slate-700">
                <p class="font-black text-slate-900">Still stuck?</p>
                <p class="mt-2 leading-relaxed">
                    Use the support chat bubble when logged in, or email us from the contact details in our
                    <Link :href="route('legal.terms')" class="font-black text-primary-800 underline">Terms of Service</Link>.
                </p>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    articles: { type: Array, default: () => [] },
    query: { type: String, default: '' },
    audience: { type: String, default: 'all' },
    featured_slugs: { type: Array, default: () => [] },
});

const searchQuery = ref(props.query);

const featuredArticles = computed(() => {
    const slugs = props.featured_slugs ?? [];

    return props.articles.filter((a) => slugs.includes(a.slug));
});

function search() {
    router.get(route('help.index'), { q: searchQuery.value }, { preserveState: true });
}

function clearSearch() {
    searchQuery.value = '';
    router.get(route('help.index'));
}
</script>
