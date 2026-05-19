<template>
    <AppShell>
        <div class="mx-auto max-w-5xl space-y-8">
            <section class="rounded-[2rem] border border-primary-100 bg-gradient-to-br from-primary-50 via-white to-amber-50 p-6 shadow-sm sm:p-8">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Help centre</p>
                <h1 class="mt-3 font-display text-4xl font-black tracking-tight text-slate-950">Find quick answers about using HustleSafe.</h1>
                <p class="mt-3 max-w-2xl text-sm font-semibold leading-7 text-slate-600">Search by question or everyday words like “pay freelancer”, “approve milestone”, or “verify account”.</p>
                <form class="mt-6 flex flex-col gap-3 sm:flex-row" @submit.prevent="search">
                    <input v-model="searchQuery" class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold shadow-sm focus:border-primary-400 focus:outline-none focus:ring-4 focus:ring-primary-100" placeholder="Search help articles" />
                    <button type="submit" class="rounded-2xl bg-primary-700 px-6 py-3 text-sm font-black text-white shadow-sm shadow-primary-900/20">Search</button>
                </form>
            </section>

            <section v-if="sections.length" class="space-y-6">
                <article v-for="section in sections" :key="section.id" class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="font-display text-2xl font-black text-slate-950">{{ section.title }}</h2>
                    <div class="mt-4 divide-y divide-slate-100">
                        <details v-for="faq in section.faqs" :key="faq.id" class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left font-black text-slate-900">
                                {{ faq.question }}
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-500 group-open:bg-primary-100 group-open:text-primary-800">Open</span>
                            </summary>
                            <div class="prose prose-slate mt-3 max-w-none text-sm font-semibold leading-7 text-slate-600" v-html="faq.answer"></div>
                        </details>
                    </div>
                </article>
            </section>

            <section v-else class="rounded-[2rem] border border-amber-200 bg-amber-50 p-6 text-amber-950">
                <p class="font-black">No answers found for “{{ query }}”.</p>
                <p class="mt-2 text-sm font-semibold">We logged this so the team can improve the help centre.</p>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    sections: { type: Array, default: () => [] },
    query: { type: String, default: '' },
});

const searchQuery = ref(props.query);

function search() {
    router.get(route('help.index'), { q: searchQuery.value }, { preserveState: true });
}
</script>
