<template>
    <AppShell>
        <Head title="Create a quest" />

        <div class="mx-auto max-w-2xl">
            <Link
                :href="route('dashboard')"
                class="mb-6 inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
                Back to home
            </Link>

            <div class="rounded-xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/90 to-primary-50/40 p-6 shadow-sm ring-1 ring-slate-100 sm:p-10">
                <p class="text-xs font-bold uppercase tracking-wider text-primary-700">
                    Coming soon
                </p>
                <h1 class="font-display mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    {{ copy.title }}
                </h1>
                <p class="mt-4 text-sm font-medium leading-relaxed text-slate-600 sm:text-base">
                    {{ copy.lead }}
                </p>
                <ul class="mt-6 space-y-3">
                    <li
                        v-for="(b, i) in copy.bullets"
                        :key="i"
                        class="flex gap-3 text-sm font-semibold text-slate-700"
                    >
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-primary-600 text-xs font-black text-white">
                            {{ i + 1 }}
                        </span>
                        <span>{{ b }}</span>
                    </li>
                </ul>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link
                        :href="route('quests.explore')"
                        class="inline-flex items-center rounded-xl bg-primary-700 px-5 py-3 text-sm font-bold text-white shadow-md shadow-primary-900/15 transition hover:bg-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    >
                        Browse open quests
                    </Link>
                    <Link
                        :href="route('profile.edit')"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    >
                        Prepare your profile
                    </Link>
                </div>
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
});
</script>
