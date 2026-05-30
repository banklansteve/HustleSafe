<template>
    <div class="min-h-screen" :class="[shell.root, isDark ? 'admin-theme-dark' : 'admin-theme-light']">
        <div class="flex min-h-screen flex-col lg:flex-row">
            <aside
                data-admin-sidebar
                class="relative border-b transition-[width] duration-300 ease-out px-3 py-4 lg:sticky lg:top-0 lg:flex lg:max-h-screen lg:shrink-0 lg:flex-col lg:border-b-0 lg:border-r lg:py-6"
                :class="[shell.aside, sidebarCollapsed ? 'lg:w-[4.5rem] lg:px-2' : 'lg:w-72 lg:px-5']"
                aria-label="Super admin navigation"
            >
                <div class="flex items-center justify-between gap-2">
                    <Link
                        :href="route('admin.dashboard')"
                        class="flex min-w-0 flex-col transition"
                        :title="sidebarCollapsed ? 'Super admin home' : undefined"
                    >
                        <div
                            class="min-w-0 shrink-0"
                            :class="sidebarCollapsed ? 'w-9 overflow-hidden' : 'w-auto'"
                        >
                            <HustleSafeLogo
                                variant="lockup"
                                :theme="logoTheme"
                                :lockup-class="sidebarCollapsed ? 'h-9 w-auto max-w-none' : 'h-7 w-auto max-w-[9rem]'"
                            />
                        </div>
                        <p
                            v-show="!sidebarCollapsed"
                            class="mt-0.5 hidden text-[10px] font-black uppercase tracking-[0.28em] lg:block"
                            :class="shell.brandEyebrow"
                        >
                            Super admin
                        </p>
                    </Link>
                    <Link
                        href="/dashboard"
                        class="rounded-full border px-3 py-1.5 text-xs font-bold transition lg:hidden"
                        :class="shell.btnGhost"
                    >
                        Exit
                    </Link>
                </div>

                <button
                    type="button"
                    class="absolute -right-3 top-20 z-10 hidden h-7 w-7 items-center justify-center rounded-full border shadow-md transition lg:flex"
                    :class="shell.toggleBtn"
                    :aria-expanded="!sidebarCollapsed"
                    :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    @click="toggleCollapsed"
                >
                    <ChevronLeftIcon v-if="!sidebarCollapsed" class="h-4 w-4" aria-hidden="true" />
                    <ChevronRightIcon v-else class="h-4 w-4" aria-hidden="true" />
                </button>

                <nav class="mt-6 min-h-0 flex-1 space-y-5 lg:overflow-y-auto lg:pr-1" :class="sidebarCollapsed ? 'lg:space-y-3' : ''">
                    <div v-for="group in navGroups" :key="group.label">
                        <p
                            v-if="!group.collapsible && !group.management"
                            v-show="!sidebarCollapsed"
                            class="hidden px-2 pb-2 text-[10px] font-black uppercase tracking-wider lg:block"
                            :class="shell.label"
                        >
                            {{ group.label }}
                        </p>
                        <template v-if="group.management">
                            <button
                                v-show="!sidebarCollapsed"
                                type="button"
                                class="mb-1 flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-left text-[10px] font-black uppercase tracking-wider transition"
                                :class="shell.btnGhost"
                                @click="toggleNavGroup(group.label)"
                            >
                                <span>{{ group.label }}</span>
                                <ChevronRightIcon class="h-3.5 w-3.5 shrink-0 transition" :class="expandedNavGroups[group.label] ? 'rotate-90' : ''" />
                            </button>
                            <div
                                v-if="visibleManagementNav.length"
                                v-show="!sidebarCollapsed && expandedNavGroups[group.label]"
                                class="space-y-2"
                            >
                                <div v-for="section in visibleManagementNav" :key="section.key" class="space-y-0.5">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-left text-[10px] font-black uppercase tracking-wider transition"
                                        :class="managementSectionIsActive(section) ? shell.navActive : shell.navIdle"
                                        @click="toggleManagementSection(section)"
                                    >
                                        <span>{{ section.label }}</span>
                                        <ChevronRightIcon
                                            class="h-3.5 w-3.5 shrink-0 transition"
                                            :class="expandedManagement[section.key] ? 'rotate-90' : ''"
                                        />
                                    </button>
                                    <div v-show="expandedManagement[section.key]" class="space-y-0.5 border-l-2 pl-2" :class="shell.tableDivide">
                                        <Link
                                            v-for="item in section.items"
                                            :key="item.key"
                                            :href="managementResourceHref(item.key)"
                                            prefetch="false"
                                            class="block rounded-lg px-2 py-1.5 text-xs font-bold transition"
                                            :class="[
                                                item.indent ? 'ml-1' : '',
                                                isManagementItemActive(item) ? shell.navActive : shell.navIdle,
                                            ]"
                                            @click="onChildNavClick('Data registry')"
                                        >
                                            {{ item.label }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                            <Link
                                v-else-if="!sidebarCollapsed && expandedNavGroups[group.label]"
                                :href="route('admin.management.index')"
                                class="block rounded-lg px-2 py-1.5 text-xs font-bold"
                                :class="shell.navIdle"
                            >
                                Open registry hub
                            </Link>
                        </template>

                        <template v-else-if="!group.management">
                        <button
                            v-if="group.collapsible && !sidebarCollapsed"
                            type="button"
                            class="mb-1 hidden w-full items-center justify-between rounded-lg px-2 py-1.5 text-left text-[10px] font-black uppercase tracking-wider transition lg:flex"
                            :class="shell.btnGhost"
                            @click="toggleNavGroup(group.label)"
                        >
                            <span>{{ group.label }}</span>
                            <ChevronRightIcon class="h-3.5 w-3.5 transition" :class="expandedNavGroups[group.label] ? 'rotate-90' : ''" />
                        </button>
                        <div
                            v-if="!group.collapsible || expandedNavGroups[group.label] || sidebarCollapsed"
                            class="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:gap-0.5 lg:overflow-visible lg:pb-0"
                            :class="sidebarCollapsed ? 'lg:items-center' : ''"
                        >
                            <template v-for="item in group.items" :key="item.label">
                                <Link
                                    :href="item.href"
                                    prefetch="false"
                                    :title="sidebarCollapsed ? item.label : undefined"
                                    class="group flex items-center gap-3 rounded-xl text-sm font-bold transition"
                                    :class="[
                                        sidebarCollapsed ? 'lg:justify-center lg:px-2 lg:py-2.5' : 'whitespace-nowrap px-3 py-2 lg:px-3 lg:py-2.5',
                                        isActive(item) ? shell.navActive : shell.navIdle,
                                    ]"
                                    @click="onChildNavClick(group.label)"
                                >
                                    <component
                                        :is="item.icon"
                                        class="h-5 w-5 shrink-0"
                                        :class="isActive(item) ? shell.navIconActive : shell.navIconIdle"
                                        aria-hidden="true"
                                    />
                                    <span class="lg:hidden">{{ item.label }}</span>
                                    <span v-show="!sidebarCollapsed" class="hidden lg:inline text-inherit">{{ item.label }}</span>
                                    <span
                                        v-if="item.badge?.() > 0"
                                        class="ml-auto rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-black text-white"
                                    >{{ item.badge() > 99 ? '99+' : item.badge() }}</span>
                                </Link>
                            </template>
                        </div>
                        </template>
                    </div>
                </nav>

                <div class="mt-4 hidden flex-col gap-2 border-t pt-4 lg:flex" :class="[shell.tableDivide, sidebarCollapsed ? 'items-center' : '']">
                    <AdminThemeToggle :compact="sidebarCollapsed" />
                    <Link
                        href="/dashboard"
                        :title="sidebarCollapsed ? 'Back to main app' : undefined"
                        class="inline-flex items-center justify-center rounded-xl border text-sm font-bold transition"
                        :class="[
                            shell.btnGhost,
                            sidebarCollapsed ? 'h-10 w-10 p-0' : 'w-full px-4 py-2.5 gap-2',
                        ]"
                    >
                        <ArrowLeftOnRectangleIcon class="h-5 w-5 shrink-0" aria-hidden="true" />
                        <span v-show="!sidebarCollapsed">Back to main app</span>
                    </Link>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="border-b px-4 py-4 backdrop-blur-md sm:px-6" :class="shell.header">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <button
                                type="button"
                                class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border transition lg:hidden"
                                :class="shell.btnGhost"
                                aria-label="Toggle navigation"
                                @click="mobileNavOpen = !mobileNavOpen"
                            >
                                <Bars3Icon class="h-5 w-5" aria-hidden="true" />
                            </button>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.25em]" :class="shell.label">Console</p>
                                <h1 class="font-display text-xl font-black tracking-tight sm:text-2xl" :class="shell.title">
                                    {{ title }}
                                </h1>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="hidden items-center gap-2 rounded-xl border px-3 py-2 text-xs font-black uppercase tracking-wide sm:inline-flex"
                                :class="shell.btnGhost"
                                @click="openCommandPalette"
                            >
                                <MagnifyingGlassIcon class="h-4 w-4" />
                                Command
                                <span class="rounded-md bg-black/10 px-1.5 py-0.5 text-[10px] dark:bg-white/10">Ctrl K</span>
                            </button>
                            <Link
                                :href="route('admin.messages.index')"
                                prefetch="false"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border"
                                :class="shell.btnGhost"
                                title="Direct messages"
                            >
                                <ChatBubbleLeftRightIcon class="h-5 w-5" />
                                <span
                                    v-if="unreadMessenger > 0"
                                    class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary-600 px-1 text-[10px] font-black text-white"
                                >
                                    {{ unreadMessenger > 99 ? '99+' : unreadMessenger }}
                                </span>
                            </Link>
                            <Link
                                :href="route('admin.alerts.index')"
                                prefetch="false"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border"
                                :class="shell.btnGhost"
                                title="Notification centre"
                            >
                                <BellIcon class="h-5 w-5" />
                                <span
                                    v-if="unreadAlerts > 0"
                                    class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-black text-white"
                                >
                                    {{ unreadAlerts > 99 ? '99+' : unreadAlerts }}
                                </span>
                            </Link>
                            <AdminThemeToggle class="lg:hidden" />
                            <p v-if="subtitle" class="max-w-xl text-xs font-semibold leading-relaxed" :class="shell.canvasMuted">
                                {{ subtitle }}
                            </p>
                        </div>
                    </div>
                </header>
                <main class="admin-main-surface flex-1 space-y-2 px-4 py-4 sm:px-6 lg:px-8" :class="shell.main">
                    <div
                        v-if="flashBanner.message"
                        class="mb-6 rounded-2xl border px-4 py-3 text-sm font-semibold"
                        :class="flashBanner.class"
                        role="status"
                    >
                        {{ flashBanner.message }}
                    </div>
                    <slot />
                </main>
            </div>
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-3 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-3 opacity-0"
            >
                <div
                    v-if="toastMessage"
                    class="fixed bottom-5 right-5 z-[95] max-w-sm rounded-2xl border px-4 py-3 text-sm font-bold shadow-2xl"
                    :class="toastClass"
                    role="status"
                    aria-live="polite"
                >
                    {{ toastMessage }}
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="-translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <div
                    v-if="adminRequestWorking"
                    class="fixed right-5 top-5 z-[96] inline-flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-black shadow-2xl"
                    :class="isDark ? 'border-primary-400/30 bg-slate-950/90 text-primary-100' : 'border-primary-200 bg-white text-primary-800'"
                    role="status"
                    aria-live="polite"
                >
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                    Working behind the scenes...
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="mobileNavOpen"
                    class="fixed inset-0 z-[70] backdrop-blur-sm lg:hidden"
                    :class="shell.overlay"
                    @click="mobileNavOpen = false"
                />
            </Transition>
            <Transition
                enter-active-class="transition duration-250 ease-out"
                enter-from-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <aside
                    v-if="mobileNavOpen"
                    data-admin-sidebar
                    class="fixed inset-y-0 left-0 z-[75] flex w-[min(18rem,88vw)] flex-col border-r px-4 py-6 shadow-2xl lg:hidden"
                    :class="shell.aside"
                >
                    <div class="flex items-center justify-between">
                        <p class="font-display text-lg font-black" :class="shell.title">Menu</p>
                        <button type="button" class="rounded-lg p-2" :class="shell.btnGhost" @click="mobileNavOpen = false">
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                    <nav class="mt-6 flex-1 space-y-5 overflow-y-auto">
                        <div v-for="group in navGroups" :key="group.label">
                            <button
                                v-if="group.management"
                                type="button"
                                class="flex w-full items-center justify-between px-2 pb-2 text-left text-[10px] font-black uppercase tracking-wider"
                                :class="shell.label"
                                @click="toggleNavGroup(group.label)"
                            >
                                <span>{{ group.label }}</span>
                                <ChevronRightIcon class="h-3.5 w-3.5 transition" :class="expandedNavGroups[group.label] ? 'rotate-90' : ''" />
                            </button>
                            <button
                                v-else-if="group.collapsible"
                                type="button"
                                class="flex w-full items-center justify-between px-2 pb-2 text-left text-[10px] font-black uppercase tracking-wider"
                                :class="shell.label"
                                @click="toggleNavGroup(group.label)"
                            >
                                <span>{{ group.label }}</span>
                                <ChevronRightIcon class="h-3.5 w-3.5 transition" :class="expandedNavGroups[group.label] ? 'rotate-90' : ''" />
                            </button>
                            <p v-else class="px-2 pb-2 text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                {{ group.label }}
                            </p>
                            <div v-if="group.management && visibleManagementNav.length && expandedNavGroups[group.label]" class="mb-3 space-y-2 pl-1">
                                <div v-for="section in visibleManagementNav" :key="`m-${section.key}`" class="space-y-0.5">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-left text-[9px] font-black uppercase tracking-wide"
                                        :class="managementSectionIsActive(section) ? shell.navActive : shell.navIdle"
                                        @click="toggleManagementSection(section)"
                                    >
                                        <span>{{ section.label }}</span>
                                        <ChevronRightIcon class="h-3.5 w-3.5 shrink-0 transition" :class="expandedManagement[section.key] ? 'rotate-90' : ''" />
                                    </button>
                                    <div v-show="expandedManagement[section.key]" class="space-y-0.5 border-l-2 pl-2" :class="shell.tableDivide">
                                        <Link
                                            v-for="item in section.items"
                                            :key="`m-${item.key}`"
                                            :href="managementResourceHref(item.key)"
                                            prefetch="false"
                                            class="block rounded-lg px-3 py-2 text-sm font-bold transition"
                                            :class="isManagementItemActive(item) ? shell.navActive : shell.navIdle"
                                            @click="onChildNavClick('Data registry')"
                                        >
                                            {{ item.label }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else-if="!group.management && (!group.collapsible || expandedNavGroups[group.label])"
                                class="space-y-0.5"
                            >
                                <template v-for="item in group.items" :key="item.label">
                                    <Link
                                        :href="item.href"
                                        prefetch="false"
                                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition"
                                        :class="isActive(item) ? shell.navActive : shell.navIdle"
                                        @click="onChildNavClick(group.label)"
                                    >
                                        <component
                                            :is="item.icon"
                                            class="h-5 w-5 shrink-0"
                                            :class="isActive(item) ? shell.navIconActive : shell.navIconIdle"
                                            aria-hidden="true"
                                        />
                                        <span class="text-inherit">{{ item.label }}</span>
                                    </Link>
                                </template>
                            </div>
                        </div>
                    </nav>
                    <AdminThemeToggle class="mt-4" />
                </aside>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0" leave-active-class="transition duration-100 ease-in" leave-to-class="opacity-0">
                <div v-if="commandOpen" class="fixed inset-0 z-[100] bg-slate-950/50 p-4 backdrop-blur-sm" @click.self="commandOpen = false">
                    <div class="mx-auto mt-16 max-w-2xl overflow-hidden rounded-3xl border shadow-2xl" :class="[shell.card, 'p-0']">
                        <div class="border-b p-4" :class="shell.tableDivide">
                            <div class="flex items-center gap-3">
                                <MagnifyingGlassIcon class="h-5 w-5" :class="shell.muted" />
                                <input
                                    ref="commandInput"
                                    v-model="commandQuery"
                                    type="search"
                                    class="w-full bg-transparent text-base font-bold outline-none"
                                    :class="shell.title"
                                    placeholder="Search users, quests, disputes, transactions or type an action..."
                                />
                                <button type="button" class="rounded-lg px-2 py-1 text-xs font-black" :class="shell.btnGhost" @click="commandOpen = false">Esc</button>
                            </div>
                        </div>
                        <div class="max-h-[60vh] overflow-y-auto p-3">
                            <CommandSection title="Quick actions" :items="commandResults.actions" />
                            <CommandSection title="Search results" :items="commandResults.results" />
                            <p v-if="commandError" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-100">
                                {{ commandError }}
                            </p>
                            <p v-if="!commandError && !commandLoading && !commandResults.actions.length && !commandResults.results.length" class="rounded-2xl p-4 text-sm font-bold" :class="shell.cardMuted">
                                0 / No command results yet. Try a user email, Quest reference, transaction reference, or an action like suspend, feature, task, payout, or compliance.
                            </p>
                            <p v-if="commandLoading" class="rounded-2xl p-4 text-sm font-bold" :class="shell.cardMuted">Searching...</p>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <OperationsToastHost />
    </div>
</template>

<script setup>
import AdminThemeToggle from '@/Components/Admin/AdminThemeToggle.vue';
import OperationsToastHost from '@/Pages/Operations/Components/OperationsToastHost.vue';
import HustleSafeLogo from '@/Components/Brand/HustleSafeLogo.vue';
import { useBrandFavicon } from '@/composables/useBrandFavicon';
import { provideAdminTheme } from '@/composables/useAdminTheme';
import { useAdminSidebar } from '@/composables/useAdminSidebar';
import { matchPathPrefix } from '@/utils/navPathMatch';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeftOnRectangleIcon,
    Bars3Icon,
    BellIcon,
    BoltIcon,
    BriefcaseIcon,
    ChartBarSquareIcon,
    ChatBubbleLeftRightIcon,
    CreditCardIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ClipboardDocumentCheckIcon,
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    BookOpenIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    ExclamationTriangleIcon,
    HomeIcon,
    IdentificationIcon,
    MagnifyingGlassIcon,
    NewspaperIcon,
    PhotoIcon,
    RocketLaunchIcon,
    ShieldCheckIcon,
    Squares2X2Icon,
    UserGroupIcon,
    UsersIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, defineComponent, h, nextTick, reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});

const page = usePage();
const { shell, isDark } = provideAdminTheme();
const { collapsed: sidebarCollapsed, toggleCollapsed } = useAdminSidebar();
const logoTheme = computed(() => (isDark.value ? 'dark' : 'light'));

useBrandFavicon(logoTheme);
const mobileNavOpen = ref(false);
const commandOpen = ref(false);
const commandQuery = ref('');
const commandLoading = ref(false);
const commandError = ref('');
const commandInput = ref(null);
const commandResults = reactive({ actions: [], results: [] });
let commandTimer = null;

const CommandSection = defineComponent({
    props: {
        title: { type: String, required: true },
        items: { type: Array, default: () => [] },
    },
    setup(props) {
        return () => props.items.length
            ? h('section', { class: 'mb-3' }, [
                h('p', { class: 'px-2 pb-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500' }, props.title),
                h('div', { class: 'space-y-1' }, props.items.map((item) => h(Link, {
                    href: item.href,
                    class: 'block rounded-2xl px-3 py-3 transition hover:bg-primary-50 dark:hover:bg-white/5',
                    onClick: () => { commandOpen.value = false; },
                }, () => [
                    h('div', { class: 'flex items-center justify-between gap-3' }, [
                        h('p', { class: 'font-black text-slate-900 dark:text-white' }, item.label),
                        item.type ? h('span', { class: 'rounded-full bg-primary-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-primary-700 dark:bg-primary-500/15 dark:text-primary-100' }, item.type) : null,
                    ]),
                    h('p', { class: 'mt-1 text-xs font-semibold text-slate-500' }, item.description || ''),
                ]))),
            ])
            : null;
    },
});

const flashSuccess = computed(() => page.props.flash?.success ?? page.props.flash?.status ?? '');
const firstError = computed(() => {
    const errors = page.props.errors || {};
    const first = Object.values(errors)[0];

    return Array.isArray(first) ? first[0] : (first || '');
});
const flashToken = computed(() => page.props.flash?.token ?? '');
const toastMessage = ref('');
const toastClass = ref(shell.value.flash);
const adminRequestWorking = ref(false);
let toastTimer = null;
let requestWorkingTimer = null;
let removeStartListener = null;
let removeFinishListener = null;
let removeErrorListener = null;
let supportPollTimer = null;

const errorTone = computed(() => (
    isDark.value
        ? 'border-rose-500/40 bg-rose-950/70 text-rose-100'
        : 'border-rose-200 bg-rose-50 text-rose-900'
));
const flashBanner = computed(() => {
    if (flashSuccess.value) {
        return { message: flashSuccess.value, class: shell.value.flash };
    }

    if (firstError.value) {
        return { message: firstError.value, class: errorTone.value };
    }

    return { message: '', class: shell.value.flash };
});

let lastConsumedFlashToken = null;

watch(flashToken, (token) => {
    if (!token || token === lastConsumedFlashToken) {
        return;
    }

    lastConsumedFlashToken = token;

    const success = flashSuccess.value;
    const error = firstError.value;
    const message = success || error;
    if (!message) {
        return;
    }

    toastMessage.value = message;
    toastClass.value = success ? shell.value.flash : errorTone.value;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toastMessage.value = '';
    }, 8000);
});

function openCommandPalette() {
    commandOpen.value = true;
    void nextTick(() => commandInput.value?.focus());
    void runCommandSearch();
}

async function runCommandSearch() {
    commandLoading.value = true;
    commandError.value = '';
    try {
        const { data } = await axios.get('/admin/command/search', {
            params: { q: commandQuery.value },
            timeout: 8000,
        });
        commandResults.actions = data.actions || [];
        commandResults.results = data.results || [];
    } catch (error) {
        commandResults.actions = [];
        commandResults.results = [];
        commandError.value = 'Command search could not load. Please refresh the dashboard and try again.';
    } finally {
        commandLoading.value = false;
    }
}

watch(commandQuery, () => {
    window.clearTimeout(commandTimer);
    commandTimer = window.setTimeout(() => {
        void runCommandSearch();
    }, 180);
});

function onGlobalKeydown(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        openCommandPalette();
    }
    if (event.key === 'Escape' && commandOpen.value) {
        commandOpen.value = false;
    }
}

function showToast(message, tone = 'success') {
    if (!message) {
        return;
    }

    if (toastMessage.value === message) {
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => {
            toastMessage.value = '';
        }, 8000);

        return;
    }

    toastMessage.value = message;
    toastClass.value = tone === 'error' ? errorTone.value : shell.value.flash;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toastMessage.value = '';
    }, 8000);
}

const unreadAlerts = ref(0);
const unreadMessenger = ref(0);
const unreadSupportLive = ref(0);

async function refreshMessengerUnread() {
    try {
        const { data } = await window.axios.get(route('admin.api.messenger.unread-count'));
        unreadMessenger.value = data.count ?? 0;
    } catch {
        unreadMessenger.value = 0;
    }
}

async function refreshUnreadAlerts() {
    try {
        const { data } = await window.axios.get(route('admin.api.alerts.unread-count'));
        unreadAlerts.value = data.count ?? 0;
    } catch {
        unreadAlerts.value = 0;
    }
}

async function refreshSupportUnread() {
    try {
        const { data } = await window.axios.get(route('admin.api.customer-support.unread-count'));
        unreadSupportLive.value = data.count ?? 0;
    } catch {
        unreadSupportLive.value = 0;
    }
}

function openMessengerFromQuery() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('open_messenger') !== '1') {
        return;
    }
    const conversationId = params.get('conversation');
    const query = conversationId ? `?conversation=${encodeURIComponent(conversationId)}` : '';
    router.visit(`${route('admin.messages.index')}${query}`);
}

onMounted(() => {
    void refreshUnreadAlerts();
    void refreshMessengerUnread();
    void refreshSupportUnread();
    openMessengerFromQuery();
    window.addEventListener('admin:notifications-changed', refreshUnreadAlerts);
    window.addEventListener('admin:messenger-changed', refreshMessengerUnread);
    window.addEventListener('admin:support-changed', refreshSupportUnread);
    supportPollTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            void refreshSupportUnread();
        }
    }, 20000);
    window.addEventListener('keydown', onGlobalKeydown);
    removeStartListener = router.on('start', () => {
        window.clearTimeout(requestWorkingTimer);
        requestWorkingTimer = window.setTimeout(() => {
            adminRequestWorking.value = true;
        }, 180);
    });
    removeFinishListener = router.on('finish', () => {
        window.clearTimeout(requestWorkingTimer);
        adminRequestWorking.value = false;
    });
    removeErrorListener = router.on('error', () => {
        showToast('The operation could not be completed. Please review the form and try again.', 'error');
    });
});
onBeforeUnmount(() => {
    window.removeEventListener('admin:notifications-changed', refreshUnreadAlerts);
    window.removeEventListener('admin:messenger-changed', refreshMessengerUnread);
    window.removeEventListener('admin:support-changed', refreshSupportUnread);
    window.removeEventListener('keydown', onGlobalKeydown);
    window.clearTimeout(requestWorkingTimer);
    if (supportPollTimer) {
        window.clearInterval(supportPollTimer);
        supportPollTimer = null;
    }
    removeStartListener?.();
    removeFinishListener?.();
    removeErrorListener?.();
});

const managementNav = computed(() => {
    const nav = page.props.admin_management_nav;
    return Array.isArray(nav) ? nav : [];
});

const visibleManagementNav = computed(() =>
    managementNav.value
        .map((section) => ({
            ...section,
            items: (section.items ?? []).filter((item) => Boolean(item?.key)),
        }))
        .filter((section) => section.items.length > 0),
);

const expandedManagement = reactive({});

const expandedNavGroups = reactive(
    Object.fromEntries(
        [
            'Home',
            'HR management',
            'Revenue & growth',
            'Customer support',
            'Moderation',
            'Marketplace',
            'Risk & compliance',
            'Communications',
            'Data registry',
            'Platform',
        ].map((label) => [label, false]),
    ),
);

function currentManagementResource() {
    const path = page.url.split('?')[0] || '';
    if (path.includes('/admin/management/conversation_threads/')) {
        return 'conversation_threads';
    }

    const query = page.url.includes('?') ? page.url.split('?')[1] : '';

    return new URLSearchParams(query).get('resource');
}

function managementResourceHref(resourceKey) {
    if (!resourceKey) {
        return route('admin.management.index');
    }

    return route('admin.management.index', { resource: resourceKey });
}

function isManagementAreaActive() {
    return page.url.split('?')[0].startsWith('/admin/management');
}

function managementSectionIsActive(section) {
    const activeResource = currentManagementResource();

    return section.items?.some((item) => item.key === activeResource) ?? false;
}

function toggleManagementSection(section) {
    const willOpen = !expandedManagement[section.key];
    visibleManagementNav.value.forEach((entry) => {
        expandedManagement[entry.key] = false;
    });
    expandedManagement[section.key] = willOpen;
    openOnlyNavGroup('Data registry');
}

function isManagementItemActive(item) {
    if (item.key === 'conversation_threads' && page.url.includes('/admin/management/conversation_threads/')) {
        return true;
    }

    return currentManagementResource() === item.key;
}

function toggleNavGroup(label) {
    if (expandedNavGroups[label]) {
        expandedNavGroups[label] = false;
        return;
    }

    openOnlyNavGroup(label);
}

const navGroups = [
    {
        label: 'Home',
        collapsible: true,
        items: [
            { label: 'Dashboard', href: route('admin.dashboard'), icon: HomeIcon, match: (p) => p === '/admin' || p === '/admin/' },
            { label: 'Insights', href: route('admin.insights.index'), icon: ChartBarSquareIcon, match: (p) => p.startsWith('/admin/insights') },
            { label: 'Lifecycle analytics', href: route('admin.lifecycle-analytics.index'), icon: ChartBarSquareIcon, match: (p) => p.startsWith('/admin/lifecycle-analytics') },
            { label: 'Live Activity', href: route('admin.live-activity.index'), icon: BoltIcon, match: (p) => p.startsWith('/admin/live-activity') },
            { label: 'Alert Centre', href: route('admin.alerts.index'), icon: BellIcon, match: (p) => p.startsWith('/admin/alerts') },
            { label: 'Tasks', href: route('admin.tasks.index'), icon: ClipboardDocumentListIcon, match: (p) => p.startsWith('/admin/tasks') },
            { label: 'Platform settings', href: route('admin.settings.index'), icon: Cog6ToothIcon, matchFull: (url) => url.startsWith('/admin/settings') && !url.includes('section=maintenance'), match: (p) => p.startsWith('/admin/settings') },
        ],
    },
    {
        label: 'HR management',
        collapsible: true,
        items: [
            { label: 'Role management', href: route('admin.hr.roles.index'), icon: UserGroupIcon, match: (p) => p.startsWith('/admin/hr/roles') },
            { label: 'Leave management', href: route('admin.hr.leave.index'), icon: UserGroupIcon, match: (p) => p.startsWith('/admin/hr/leave') },
            { label: 'Payment management', href: route('admin.hr.payments.index'), icon: UserGroupIcon, match: (p) => p.startsWith('/admin/hr/payments') },
            { label: 'Monthly payroll', href: route('admin.hr.payroll-monthly.index'), icon: UserGroupIcon, match: (p) => p.startsWith('/admin/hr/payroll-monthly') },
        ],
    },
    {
        label: 'Revenue & growth',
        collapsible: true,
        items: [
            { label: 'Payments & Escrow', href: route('admin.payments-escrow.index'), icon: CreditCardIcon, match: (p) => p.startsWith('/admin/payments-escrow') },
            { label: 'Financial review queue', href: route('admin.financial-review.index'), icon: CreditCardIcon, match: (p) => p.startsWith('/admin/financial-review') },
            { label: 'Financial Control', href: route('admin.financial.index'), icon: CreditCardIcon, match: (p) => p.startsWith('/admin/financial') && !p.startsWith('/admin/financial-review') },
            { label: 'Treasury', href: route('admin.treasury.index'), icon: CreditCardIcon, match: (p) => p.startsWith('/admin/treasury') },
            { label: 'Reports & analytics', href: route('admin.reports.index'), icon: ChartBarSquareIcon, match: (p) => p.startsWith('/admin/reports') },
            { label: 'Promotions & Growth', href: route('admin.promotions.index'), icon: RocketLaunchIcon, match: (p) => p.startsWith('/admin/promotions') },
            { label: 'Email Broadcasts', href: route('admin.communications.email-broadcasts.index'), icon: EnvelopeIcon, match: (p) => p.startsWith('/admin/communications/email-broadcasts') },
        ],
    },
    {
        label: 'Customer support',
        collapsible: true,
        items: [
            { label: 'Live support', href: route('admin.customer-support.index'), icon: ChatBubbleLeftRightIcon, match: (p) => p.startsWith('/admin/customer-support') && !p.includes('/performance'), badge: () => unreadSupportLive.value },
            { label: 'Support performance', href: route('admin.customer-support.performance'), icon: ChartBarSquareIcon, match: (p) => p.startsWith('/admin/customer-support/performance') },
            { label: 'Support Tickets', href: route('admin.support-tickets.index'), icon: ClipboardDocumentListIcon, match: (p) => p.startsWith('/admin/support-tickets') },
            { label: 'Knowledge base', href: route('admin.knowledge-base.index'), icon: BookOpenIcon, match: (p) => p.startsWith('/admin/knowledge-base') },
        ],
    },
    {
        label: 'Moderation',
        collapsible: true,
        items: [
            { label: 'Onboarding quality control', href: route('admin.onboarding-quality.index'), icon: ShieldCheckIcon, match: (p) => matchPathPrefix(p, '/admin/onboarding-quality', { exclude: ['/admin/onboarding-quality/flagged-profiles'] }) },
            { label: 'Flagged profiles', href: route('admin.onboarding-quality.flagged'), icon: ShieldCheckIcon, match: (p) => matchPathPrefix(p, '/admin/onboarding-quality/flagged-profiles') },
            { label: 'Quest & proposal review', href: route('admin.moderation.index'), icon: ShieldCheckIcon, match: (p) => p.startsWith('/admin/moderation') },
            { label: 'Content Moderation', href: route('admin.content-moderation.index'), icon: ShieldCheckIcon, match: (p) => p.startsWith('/admin/content-moderation') },
            { label: 'Conversation monitoring', href: route('admin.conversation-monitoring.index'), icon: ChatBubbleLeftRightIcon, match: (p) => p.startsWith('/admin/conversation-monitoring') },
            { label: 'Proactive outreach', href: route('admin.outreach.index'), icon: ChatBubbleLeftRightIcon, match: (p) => p.startsWith('/admin/outreach') },
            { label: 'Response templates', href: route('admin.response-templates.index'), icon: DocumentTextIcon, match: (p) => p.startsWith('/admin/response-templates') },
        ],
    },
    {
        label: 'Marketplace',
        collapsible: true,
        items: [
            { label: 'Quests', href: route('admin.quests.index'), icon: BriefcaseIcon, match: (p) => p.startsWith('/admin/quests') },
            { label: 'Proposals', href: route('admin.proposals.index'), icon: ClipboardDocumentCheckIcon, match: (p) => p.startsWith('/admin/proposals') },
            { label: 'Categories', href: route('admin.categories.index'), icon: Squares2X2Icon, match: (p) => p.startsWith('/admin/categories') },
            { label: 'Users', href: route('admin.users.index'), icon: UsersIcon, match: (p) => p.startsWith('/admin/users') },
            { label: 'Verification Engine', href: route('admin.verification-engine.index'), icon: IdentificationIcon, match: (p) => p.startsWith('/admin/verification-engine') || p.startsWith('/admin/kyc') },
            { label: 'Portfolio review', href: route('admin.portfolio-review.index'), icon: PhotoIcon, match: (p) => p.startsWith('/admin/portfolio-review') },
            { label: 'Disputes', href: route('admin.disputes.index'), icon: ExclamationTriangleIcon, match: (p) => p.startsWith('/admin/disputes') },
        ],
    },
    {
        label: 'Risk & compliance',
        collapsible: true,
        items: [
            { label: 'User Intelligence', href: route('admin.intelligence.index'), icon: UserGroupIcon, match: (p) => p.startsWith('/admin/intelligence') },
            { label: 'Trust & risk', href: route('admin.trust-risk.index'), icon: ShieldCheckIcon, match: (p) => p.startsWith('/admin/trust-risk') || p.startsWith('/admin/fraud-risk') },
            { label: 'Compliance', href: route('admin.compliance.index'), icon: DocumentTextIcon, match: (p) => p.startsWith('/admin/compliance') },
        ],
    },
    {
        label: 'Communications',
        collapsible: true,
        items: [
            { label: 'Team chat', href: route('admin.team-chat.index'), icon: ChatBubbleLeftRightIcon, match: (p) => p.startsWith('/admin/team-chat') },
            { label: 'Direct messages', href: route('admin.messages.index'), icon: ChatBubbleLeftRightIcon, match: (p) => p.startsWith('/admin/messages') },
            { label: 'SEO & Content', href: route('admin.content.index'), icon: NewspaperIcon, match: (p) => p === '/admin/content' || (p.startsWith('/admin/content/') && !p.startsWith('/admin/content-moderation')) },
            { label: 'Staff Digest', href: route('admin.activity.digest'), icon: ClipboardDocumentListIcon, match: (p) => p.startsWith('/admin/activity/digest') },
        ],
    },
    { label: 'Data registry', management: true, collapsible: true, items: [] },
    {
        label: 'Platform',
        collapsible: true,
        items: [
            { label: 'Dashboard Guide', href: route('admin.documentation.guide'), icon: BookOpenIcon, match: (p) => p.startsWith('/admin/documentation') },
            { label: 'Staff & roles', href: route('admin.staff.index'), icon: UserGroupIcon, match: (p) => p.startsWith('/admin/staff') },
            { label: 'Audit log', href: route('admin.activity.index'), icon: ClipboardDocumentListIcon, match: (p) => p.startsWith('/admin/activity') && !p.includes('/digest') },
            { label: 'Completion events', href: route('admin.quest-completion-events.index'), icon: ClipboardDocumentCheckIcon, match: (p) => p.startsWith('/admin/quest-completion-events') },
            { label: 'Completion events', href: route('admin.quest-completion-events.index'), icon: ClipboardDocumentListIcon, match: (p) => p.startsWith('/admin/quest-completion-events') },
            { label: 'Engagement policy', href: route('admin.engagement-policy'), icon: DocumentTextIcon, match: (p) => p.startsWith('/admin/engagement-policy') },
            { label: 'Settings', href: route('admin.settings.index'), icon: Cog6ToothIcon, matchFull: (url) => url.startsWith('/admin/settings') && !url.includes('section=maintenance'), match: (p) => p.startsWith('/admin/settings') },
        ],
    },
];

function isActive(item) {
    const p = page.url.split('?')[0] || '';
    if (typeof item.matchFull === 'function') {
        return item.matchFull(page.url);
    }

    return item.match(p);
}

function findActiveNavGroupLabel() {
    if (isManagementAreaActive()) {
        return 'Data registry';
    }

    for (const group of navGroups) {
        if (group.management || !group.items?.length) {
            continue;
        }

        if (group.items.some((item) => item.href && isActive(item))) {
            return group.label;
        }
    }

    return null;
}

function openOnlyNavGroup(label) {
    for (const group of navGroups) {
        if (! group.collapsible) {
            continue;
        }

        expandedNavGroups[group.label] = label !== null && group.label === label;
    }
}

function syncNavGroupsFromRoute() {
    const activeGroup = findActiveNavGroupLabel();
    openOnlyNavGroup(activeGroup);
}

function syncExpandedManagementSections() {
    if (!visibleManagementNav.value.length) {
        return;
    }

    const activeResource = currentManagementResource();
    const onManagement = isManagementAreaActive();

    visibleManagementNav.value.forEach((section) => {
        const hasActive = section.items?.some((item) => item.key === activeResource);
        expandedManagement[section.key] = onManagement && hasActive;
    });
}

function onChildNavClick(groupLabel) {
    openOnlyNavGroup(groupLabel);
    mobileNavOpen.value = false;
}

watch(
    () => page.url,
    () => {
        syncNavGroupsFromRoute();
        syncExpandedManagementSections();
    },
    { immediate: true },
);

</script>
