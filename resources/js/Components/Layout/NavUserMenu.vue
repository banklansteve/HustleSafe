<template>
    <div ref="rootRef" class="relative" @click.stop>
        <button
            type="button"
            class="group flex max-w-[14rem] items-center gap-2 rounded-full border border-slate-200/90 bg-white py-1 pl-1 pr-3 shadow-sm transition hover:border-primary-200 hover:bg-primary-50/40 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 sm:max-w-[18rem] sm:pr-3.5"
            :aria-expanded="open"
            aria-haspopup="true"
            aria-label="Account menu"
            @click.stop="open = !open"
        >
            <span
                class="relative flex h-11 w-11 shrink-0 items-stretch justify-stretch overflow-hidden rounded-full bg-gradient-to-br from-primary-600 to-primary-800 ring-2 ring-white shadow-inner shadow-primary-900/20"
            >
                <img
                    v-if="avatarUrl"
                    :src="avatarUrl"
                    :alt="displayName"
                    class="h-full w-full object-cover"
                />
                <span
                    v-else
                    class="flex h-full w-full items-center justify-center text-base font-black tracking-tight text-white sm:text-lg"
                >
                    {{ initials }}
                </span>
            </span>
            <span class="min-w-0 flex-1 text-left">
                <span class="block truncate text-sm font-bold text-slate-900">{{ displayName }}</span>
            </span>
            <ChevronDownIcon
                class="h-4 w-4 shrink-0 text-slate-400 transition duration-200 ease-out group-hover:text-primary-600"
                :class="open ? '-rotate-180 text-primary-600' : ''"
                aria-hidden="true"
            />
        </button>

        <!-- Mobile dim -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-show="open"
                class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm sm:hidden"
                aria-hidden="true"
                @click="open = false"
            />
        </Transition>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
            enter-to-class="opacity-100 translate-y-0 sm:scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 sm:scale-100"
            leave-to-class="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
        >
            <div
                v-show="open"
                class="fixed left-3 right-3 top-[4.75rem] z-50 max-h-[min(32rem,calc(100dvh-6rem))] overflow-y-auto overscroll-contain rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl shadow-slate-900/20 ring-1 ring-slate-200/90 sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-3 sm:max-h-[min(28rem,calc(100vh-8rem))] sm:w-[min(22rem,calc(100vw-1.5rem))] sm:rounded-2xl sm:p-2.5"
                role="menu"
                @click.stop
            >
                <div v-if="mobileQuickLinks.length" class="mb-1 border-b border-slate-100 pb-2 sm:hidden">
                    <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Shortcuts
                    </p>
                    <div class="grid gap-1">
                        <Link
                            v-for="item in mobileQuickLinks"
                            :key="item.href"
                            :href="item.href"
                            prefetch="false"
                            preserve-scroll
                            class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition"
                            :class="
                                item.isActive(pathname)
                                    ? 'bg-primary-50 text-primary-950 ring-2 ring-inset ring-primary-200'
                                    : 'text-slate-800 hover:bg-primary-50 hover:text-primary-900'
                            "
                            role="menuitem"
                            @click="open = false"
                        >
                            <component :is="item.icon" class="h-5 w-5 text-primary-600" aria-hidden="true" />
                            {{ item.label }}
                        </Link>
                    </div>
                </div>

                <div class="grid gap-0.5">
                    <Link
                        v-for="item in menuLinks"
                        :key="item.href + item.label"
                        :href="item.href"
                        prefetch="false"
                        preserve-scroll
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition"
                        :class="
                            item.isActive(pathname)
                                ? 'bg-primary-50 text-primary-950 ring-2 ring-inset ring-primary-200'
                                : 'text-slate-800 hover:bg-primary-50 hover:text-primary-900'
                        "
                        role="menuitem"
                        @click="open = false"
                    >
                        <component
                            :is="item.icon"
                            class="h-5 w-5 shrink-0"
                            :class="item.isActive(pathname) ? 'text-primary-600' : 'text-slate-400'"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">{{ item.label }}</span>
                    </Link>

                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-rose-700 transition hover:bg-rose-50"
                        role="menuitem"
                        @click="logout"
                    >
                        <ArrowRightOnRectangleIcon class="h-5 w-5 shrink-0" aria-hidden="true" />
                        Log out
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import {
    ArrowRightOnRectangleIcon,
    BriefcaseIcon,
    ChartBarIcon,
    ChartPieIcon,
    ChevronDownIcon,
    ClipboardDocumentListIcon,
    HomeIcon,
    MagnifyingGlassIcon,
    ShieldCheckIcon,
    UserCircleIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';
import { usePathname } from '@/composables/usePathname';
import { usePlatformRoleNav } from '@/composables/usePlatformRoleNav';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();
const pathname = usePathname(page);
const open = ref(false);
const rootRef = ref(null);

const user = computed(() => page.props.auth?.user ?? null);
const avatarUrl = computed(() => user.value?.avatar_url ?? null);
const displayName = computed(() => user.value?.name ?? 'Account');

const initials = computed(() => {
    const n = displayName.value || '';
    const parts = n.trim().split(/\s+/);

    return ((parts[0]?.[0] || 'H') + (parts[1]?.[0] || '')).toUpperCase();
});

const { roleSlug, isFreelancer, showClientTools } = usePlatformRoleNav();

const mobileQuickLinks = computed(() => {
    const items = [
        {
            href: route('dashboard'),
            label: 'Dashboard',
            icon: HomeIcon,
            isActive: (path) => path === '/dashboard',
        },
    ];
    if (roleSlug.value === 'super_admin' && page.props.admin_entry_url) {
        items.push({
            href: page.props.admin_entry_url,
            label: 'Super admin',
            icon: ChartPieIcon,
            isActive: (path) => path.startsWith('/admin'),
        });
    }
    if (roleSlug.value === 'admin' && page.props.operations_entry_url) {
        items.push({
            href: page.props.operations_entry_url,
            label: 'Operations',
            icon: ChartBarIcon,
            isActive: (path) => path.startsWith('/operations'),
        });
    }
    if (showClientTools.value) {
        items.push({
            href: route('quests.index'),
            label: 'My quests',
            icon: ClipboardDocumentListIcon,
            isActive: (path) => path === '/quests',
        });
        items.push({
            href: route('quests.create'),
            label: 'Create quest',
            icon: BriefcaseIcon,
            isActive: (path) => path.startsWith('/quests/create'),
        });
        items.push({
            href: route('quests.explore'),
            label: 'Browse quests',
            icon: MagnifyingGlassIcon,
            isActive: (path) => path.startsWith('/quests/explore'),
        });
    }
    if (isFreelancer.value) {
        items.push({
            href: route('portfolio.manage'),
            label: 'Portfolio',
            icon: BriefcaseIcon,
            isActive: (path) =>
                path.startsWith('/portfolio/manage')
                || path.startsWith('/portfolio/create')
                || /\/portfolio\/\d+\/edit$/.test(path),
        });
        items.push({
            href: route('quests.explore'),
            label: 'Browse quests',
            icon: MagnifyingGlassIcon,
            isActive: (path) => path.startsWith('/quests/explore'),
        });
    }

    return items;
});

function accountHubActive(p) {
    if (p.startsWith('/account/security')) {
        return false;
    }
    return p === '/account' || p.startsWith('/account/credentials');
}

const menuLinks = computed(() => {
    const items = [
        {
            href: route('account.show'),
            label: 'Account & profile',
            icon: UserCircleIcon,
            isActive: (p) => accountHubActive(p),
        },
        {
            href: route('account.security.edit'),
            label: 'Security & photo',
            icon: WrenchScrewdriverIcon,
            isActive: (p) => p.startsWith('/account/security'),
        },
        {
            href: route('verifications.index'),
            label: 'Trust & verifications',
            icon: ShieldCheckIcon,
            isActive: (p) => p.startsWith('/verifications'),
        },
    ];

    if (roleSlug.value === 'super_admin' && page.props.admin_entry_url) {
        items.push({
            href: page.props.admin_entry_url,
            label: 'Super admin console',
            icon: ChartPieIcon,
            isActive: (p) => p.startsWith('/admin'),
        });
    }
    if (roleSlug.value === 'admin' && page.props.operations_entry_url) {
        items.push({
            href: page.props.operations_entry_url,
            label: 'Operations console',
            icon: ChartBarIcon,
            isActive: (p) => p.startsWith('/operations'),
        });
    }

    return items;
});

const logoutForm = useForm({});

function logout() {
    open.value = false;
    logoutForm.post(route('logout'));
}

function onDocClick(e) {
    if (!open.value || !rootRef.value) {
        return;
    }
    if (rootRef.value.contains(e.target)) {
        return;
    }
    open.value = false;
}

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));

watch(
    () => page.url,
    () => {
        open.value = false;
    },
);
</script>
