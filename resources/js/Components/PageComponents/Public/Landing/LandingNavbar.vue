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

            <nav class="hidden items-center gap-8 lg:flex" aria-label="Primary">
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

            <div class="hidden items-center gap-3 lg:flex">
                <Link
                    v-if="canLogin"
                    :href="route('login')"
                    class="rounded-xl px-4 py-2 text-[0.9375rem] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 lg:text-[1.0625rem]"
                    :class="
                        navSolid
                            ? 'text-slate-800 hover:bg-slate-100 focus-visible:ring-primary-500'
                            : 'text-white hover:bg-white/10 focus-visible:ring-white focus-visible:ring-offset-primary-950'
                    "
                >
                    {{ nav.login }}
                </Link>
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="rounded-xl px-4 py-2.5 text-[0.9375rem] font-bold shadow-md ring-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 lg:text-[1.0625rem]"
                    :class="
                        navSolid
                            ? 'bg-primary-600 text-white shadow-primary-900/10 ring-primary-500/30 hover:bg-primary-700 focus-visible:ring-primary-500'
                            : 'bg-white text-primary-800 shadow-primary-950/25 ring-white/40 hover:bg-teal-50 focus-visible:ring-white'
                    "
                >
                    {{ nav.register }}
                </Link>
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
                    <nav class="flex flex-1 flex-col gap-1 px-3 py-4" aria-label="Mobile primary">
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
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="rounded-xl px-4 py-3 text-base font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                            @click="closeMobile"
                        >
                            {{ nav.login }}
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="mx-1 rounded-xl bg-primary-600 px-4 py-3 text-center text-base font-bold text-white shadow-md hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                            @click="closeMobile"
                        >
                            {{ nav.register }}
                        </Link>
                    </nav>
                </aside>
            </Transition>
        </Teleport>
    </header>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useWindowScroll } from '@vueuse/core';

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
