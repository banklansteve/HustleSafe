<template>
    <AppShell>
        <Head title="Improve your trust score" />

        <div class="mx-auto max-w-3xl">
            <Link
                :href="route('dashboard')"
                class="mb-6 inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
                Back to home
            </Link>

            <header class="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-wider text-primary-700">
                    Freelancers
                </p>
                <h1 class="font-display mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    {{ copy.title }}
                </h1>
                <p class="mt-3 text-sm font-medium leading-relaxed text-slate-600 sm:text-base">
                    {{ copy.intro }}
                </p>
            </header>

            <div class="mt-6 space-y-6">
                <section
                    v-for="(block, bi) in sections"
                    :key="bi"
                    class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6"
                >
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        {{ block.heading }}
                    </h2>
                    <ul class="mt-4 space-y-4">
                        <li
                            v-for="(item, ii) in block.items"
                            :key="ii"
                            class="rounded-lg border border-slate-100 bg-slate-50/60 p-4 sm:p-5"
                        >
                            <p class="font-bold text-slate-900">
                                {{ item.title }}
                            </p>
                            <p class="mt-2 text-sm font-medium leading-relaxed text-slate-600">
                                {{ item.body }}
                            </p>
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                class="mt-3 inline-flex text-sm font-bold text-primary-700 hover:text-primary-800"
                            >
                                {{ item.cta ?? 'Open' }}
                                <span class="ml-1" aria-hidden="true">→</span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    copy: {
        type: Object,
        required: true,
    },
    sections: {
        type: Array,
        required: true,
    },
});
</script>
