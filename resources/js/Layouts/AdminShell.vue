<template>
    <div class="min-h-screen" :class="[shell.root, isDark ? 'admin-theme-dark' : 'admin-theme-light']">
        <div class="flex min-h-screen flex-col lg:flex-row">
            <aside
                data-admin-sidebar
                class="relative border-b transition-[width] duration-300 ease-out px-3 py-4 lg:shrink-0 lg:border-b-0 lg:border-r lg:py-6"
                :class="[shell.aside, sidebarCollapsed ? 'lg:w-[4.5rem] lg:px-2' : 'lg:w-72 lg:px-5']"
                aria-label="Super admin navigation"
            >
                <div class="flex items-center justify-between gap-2">
                    <Link
                        :href="route('admin.dashboard')"
                        prefetch
                        class="flex min-w-0 items-center gap-2.5 transition"
                        :title="sidebarCollapsed ? 'Super admin home' : undefined"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-black shadow-md"
                            :class="shell.brandMark"
                        >
                            HS
                        </span>
                        <div v-show="!sidebarCollapsed" class="min-w-0 hidden lg:block">
                            <p class="text-[10px] font-black uppercase tracking-[0.28em]" :class="shell.brandEyebrow">
                                HustleSafe
                            </p>
                            <p class="font-display text-base font-black tracking-tight" :class="shell.brandTitle">Super admin</p>
                        </div>
                    </Link>
                    <Link
                        href="/dashboard"
                        prefetch
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

                <nav class="mt-6 space-y-5" :class="sidebarCollapsed ? 'lg:space-y-3' : ''">
                    <div v-for="group in navGroups" :key="group.label">
                        <p
                            v-show="!sidebarCollapsed"
                            class="hidden px-2 pb-2 text-[10px] font-black uppercase tracking-wider lg:block"
                            :class="shell.label"
                        >
                            {{ group.label }}
                        </p>
                        <div
                            v-if="group.management && managementNav.length"
                            v-show="!sidebarCollapsed"
                            class="hidden space-y-2 lg:block"
                        >
                            <div v-for="section in managementNav" :key="section.key" class="space-y-0.5">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-left text-[10px] font-black uppercase tracking-wider transition"
                                    :class="shell.btnGhost"
                                    @click="toggleManagementSection(section.key)"
                                >
                                    <span>{{ section.label }}</span>
                                    <ChevronRightIcon
                                        class="h-3.5 w-3.5 transition"
                                        :class="expandedManagement[section.key] ? 'rotate-90' : ''"
                                    />
                                </button>
                                <div v-show="expandedManagement[section.key]" class="space-y-0.5 border-l-2 pl-2" :class="shell.tableDivide">
                                    <Link
                                        v-for="item in section.items"
                                        :key="item.key"
                                        :href="item.href"
                                        prefetch
                                        class="block rounded-lg px-2 py-1.5 text-xs font-bold transition"
                                        :class="[
                                            item.indent ? 'ml-1' : '',
                                            isManagementItemActive(item) ? shell.navActive : shell.navIdle,
                                        ]"
                                    >
                                        {{ item.label }}
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="!group.management"
                            class="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:gap-0.5 lg:overflow-visible lg:pb-0"
                            :class="sidebarCollapsed ? 'lg:items-center' : ''"
                        >
                            <Link
                                v-for="item in group.items"
                                :key="item.href"
                                :href="item.href"
                                prefetch
                                :title="sidebarCollapsed ? item.label : undefined"
                                class="group flex items-center gap-3 rounded-xl text-sm font-bold transition"
                                :class="[
                                    sidebarCollapsed ? 'lg:justify-center lg:px-2 lg:py-2.5' : 'whitespace-nowrap px-3 py-2 lg:px-3 lg:py-2.5',
                                    isActive(item) ? shell.navActive : shell.navIdle,
                                ]"
                            >
                                <component
                                    :is="item.icon"
                                    class="h-5 w-5 shrink-0"
                                    :class="isActive(item) ? shell.navIconActive : shell.navIconIdle"
                                    aria-hidden="true"
                                />
                                <span class="lg:hidden">{{ item.label }}</span>
                                <span v-show="!sidebarCollapsed" class="hidden lg:inline text-inherit">{{ item.label }}</span>
                            </Link>
                        </div>
                    </div>
                </nav>

                <div class="mt-8 hidden flex-col gap-2 lg:flex" :class="sidebarCollapsed ? 'items-center' : ''">
                    <AdminThemeToggle :compact="sidebarCollapsed" />
                    <Link
                        href="/dashboard"
                        prefetch
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
                            <AdminThemeToggle class="lg:hidden" />
                            <p v-if="subtitle" class="max-w-xl text-xs font-semibold leading-relaxed" :class="shell.canvasMuted">
                                {{ subtitle }}
                            </p>
                        </div>
                    </div>
                </header>
                <main class="admin-main-surface flex-1 space-y-2 px-4 py-4 sm:px-6 lg:px-8" :class="shell.main">
                    <div
                        v-if="flashSuccess"
                        class="mb-6 rounded-2xl border px-4 py-3 text-sm font-semibold"
                        :class="shell.flash"
                        role="status"
                    >
                        {{ flashSuccess }}
                    </div>
                    <slot />
                </main>
            </div>
        </div>

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
                            <p class="px-2 pb-2 text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                {{ group.label }}
                            </p>
                            <div class="space-y-0.5">
                                <Link
                                    v-for="item in group.items"
                                    :key="item.href"
                                    :href="item.href"
                                    prefetch
                                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition"
                                    :class="isActive(item) ? shell.navActive : shell.navIdle"
                                    @click="mobileNavOpen = false"
                                >
                                    <component
                                        :is="item.icon"
                                        class="h-5 w-5 shrink-0"
                                        :class="isActive(item) ? shell.navIconActive : shell.navIconIdle"
                                        aria-hidden="true"
                                    />
                                    <span class="text-inherit">{{ item.label }}</span>
                                </Link>
                            </div>
                        </div>
                    </nav>
                    <AdminThemeToggle class="mt-4" />
                </aside>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import AdminThemeToggle from '@/Components/Admin/AdminThemeToggle.vue';
import { provideAdminTheme } from '@/composables/useAdminTheme';
import { useAdminSidebar } from '@/composables/useAdminSidebar';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftOnRectangleIcon,
    Bars3Icon,
    BoltIcon,
    BriefcaseIcon,
    ChartBarSquareIcon,
    CreditCardIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    HomeIcon,
    IdentificationIcon,
    RocketLaunchIcon,
    ShieldCheckIcon,
    UserGroupIcon,
    UsersIcon,
    WrenchScrewdriverIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});

const page = usePage();
const { shell, isDark } = provideAdminTheme();
const { collapsed: sidebarCollapsed, toggleCollapsed } = useAdminSidebar();
const mobileNavOpen = ref(false);

const flashSuccess = computed(() => page.props.flash?.success ?? '');

const managementNav = computed(() => page.props.admin_management_nav ?? []);
const expandedManagement = reactive({});

function currentManagementResource() {
    const path = page.url.split('?')[0] || '';
    if (path.includes('/admin/management/conversation_threads/')) {
        return 'conversation_threads';
    }

    const query = page.url.includes('?') ? page.url.split('?')[1] : '';

    return new URLSearchParams(query).get('resource');
}

function syncExpandedManagementSections() {
    if (!managementNav.value.length) {
        return;
    }

    const activeResource = currentManagementResource();
    managementNav.value.forEach((section) => {
        const hasActive = section.items?.some((item) => item.key === activeResource);
        if (hasActive) {
            expandedManagement[section.key] = true;
        } else if (expandedManagement[section.key] === undefined) {
            expandedManagement[section.key] = false;
        }
    });
}

watch(() => page.url, syncExpandedManagementSections, { immediate: true });

function toggleManagementSection(key) {
    expandedManagement[key] = !expandedManagement[key];
}

function isManagementItemActive(item) {
    if (item.key === 'conversation_threads' && page.url.includes('/admin/management/conversation_threads/')) {
        return true;
    }

    return currentManagementResource() === item.key;
}

function isManagementAreaActive() {
    return page.url.split('?')[0].startsWith('/admin/management');
}

const navGroups = [
    {
        label: 'Overview',
        items: [
            {
                label: 'Dashboard',
                href: route('admin.dashboard'),
                icon: HomeIcon,
                match: (p) => p === '/admin' || p === '/admin/',
            },
            {
                label: 'Reports & analytics',
                href: route('admin.reports.index'),
                icon: ChartBarSquareIcon,
                match: (p) => p.startsWith('/admin/reports'),
            },
            {
                label: 'Financial Control',
                href: route('admin.financial.index'),
                icon: CreditCardIcon,
                match: (p) => p.startsWith('/admin/financial'),
            },
            {
                label: 'Promotions & Growth',
                href: route('admin.promotions.index'),
                icon: RocketLaunchIcon,
                match: (p) => p.startsWith('/admin/promotions'),
            },
            {
                label: 'Live Activity',
                href: route('admin.live-activity.index'),
                icon: BoltIcon,
                match: (p) => p.startsWith('/admin/live-activity'),
            },
        ],
    },
    {
        label: 'Operations',
        items: [
            {
                label: 'Quests',
                href: route('admin.quests.index'),
                icon: BriefcaseIcon,
                match: (p) => p.startsWith('/admin/quests'),
            },
            {
                label: 'Users',
                href: route('admin.users.index'),
                icon: UsersIcon,
                match: (p) => p.startsWith('/admin/users'),
            },
            {
                label: 'KYC Centre',
                href: route('admin.kyc.index'),
                icon: IdentificationIcon,
                match: (p) => p.startsWith('/admin/kyc'),
            },
            {
                label: 'Content Moderation',
                href: route('admin.content-moderation.index'),
                icon: ShieldCheckIcon,
                match: (p) => p.startsWith('/admin/content-moderation'),
            },
            {
                label: 'Disputes',
                href: route('admin.disputes.index'),
                icon: ExclamationTriangleIcon,
                match: (p) => p.startsWith('/admin/disputes'),
            },
        ],
    },
    {
        label: 'Data registry',
        management: true,
        items: [],
    },
    {
        label: 'Governance',
        items: [
            {
                label: 'Staff & roles',
                href: route('admin.staff.index'),
                icon: UserGroupIcon,
                match: (p) => p.startsWith('/admin/staff'),
            },
            {
                label: 'Audit log',
                href: route('admin.activity.index'),
                icon: ClipboardDocumentListIcon,
                match: (p) => p.startsWith('/admin/activity'),
            },
            {
                label: 'Engagement policy',
                href: route('admin.engagement-policy'),
                icon: DocumentTextIcon,
                match: (p) => p.startsWith('/admin/engagement-policy'),
            },
            {
                label: 'Settings',
                href: route('admin.settings.index'),
                icon: Cog6ToothIcon,
                match: (p) => p.startsWith('/admin/settings'),
            },
        ],
    },
];

function isActive(item) {
    const p = page.url.split('?')[0] || '';

    return item.match(p);
}
</script>
