<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <div
            v-if="navPending"
            class="pointer-events-none fixed inset-x-0 top-0 z-[60] h-0.5 bg-primary-600 shadow-[0_0_12px_rgba(37,99,235,0.45)]"
            role="progressbar"
            aria-label="Loading page"
        />

        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur sm:px-6">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
                <Link :href="route('operations.dashboard')" prefetch="false" class="flex min-w-0 items-center gap-3" @click.prevent="visitNav(route('operations.dashboard'))">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-primary-700 text-sm font-black text-white shadow-md shadow-primary-900/20">HS</span>
                    <span class="min-w-0">
                        <span class="block text-[10px] font-black uppercase tracking-[0.24em] text-primary-700">HustleSafe</span>
                        <span class="block truncate font-display text-base font-black text-slate-950">Staff Admin</span>
                    </span>
                </Link>

                <nav class="hidden items-center gap-2 lg:flex" aria-label="Staff navigation">
                    <div v-for="group in navGroups" :key="group.label" class="relative">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-black transition"
                            :class="isGroupActive(group) ? 'bg-primary-700 text-white shadow-md shadow-primary-900/15' : 'text-slate-700 hover:bg-primary-50 hover:text-primary-900'"
                            @click="desktopOpen = desktopOpen === group.label ? '' : group.label"
                        >
                            {{ group.label }}
                            <span class="text-xs opacity-70">⌄</span>
                        </button>
                        <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="-translate-y-1 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-100 ease-in" leave-to-class="-translate-y-1 opacity-0">
                            <div v-if="desktopOpen === group.label" class="absolute left-0 mt-2 w-72 rounded-3xl border border-slate-200 bg-white p-3 shadow-2xl shadow-slate-900/15">
                                <Link
                                    v-for="item in group.items"
                                    :key="item.href"
                                    :href="item.href"
                                    prefetch="false"
                                    class="block rounded-2xl px-4 py-3 transition"
                                    :class="isActive(item) ? 'bg-primary-700 text-white shadow-md' : 'text-slate-700 hover:bg-primary-50 hover:text-primary-900'"
                                    @click.prevent="visitNav(item.href); desktopOpen = ''"
                                >
                                    <span class="block text-sm font-black">{{ item.label }}</span>
                                    <span class="mt-1 block text-xs font-semibold opacity-75">{{ item.hint }}</span>
                                </Link>
                            </div>
                        </Transition>
                    </div>
                </nav>

                <div class="relative lg:hidden">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-800 shadow-sm hover:bg-primary-50"
                        :aria-expanded="menuOpen"
                        aria-label="Open staff navigation"
                        @click="menuOpen = !menuOpen"
                    >
                        <span class="text-xl leading-none">☰</span>
                    </button>
                    <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="-translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-100 ease-in" leave-to-class="-translate-y-2 opacity-0">
                        <div v-if="menuOpen" class="fixed inset-x-3 top-20 z-50 max-h-[calc(100vh-6rem)] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-3 shadow-2xl shadow-slate-900/15">
                            <nav class="space-y-4" aria-label="Staff mobile navigation">
                                <section v-for="group in navGroups" :key="group.label">
                                    <p class="px-3 pb-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">{{ group.label }}</p>
                                    <div class="grid gap-1">
                                        <Link
                                            v-for="item in group.items"
                                            :key="item.href"
                                            :href="item.href"
                                            prefetch="false"
                                            class="rounded-2xl px-4 py-3 transition"
                                            :class="isActive(item) ? 'bg-primary-700 text-white shadow-md' : 'text-slate-700 hover:bg-primary-50 hover:text-primary-900'"
                                            @click.prevent="visitNav(item.href); menuOpen = false"
                                        >
                                            <span class="block text-sm font-black">{{ item.label }}</span>
                                            <span class="mt-1 block text-xs font-semibold opacity-75">{{ item.hint }}</span>
                                        </Link>
                                    </div>
                                </section>
                            </nav>
                            <Link href="/dashboard" prefetch="false" class="mt-3 block rounded-2xl border border-slate-200 px-4 py-3 text-center text-sm font-black text-slate-700 hover:bg-slate-50" @click="menuOpen = false">
                                Back to main app
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
            <div class="mb-5 rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.25em] text-primary-700">Operations</p>
                        <h1 class="font-display mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-2 max-w-2xl text-sm font-semibold leading-relaxed text-slate-600">{{ subtitle }}</p>
                    </div>
                    <Link
                        :href="route('operations.tasks.index')"
                        prefetch="false"
                        class="inline-flex items-center justify-center rounded-xl bg-primary-700 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-md hover:bg-primary-800"
                        @click.prevent="visitNav(route('operations.tasks.index'))"
                    >
                        My Tasks
                    </Link>
                </div>
            </div>

            <div v-if="flashSuccess" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-900" role="status">
                {{ flashSuccess }}
            </div>
            <div v-if="firstError" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-900" role="alert">
                {{ firstError }}
            </div>

            <slot />
        </main>
    </div>
</template>

<script setup>
import { useInertiaNav } from '@/composables/useInertiaNav';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const { navPending, visit: visitNav } = useInertiaNav();

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});

const page = usePage();
const menuOpen = ref(false);
const desktopOpen = ref('');

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const firstError = computed(() => {
    const errors = page.props.errors || {};
    const first = Object.values(errors)[0];

    return Array.isArray(first) ? first[0] : (first || '');
});

const navGroups = [
    {
        label: 'Workspace',
        items: [
            { label: 'Dashboard', hint: 'Personal workload and active queues', href: route('operations.dashboard'), match: (p) => p === '/operations' || p === '/operations/' },
            { label: 'My Tasks', hint: 'Assigned flags, referrals, disputes, and escalations', href: route('operations.tasks.index'), match: (p) => p.startsWith('/operations/tasks') },
        ],
    },
    {
        label: 'Moderation',
        items: [
            { label: 'Moderation centre', hint: 'Tabbed quest & proposal queues with slide-in actions', href: route('operations.moderation.index'), match: (p) => p.startsWith('/operations/moderation') },
            { label: 'Reviews', hint: 'Review moderation and appeals triage', href: route('operations.reviews.index'), match: (p) => p.startsWith('/operations/reviews') },
        ],
    },
    {
        label: 'People',
        items: [
            { label: 'Users', hint: 'User context, warnings, and 72-hour suspensions', href: route('operations.users.index'), match: (p) => p.startsWith('/operations/users') },
            { label: 'Verifications', hint: 'KYC, BVN, NIN, and utility review queue', href: route('operations.verifications.index'), match: (p) => p.startsWith('/operations/verifications') },
        ],
    },
    {
        label: 'Cases',
        items: [
            { label: 'Disputes', hint: 'Mediation queue and evidence review', href: route('operations.disputes.index'), match: (p) => p.startsWith('/operations/disputes') },
            { label: 'Payments', hint: 'Limited escrow and payout support view', href: route('operations.payments.index'), match: (p) => p.startsWith('/operations/payments') },
        ],
    },
    {
        label: 'Support',
        items: [
            { label: 'Support hub', hint: 'Global search, ticket queues, CS chats, disputes', href: route('operations.support.index'), match: (p) => p.startsWith('/operations/support') || p.startsWith('/operations/communications') },
        ],
    },
];

function isActive(item) {
    const p = page.url.split('?')[0] || '';

    return item.match(p);
}

function isGroupActive(group) {
    return group.items.some((item) => isActive(item));
}
</script>
