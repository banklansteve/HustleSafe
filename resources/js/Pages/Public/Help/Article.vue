<template>
    <AppShell>
        <Head :title="article.title" />

        <article class="mx-auto max-w-3xl space-y-8 pb-16 pt-4">
            <div>
                <Link :href="route('help.index')" class="text-xs font-black uppercase tracking-wide text-primary-700 hover:underline">
                    ← Help centre
                </Link>
                <p class="mt-4 text-[10px] font-black uppercase tracking-[0.2em] text-primary-700">{{ article.audience_label }}</p>
                <h1 class="font-display mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                    {{ article.title }}
                </h1>
                <p class="mt-3 text-sm font-semibold leading-relaxed text-slate-600">{{ article.summary }}</p>
                <p class="mt-2 text-xs font-semibold text-slate-400">{{ article.read_minutes }} min read</p>
            </div>

            <section v-if="article.steps?.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-[0.18em] text-slate-500">Step by step</h2>
                <ol class="mt-5 space-y-5">
                    <li v-for="(step, index) in article.steps" :key="index" class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-700 text-sm font-black text-white">
                            {{ index + 1 }}
                        </span>
                        <div class="min-w-0 pt-0.5">
                            <h3 class="text-sm font-black text-slate-900">{{ step.title }}</h3>
                            <p class="mt-1 text-sm font-medium leading-relaxed text-slate-700">{{ step.body }}</p>
                        </div>
                    </li>
                </ol>
            </section>

            <section v-if="article.what_happens_next?.length" class="rounded-2xl border border-teal-200/80 bg-gradient-to-br from-teal-50/90 to-white p-5 ring-1 ring-teal-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-[0.18em] text-teal-800">What happens next</h2>
                <ul class="mt-4 space-y-3">
                    <li v-for="(line, i) in article.what_happens_next" :key="i" class="flex gap-3 text-sm font-semibold leading-relaxed text-slate-800">
                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-teal-500" aria-hidden="true" />
                        <span>{{ line }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="article.faqs?.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-[0.18em] text-slate-500">Common questions</h2>
                <div class="mt-4 divide-y divide-slate-100">
                    <details v-for="(faq, i) in article.faqs" :key="i" class="group py-4">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-4 text-left text-sm font-black text-slate-900">
                            {{ faq.question }}
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-slate-500 group-open:bg-primary-100 group-open:text-primary-800">Answer</span>
                        </summary>
                        <p class="mt-3 text-sm font-medium leading-relaxed text-slate-700">{{ faq.answer }}</p>
                    </details>
                </div>
            </section>

            <section v-if="article.related_articles?.length" class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-[0.18em] text-slate-600">Related articles</h2>
                <ul class="mt-4 space-y-3">
                    <li v-for="related in article.related_articles" :key="related.slug">
                        <Link
                            :href="related.href"
                            class="group flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-primary-800 transition hover:border-primary-200 hover:bg-primary-50/50"
                        >
                            {{ related.title }}
                            <span class="text-xs text-slate-400 group-hover:text-primary-600">→</span>
                        </Link>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border border-primary-100 bg-primary-50/60 p-5 text-sm font-semibold text-slate-700">
                <p class="font-black text-slate-900">Official policies</p>
                <p class="mt-2 leading-relaxed">
                    For legal detail, see our
                    <a :href="route('legal.terms')" class="font-black text-primary-800 underline" target="_blank" rel="noopener noreferrer">Terms</a>,
                    <a :href="route('legal.escrow')" class="font-black text-primary-800 underline" target="_blank" rel="noopener noreferrer">Escrow Policy</a>, and
                    <a :href="route('legal.dispute')" class="font-black text-primary-800 underline" target="_blank" rel="noopener noreferrer">Dispute Policy</a>.
                </p>
            </section>
        </article>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    article: { type: Object, required: true },
});
</script>

<style scoped>
summary::-webkit-details-marker {
    display: none;
}
</style>
