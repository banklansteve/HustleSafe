<template>
    <header
        class="fixed inset-x-0 top-0 z-50 border-b transition-all duration-300"
        :class="
            navSolid
                ? 'border-slate-200/90 bg-white/95 shadow-md backdrop-blur-md'
                : 'border-transparent bg-transparent shadow-none'
        "
    >
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <Link
                href="/"
                preserve-scroll
                class="flex items-center gap-2 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                :class="
                    navSolid
                        ? 'text-slate-900 focus-visible:ring-primary-500'
                        : 'text-white focus-visible:ring-white focus-visible:ring-offset-primary-900'
                "
            >
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-600 text-sm font-extrabold text-white shadow-sm ring-1 ring-primary-500/25"
                >
                    HS
                </span>
                <span class="font-display text-lg font-bold tracking-tight sm:text-xl">HustleSafe</span>
            </Link>

            <nav v-if="!isAuthenticated" class="hidden items-center gap-8 lg:flex" aria-label="Primary">
                <a
                    v-for="item in anchorLinks"
                    :key="item.href"
                    :href="item.href"
                    class="text-[0.9375rem] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 lg:text-[1.0625rem]"
                    :class="
                        navSolid
                            ? 'text-slate-700 hover:text-primary-700 focus-visible:ring-primary-500'
                            : 'text-white/90 hover:text-white focus-visible:ring-white focus-visible:ring-offset-primary-950'
                    "
                >
                    {{ item.label }}
                </a>
            </nav>

            <nav v-else class="hidden min-w-0 flex-1 items-center justify-center gap-1 px-2 lg:flex" aria-label="App">
                <Link
                    v-for="item in desktopAuthLinks"
                    :key="item.href + item.label"
                    :href="item.href"
                    prefetch="false"
                    preserve-scroll
                    class="whitespace-nowrap rounded-xl px-3 py-2 text-[0.8125rem] font-bold ring-2 ring-transparent transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 sm:px-3.5 sm:text-[0.9375rem] lg:text-[1rem]"
                    :class="authLinkClass(item.active())"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="hidden shrink-0 items-center gap-2 lg:flex">
                <template v-if="isAuthenticated">
                    <Link
                        :href="route('account.show')"
                        prefetch="false"
                        preserve-scroll
                        class="rounded-xl px-4 py-2.5 text-[0.9375rem] font-bold shadow-md ring-2 ring-transparent transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 lg:text-[1.0625rem]"
                        :class="accountCtaClass(accountSectionActive)"
                    >
                        {{ nav.account }}
                    </Link>
                </template>
                <template v-else>
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        prefetch="false"
                        preserve-scroll
                        class="rounded-xl px-4 py-2 text-[0.9375rem] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 lg:text-[1.0625rem]"
                        :class="
                            navSolid
                                ? 'text-slate-800 ring-transparent hover:bg-slate-100 focus-visible:ring-primary-500'
                                : 'text-white ring-transparent hover:bg-white/10 focus-visible:ring-white focus-visible:ring-offset-primary-950'
                        "
                    >
                        {{ nav.login }}
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        prefetch="false"
                        preserve-scroll
                        class="rounded-xl px-4 py-2.5 text-[0.9375rem] font-bold shadow-md ring-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 lg:text-[1.0625rem]"
                        :class="
                            navSolid
                                ? 'bg-primary-600 text-white shadow-primary-900/10 ring-primary-500/30 hover:bg-primary-700 focus-visible:ring-primary-500'
                                : 'bg-white text-primary-800 shadow-primary-950/25 ring-white/40 hover:bg-teal-50 focus-visible:ring-white'
                        "
                    >
                        {{ nav.register }}
                    </Link>
                </template>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl p-2 lg:hidden"
                :class="
                    navSolid
                        ? 'text-slate-800 hover:bg-slate-100'
                        : 'text-white hover:bg-white/15'
                "
                :aria-expanded="mobileOpen"
                aria-controls="mobile-nav"
                :aria-label="mobileOpen ? nav.close_menu : nav.open_menu"
                @click="mobileOpen = !mobileOpen"
            >
                <Bars3Icon v-if="!mobileOpen" class="h-7 w-7" aria-hidden="true" />
                <XMarkIcon v-else class="h-7 w-7" aria-hidden="true" />
            </button>
        </div>

        <Teleport to="body">
            <Transition name="fade">
                <div
                    v-if="mobileOpen"
                    class="fixed inset-0 z-[60] bg-slate-950/40 backdrop-blur-[2px] lg:hidden"
                    aria-hidden="true"
                    @click="closeMobile"
                />
            </Transition>
            <Transition name="slide">
                <aside
                    v-if="mobileOpen"
                    id="mobile-nav"
                    class="fixed inset-y-0 right-0 z-[70] flex w-[75vw] max-w-sm flex-col bg-white shadow-2xl lg:hidden"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Mobile navigation"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <span class="font-display text-lg font-bold text-slate-900">Menu</span>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                            :aria-label="nav.close_menu"
                            @click="closeMobile"
                        >
                            <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                        </button>
                    </div>
                    <nav class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-4" aria-label="Mobile primary">
                        <template v-if="!isAuthenticated">
                            <a
                                v-for="item in anchorLinks"
                                :key="item.href"
                                :href="item.href"
                                class="rounded-xl px-4 py-3 text-base font-semibold text-slate-800 hover:bg-primary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                                @click="closeMobile"
                            >
                                {{ item.label }}
                            </a>
                            <div class="my-3 border-t border-slate-100" />
                        </template>
                        <template v-if="isAuthenticated">
                            <Link
                                v-for="item in mobileAuthLinks"
                                :key="item.href + item.label"
                                :href="item.href"
                                prefetch="false"
                                preserve-scroll
                                class="rounded-xl px-4 py-3 text-base font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                                :class="mobileAuthLinkClass(item.active())"
                                @click="closeMobile"
                            >
                                {{ item.label }}
                            </Link>
                            <div class="my-3 border-t border-slate-100" />
                            <Link
                                :href="route('account.show')"
                                prefetch="false"
                                preserve-scroll
                                class="mx-1 mt-1 rounded-xl bg-primary-600 px-4 py-3 text-center text-base font-bold text-white shadow-md hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                                :class="accountSectionActive ? 'ring-2 ring-inset ring-primary-300' : ''"
                                @click="closeMobile"
                            >
                                {{ nav.account }}
                            </Link>
                        </template>
                        <template v-if="!isAuthenticated">
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                prefetch="false"
                                preserve-scroll
                                class="rounded-xl px-4 py-3 text-base font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                                @click="closeMobile"
                            >
                                {{ nav.login }}
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                prefetch="false"
                                preserve-scroll
                                class="mx-1 rounded-xl bg-primary-600 px-4 py-3 text-center text-base font-bold text-white shadow-md hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                                @click="closeMobile"
                            >
                                {{ nav.register }}
                            </Link>
                        </template>
                    </nav>
                </aside>
            </Transition>
        </Teleport>
    </header>
</template>

<script setup>
import { pathMatches, usePathname } from '@/composables/usePathname';
import { Link, usePage } from '@inertiajs/vue3';
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useWindowScroll } from '@vueuse/core';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    nav: {
        type: Object,
        required: true,
    },
    canLogin: {
        type: Boolean,
        default: false,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const pathname = usePathname(page);

const user = computed(() => page.props.auth?.user ?? null);
const isAuthenticated = computed(() => !!user.value);
const roleSlug = computed(() => user.value?.role?.slug ?? '');
const isFreelancer = computed(() => roleSlug.value === 'freelancer');
const showClientTools = computed(() => ['client', 'super_admin'].includes(roleSlug.value));

const mobileOpen = ref(false);

const { y } = useWindowScroll();

/** Solid white bar after leaving the hero banner */
const navSolid = computed(() => y.value > 24);

const anchorLinks = computed(() => [
    { href: '#how-it-works', label: props.nav.how_it_works },
    { href: '#trust', label: props.nav.trust },
    { href: '#categories', label: props.nav.categories },
    { href: '#popular-jobs', label: props.nav.popular_jobs },
    { href: '#faq', label: props.nav.faq },
]);

const exploreHref = computed(() => route('quests.explore'));
const myOffersHref = computed(() => route('dashboard.lists.show', { list: 'freelancer-proposals-sent' }));

function portfolioManageActive() {
    const p = pathname.value;
    return (
        p.startsWith('/portfolio/manage')
        || p.startsWith('/portfolio/create')
        || /\/portfolio\/\d+\/edit$/.test(p)
    );
}

const accountSectionActive = computed(() => pathname.value.startsWith('/account'));

const desktopAuthLinks = computed(() => {
    const items = [
        {
            href: '/',
            label: props.nav.home,
            active: () => pathname.value === '/',
        },
        {
            href: route('dashboard'),
            label: props.nav.dashboard,
            active: () => pathMatches(pathname, route('dashboard')),
        },
        {
            href: exploreHref.value,
            label: props.nav.quests,
            active: () => pathMatches(pathname, exploreHref.value),
        },
    ];
    if (showClientTools.value) {
        items.push({
            href: route('quests.create'),
            label: props.nav.new_quest,
            active: () => pathMatches(pathname, route('quests.create')),
        });
        items.push({
            href: route('portfolio.index'),
            label: props.nav.portfolios,
            active: () => pathMatches(pathname, route('portfolio.index')),
        });
    }
    if (isFreelancer.value) {
        items.push({
            href: myOffersHref.value,
            label: props.nav.my_offers,
            active: () => pathMatches(pathname, myOffersHref.value, { prefix: true }),
        });
        items.push({
            href: route('portfolio.manage'),
            label: props.nav.portfolio,
            active: () => portfolioManageActive(),
        });
    }
    return items;
});

const mobileAuthLinks = computed(() => desktopAuthLinks.value);

function authLinkClass(active) {
    if (navSolid.value) {
        return active
            ? 'bg-primary-50 text-primary-900 ring-primary-400/80'
            : 'text-slate-800 ring-transparent hover:bg-slate-100';
    }
    return active
        ? 'bg-white/20 text-white ring-white/70'
        : 'text-white/90 ring-transparent hover:bg-white/10';
}

function mobileAuthLinkClass(active) {
    return active
        ? 'bg-primary-50 font-bold text-primary-900 ring-2 ring-inset ring-primary-300'
        : 'font-semibold text-slate-800 hover:bg-slate-50';
}

function accountCtaClass(active) {
    if (navSolid.value) {
        return active
            ? 'bg-primary-700 text-white shadow-primary-900/15 ring-primary-400/90 focus-visible:ring-primary-500'
            : 'bg-primary-600 text-white shadow-primary-900/10 ring-primary-500/30 hover:bg-primary-700 focus-visible:ring-primary-500';
    }
    return active
        ? 'bg-teal-100 text-primary-950 shadow-primary-950/20 ring-white/80 focus-visible:ring-white'
        : 'bg-white text-primary-800 shadow-primary-950/25 ring-white/40 hover:bg-teal-50 focus-visible:ring-white';
}

function closeMobile() {
    mobileOpen.value = false;
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        closeMobile();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

watch(mobileOpen, (open) => {
    document.documentElement.classList.toggle('overflow-hidden', open);
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
    transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}
.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}
</style>
