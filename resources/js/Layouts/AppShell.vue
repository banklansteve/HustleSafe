<template>
    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 text-slate-900">
        <header
            class="sticky top-0 z-40 border-b border-slate-200/90 bg-white/90 backdrop-blur-lg supports-[backdrop-filter]:bg-white/80"
        >
            <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:gap-4 sm:px-6 lg:px-8">
                <Link
                    href="/"
                    prefetch="false"
                    preserve-scroll
                    class="flex shrink-0 items-center gap-3 rounded-xl outline-none ring-2 ring-transparent ring-offset-2 transition focus-visible:ring-primary-600"
                    :class="homeActive ? 'ring-primary-200' : ''"
                >
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-600 to-primary-800 text-sm font-black tracking-wide text-white shadow-md shadow-primary-900/20 ring-1 ring-white/25"
                    >
                        HS
                    </span>
                    <div class="hidden leading-tight sm:block">
                        <p class="font-display text-lg font-bold tracking-tight text-slate-900">
                            HustleSafe
                        </p>
                        <p class="text-sm font-semibold text-slate-500">
                            Escrow-first marketplace
                        </p>
                    </div>
                </Link>

                <!-- Desktop shortcuts -->
                <nav
                    class="hidden min-w-0 flex-1 items-center justify-center gap-2 lg:flex"
                    aria-label="Quick navigation"
                >
                    <Link
                        :href="route('dashboard')"
                        prefetch="false"
                        preserve-scroll
                        class="inline-flex items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-bold shadow-sm transition"
                        :class="pillClass(dashboardActive)"
                    >
                        <HomeIcon class="h-5 w-5 opacity-80" aria-hidden="true" />
                        Dashboard
                    </Link>
                    <template v-if="showClientTools">
                        <Link
                            :href="route('quests.index')"
                            prefetch="false"
                            preserve-scroll
                            class="inline-flex items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-bold shadow-sm transition"
                            :class="pillClass(questsIndexActive)"
                        >
                            <ClipboardDocumentListIcon class="h-5 w-5 opacity-80" aria-hidden="true" />
                            My quests
                        </Link>
                        <Link
                            :href="route('quests.create')"
                            prefetch="false"
                            preserve-scroll
                            class="inline-flex items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-bold shadow-sm transition"
                            :class="pillClass(createQuestActive)"
                        >
                            <PlusCircleIcon class="h-5 w-5 opacity-80" aria-hidden="true" />
                            Create quest
                        </Link>
                        <Link
                            :href="route('quests.explore')"
                            prefetch="false"
                            preserve-scroll
                            class="inline-flex items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-bold shadow-sm transition"
                            :class="pillClass(exploreActive)"
                        >
                            <MagnifyingGlassIcon class="h-5 w-5 opacity-80" aria-hidden="true" />
                            Browse quests
                        </Link>
                    </template>
                    <template v-else-if="isFreelancer">
                        <Link
                            :href="route('portfolio.manage')"
                            prefetch="false"
                            preserve-scroll
                            class="inline-flex items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-bold shadow-sm transition"
                            :class="pillClass(portfolioManageActive)"
                        >
                            <BriefcaseIcon class="h-5 w-5 opacity-80" aria-hidden="true" />
                            Your portfolio
                        </Link>
                        <Link
                            :href="route('quests.explore')"
                            prefetch="false"
                            preserve-scroll
                            class="inline-flex items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-bold shadow-sm transition"
                            :class="pillClass(exploreActive)"
                        >
                            <MagnifyingGlassIcon class="h-5 w-5 opacity-80" aria-hidden="true" />
                            Browse quests
                        </Link>
                    </template>
                </nav>

                <div class="ml-auto flex shrink-0 items-center gap-2 sm:gap-3">
                    <div class="relative">
                        <a
                            href="#notifications"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                            aria-label="Notifications"
                        >
                            <BellAlertIcon class="h-6 w-6" aria-hidden="true" />
                        </a>
                        <span
                            v-if="unreadNotificationsCount > 0"
                            class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-secondary-500 px-1 text-[10px] font-black text-white ring-2 ring-white"
                        >
                            {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
                        </span>
                    </div>

                    <NavUserMenu />
                </div>
            </div>

            <div
                v-if="flashSuccess"
                class="border-t border-emerald-100 bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-900 sm:text-base"
                role="status"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-else-if="flashStatus"
                class="border-t border-primary-100 bg-primary-50 px-4 py-3 text-center text-sm font-semibold text-primary-950 sm:text-base"
                role="status"
            >
                {{ flashStatus }}
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8 lg:py-12">
            <div
                v-if="freelancerNudgeLines.length && !hideWorkspaceNudgeOnAccount"
                class="mb-6 rounded-2xl border border-secondary-200/80 bg-secondary-50/90 px-4 py-3 text-sm font-semibold text-secondary-950 shadow-sm ring-1 ring-secondary-100 sm:px-5 sm:py-4"
                role="status"
            >
                <p class="text-xs font-black uppercase tracking-wide text-secondary-800">Action needed</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    <li v-for="(line, i) in freelancerNudgeLines" :key="i">{{ line }}</li>
                </ul>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link
                        :href="route('account.show', { tab: 'overview' })"
                        class="text-xs font-bold text-secondary-900 underline underline-offset-2 hover:text-secondary-700"
                    >
                        Account
                    </Link>
                    <span class="text-xs font-bold text-secondary-400">·</span>
                    <Link
                        :href="route('verifications.index')"
                        class="text-xs font-bold text-secondary-900 underline underline-offset-2 hover:text-secondary-700"
                    >
                        Verifications
                    </Link>
                </div>
            </div>
            <slot />
        </main>
    </div>
</template>

<script setup>
import NavUserMenu from '@/Components/Layout/NavUserMenu.vue';
import { pathMatches, usePathname } from '@/composables/usePathname';
import { Link, usePage } from '@inertiajs/vue3';
import { BellAlertIcon, BriefcaseIcon, ClipboardDocumentListIcon, HomeIcon, MagnifyingGlassIcon, PlusCircleIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const page = usePage();
const pathname = usePathname(page);

const unreadNotificationsCount = computed(() => Number(page.props.unreadNotificationsCount ?? 0) || 0);

/** Avoid duplicating category/setup prompts already shown on Account Hub. */
const hideWorkspaceNudgeOnAccount = computed(() => pathname.value.startsWith('/account'));

const freelancerNudgeLines = computed(() => {
    const ws = page.props.freelancerWorkspace;
    if (!ws?.enabled) {
        return [];
    }
    const lines = [];
    for (const b of ws.blockers || []) {
        if (b?.message) {
            lines.push(b.message);
        }
    }
    for (const h of ws.hints || []) {
        if (h?.message) {
            lines.push(h.message);
        }
    }

    return lines.slice(0, 4);
});

const roleSlug = computed(() => page.props.auth?.user?.role?.slug ?? '');
const isFreelancer = computed(() => roleSlug.value === 'freelancer');
const showClientTools = computed(() => ['client', 'admin', 'super_admin'].includes(roleSlug.value));

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashStatus = computed(() => page.props.flash?.status ?? null);

const homeActive = computed(() => pathname.value === '/');

const dashboardActive = computed(() => pathMatches(pathname, route('dashboard')));

const createQuestActive = computed(() => pathMatches(pathname, route('quests.create')));

const questsIndexActive = computed(() => pathMatches(pathname, route('quests.index')));

const exploreActive = computed(() => pathMatches(pathname, route('quests.explore')));

const portfolioManageActive = computed(() => {
    const p = pathname.value;
    return (
        p.startsWith('/portfolio/manage')
        || p.startsWith('/portfolio/create')
        || /\/portfolio\/\d+\/edit$/.test(p)
    );
});

function pillClass(active) {
    return active
        ? 'border-primary-400 bg-primary-50 text-primary-950 shadow-primary-900/5'
        : 'border-slate-200 border-opacity-100 bg-white text-slate-800 hover:border-primary-200 hover:bg-primary-50/60';
}
</script>
