<template>
    <div class="min-h-screen bg-slate-950 text-slate-100">
        <div class="flex min-h-screen flex-col lg:flex-row">
            <aside
                class="border-b border-white/10 bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 px-4 py-4 lg:w-72 lg:shrink-0 lg:border-b-0 lg:border-r lg:border-white/10 lg:px-5 lg:py-8"
                aria-label="Operations console navigation"
            >
                <div class="flex items-center justify-between gap-3 lg:block">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-amber-300/90">HustleSafe</p>
                        <p class="font-display mt-1 text-lg font-black tracking-tight text-white">Operations console</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Staff · view, export & limited controls</p>
                    </div>
                    <Link
                        href="/dashboard"
                        prefetch="false"
                        class="rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-white/10 lg:hidden"
                    >
                        Exit
                    </Link>
                </div>
                <nav class="mt-6 flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:gap-0.5 lg:overflow-visible lg:pb-0" aria-label="Primary">
                    <p class="hidden px-3 pb-2 text-[10px] font-black uppercase tracking-wider text-slate-600 lg:block">
                        Workspace
                    </p>
                    <Link
                        v-for="item in primaryNav"
                        :key="item.href"
                        :href="item.href"
                        prefetch="false"
                        class="whitespace-nowrap rounded-xl px-3 py-2 text-sm font-bold transition lg:px-4 lg:py-2.5"
                        :class="isActive(item) ? 'bg-amber-500/15 text-amber-50 ring-1 ring-amber-400/35' : 'text-slate-400 hover:bg-white/5 hover:text-white'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
                <nav class="mt-4 flex gap-2 overflow-x-auto pb-1 lg:mt-6 lg:flex-col lg:gap-0.5 lg:overflow-visible lg:pb-0" aria-label="Disputes">
                    <p class="hidden px-3 pb-2 text-[10px] font-black uppercase tracking-wider text-slate-600 lg:block">
                        Disputes
                    </p>
                    <Link
                        v-for="item in secondaryNav"
                        :key="item.href"
                        :href="item.href"
                        prefetch="false"
                        class="whitespace-nowrap rounded-xl px-3 py-2 text-sm font-bold transition lg:px-4 lg:py-2.5"
                        :class="isActive(item) ? 'bg-sky-500/15 text-sky-50 ring-1 ring-sky-400/35' : 'text-slate-400 hover:bg-white/5 hover:text-white'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
                <Link
                    href="/dashboard"
                    prefetch="false"
                    class="mt-8 hidden rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-bold text-slate-100 transition hover:bg-white/10 lg:inline-flex lg:w-full lg:justify-center"
                >
                    Back to main app
                </Link>
            </aside>
            <div class="flex min-w-0 flex-1 flex-col">
                <header class="border-b border-white/10 bg-slate-900/30 px-4 py-4 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">Operations</p>
                            <h1 class="font-display text-xl font-black tracking-tight text-white sm:text-2xl">
                                {{ title }}
                            </h1>
                        </div>
                        <p v-if="subtitle" class="max-w-xl text-xs font-semibold leading-relaxed text-slate-400">
                            {{ subtitle }}
                        </p>
                    </div>
                </header>
                <main class="flex-1 bg-slate-950 px-4 py-6 sm:px-6 lg:px-8">
                    <div
                        v-if="flashSuccess"
                        class="mb-6 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-50 ring-1 ring-emerald-500/30"
                        role="status"
                    >
                        {{ flashSuccess }}
                    </div>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? '');

const primaryNav = [
    { label: 'Overview', href: route('operations.dashboard'), match: (p) => p === '/operations' || p === '/operations/' },
    { label: 'Quests', href: route('operations.quests.index'), match: (p) => p.startsWith('/operations/quests') },
    { label: 'Users', href: route('operations.users.index'), match: (p) => p.startsWith('/operations/users') },
    { label: 'Escrow & payouts', href: route('operations.payments.index'), match: (p) => p.startsWith('/operations/payments') },
    { label: 'Portfolios', href: route('operations.portfolios.index'), match: (p) => p.startsWith('/operations/portfolios') },
];

const secondaryNav = [
    { label: 'Disputes registry', href: route('operations.disputes.index'), match: (p) => p.startsWith('/operations/disputes') },
    { label: 'Member disputes UI', href: route('disputes.index'), match: (p) => p.startsWith('/disputes') },
];

function isActive(item) {
    const p = page.url.split('?')[0] || '';

    return item.match(p);
}
</script>
