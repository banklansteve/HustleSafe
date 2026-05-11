<template>
    <div
        class="relative flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-slate-50 via-white to-teal-50/50 px-4 py-12"
    >
        <div class="pointer-events-none absolute inset-0 opacity-90">
            <div class="absolute left-1/4 top-20 h-64 w-64 rounded-full bg-primary-200/35 blur-3xl" />
            <div class="absolute bottom-10 right-1/4 h-80 w-80 rounded-full bg-teal-200/40 blur-3xl" />
        </div>
        <Link
            href="/"
            class="relative mb-8 inline-flex items-center gap-2 rounded-xl px-1 py-0.5 text-slate-900 transition hover:bg-white/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
            <span
                class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-600 text-sm font-black text-white shadow-sm ring-1 ring-primary-500/30"
            >
                HS
            </span>
            <span class="font-display text-xl font-bold tracking-tight">HustleSafe</span>
        </Link>
        <div
            class="relative w-full max-w-md rounded-3xl bg-white p-8 shadow-xl shadow-slate-300/45 ring-1 ring-slate-200/90"
        >
            <slot />
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>
