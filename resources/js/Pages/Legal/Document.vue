<template>
    <AppShell>
        <Head :title="title" />

        <article class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">
                Legal
            </p>
            <h1 class="font-display mt-2 text-3xl font-black tracking-tight text-slate-900">
                {{ title }}
            </h1>
            <p class="mt-2 text-sm font-semibold text-slate-500">
                {{ subtitle }}
            </p>
            <div class="mt-8 space-y-5 text-sm font-medium leading-relaxed text-slate-700">
                <p v-for="(p, i) in paragraphs" :key="i" class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                    {{ p }}
                </p>
            </div>
            <p class="mt-10 text-xs font-semibold text-slate-500">
                This summary is for convenience. Your agreements inside HustleSafe may include additional terms for specific programs.
            </p>
        </article>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
    paragraphs: { type: Array, required: true },
});
</script>
