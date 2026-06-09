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
                <Link :href="route('operations.dashboard')" prefetch="false" class="flex min-w-0 flex-col" @click.prevent="visitNav(route('operations.dashboard'))">
                    <HustleSafeLogo variant="lockup" theme="light" lockup-class="h-8 w-auto max-w-[9.5rem]" />
                    <span class="mt-0.5 hidden text-[10px] font-black uppercase tracking-[0.2em] text-primary-700 sm:block">Staff Admin</span>
                </Link>

                <div class="flex min-w-0 flex-1 items-center justify-end gap-2">
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
                                <div v-if="desktopOpen === group.label" class="absolute right-0 mt-2 w-72 rounded-3xl border border-slate-200 bg-white p-3 shadow-2xl shadow-slate-900/15">
                                    <template v-for="item in group.items" :key="item.label">
                                        <button
                                            v-if="item.action === 'messenger'"
                                            type="button"
                                            class="block w-full rounded-2xl px-4 py-3 text-left transition text-slate-700 hover:bg-primary-50 hover:text-primary-900"
                                            @click="messengerOpen = true; desktopOpen = ''"
                                        >
                                            <span class="block text-sm font-black">{{ item.label }}</span>
                                            <span class="mt-1 block text-xs font-semibold opacity-75">{{ item.hint }}</span>
                                        </button>
                                        <Link
                                            v-else
                                            :href="item.href"
                                            prefetch="false"
                                            class="block rounded-2xl px-4 py-3 transition"
                                            :class="isActive(item) ? 'bg-primary-700 text-white shadow-md' : 'text-slate-700 hover:bg-primary-50 hover:text-primary-900'"
                                            @click.prevent="visitNav(item.href); desktopOpen = ''"
                                        >
                                            <span class="flex items-center gap-2">
                                                <span class="block text-sm font-black">{{ item.label }}</span>
                                                <span v-if="item.badge?.() > 0" class="rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-black text-white">{{ item.badge() > 99 ? '99+' : item.badge() }}</span>
                                            </span>
                                            <span class="mt-1 block text-xs font-semibold opacity-75">{{ item.hint }}</span>
                                        </Link>
                                    </template>
                                </div>
                            </Transition>
                        </div>
                    </nav>

                    <div class="hidden items-center gap-2 sm:flex">
                        <button
                            type="button"
                            class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-800 shadow-sm hover:bg-primary-50"
                            title="Direct messages"
                            @click="messengerOpen = true"
                        >
                            <span class="sr-only">Messages</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                            <span v-if="unreadMessenger > 0" class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary-600 px-1 text-[10px] font-black text-white">{{ unreadMessenger > 99 ? '99+' : unreadMessenger }}</span>
                        </button>
                        <Link
                            :href="route('operations.notifications.index')"
                            prefetch="false"
                            class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-primary-200 bg-primary-50 text-primary-900 shadow-sm hover:bg-primary-100"
                            title="Notifications"
                            @click.prevent="visitNav(route('operations.notifications.index'))"
                        >
                            <span class="sr-only">Notifications</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.454 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                            <span v-if="unreadAlerts > 0" class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-black text-white">{{ unreadAlerts > 99 ? '99+' : unreadAlerts }}</span>
                        </Link>
                    </div>

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
                                            <template v-for="item in group.items" :key="item.label">
                                                <button
                                                    v-if="item.action === 'messenger'"
                                                    type="button"
                                                    class="w-full rounded-2xl px-4 py-3 text-left transition text-slate-700 hover:bg-primary-50 hover:text-primary-900"
                                                    @click="messengerOpen = true; menuOpen = false"
                                                >
                                                    <span class="block text-sm font-black">{{ item.label }}</span>
                                                    <span class="mt-1 block text-xs font-semibold opacity-75">{{ item.hint }}</span>
                                                </button>
                                                <Link
                                                    v-else
                                                    :href="item.href"
                                                    prefetch="false"
                                                    class="rounded-2xl px-4 py-3 transition"
                                                    :class="isActive(item) ? 'bg-primary-700 text-white shadow-md' : 'text-slate-700 hover:bg-primary-50 hover:text-primary-900'"
                                                    @click.prevent="visitNav(item.href); menuOpen = false"
                                                >
                                                    <span class="flex items-center gap-2">
                                                        <span class="block text-sm font-black">{{ item.label }}</span>
                                                        <span v-if="item.badge?.() > 0" class="rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-black text-white">{{ item.badge() > 99 ? '99+' : item.badge() }}</span>
                                                    </span>
                                                    <span class="mt-1 block text-xs font-semibold opacity-75">{{ item.hint }}</span>
                                                </Link>
                                            </template>
                                        </div>
                                    </section>
                                </nav>
                                <Link href="/dashboard" prefetch="false" class="mt-3 block rounded-2xl border border-slate-200 px-4 py-3 text-center text-sm font-black text-slate-700 hover:bg-slate-50" @click="menuOpen = false">
                                    Back to main app
                                </Link>
                            </div>
                        </Transition>
                    </div>

                    <div
                        class="flex min-w-0 max-w-[7.5rem] items-center gap-2 rounded-2xl border border-primary-200 bg-primary-50 px-2 py-1.5 shadow-sm sm:max-w-none sm:px-3 sm:py-2"
                        :title="`Signed in as @${staffUsername}`"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-700 text-xs font-black text-white"
                            aria-hidden="true"
                        >
                            {{ staffInitial }}
                        </span>
                        <span class="min-w-0 truncate text-xs font-black leading-tight text-primary-900 sm:text-sm">
                            @{{ staffUsername }}
                        </span>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-2xl border border-rose-200 bg-white px-3 py-2.5 text-xs font-black uppercase tracking-wide text-rose-700 shadow-sm hover:bg-rose-50 sm:px-4"
                        title="Log out"
                        :disabled="logoutForm.processing"
                        @click="logout"
                    >
                        <ArrowRightOnRectangleIcon class="h-4 w-4 shrink-0" aria-hidden="true" />
                        <span class="hidden sm:inline">Log out</span>
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
            <div class="mb-4 rounded-xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.25em] text-primary-700">Operations</p>
                        <h1 class="font-display mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-2 max-w-2xl text-sm font-semibold leading-relaxed text-slate-600">{{ subtitle }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="relative inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-slate-800 hover:bg-primary-50"
                            title="Direct messages"
                            @click="messengerOpen = true"
                        >
                            Messages
                            <span v-if="unreadMessenger > 0" class="ml-2 rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-black text-white">{{ unreadMessenger > 99 ? '99+' : unreadMessenger }}</span>
                        </button>
                        <Link
                            :href="route('operations.notifications.index')"
                            prefetch="false"
                            class="relative inline-flex items-center justify-center rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-primary-900 hover:bg-primary-100"
                            @click.prevent="visitNav(route('operations.notifications.index'))"
                        >
                            Alerts
                            <span v-if="unreadAlerts > 0" class="ml-2 rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-black text-white">{{ unreadAlerts > 99 ? '99+' : unreadAlerts }}</span>
                        </Link>
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
            </div>

            <div v-if="flashSuccess" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-900" role="status">
                {{ flashSuccess }}
            </div>
            <div v-if="firstError" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-900" role="alert">
                {{ firstError }}
            </div>

            <slot />
        </main>

        <OperationsToastHost />

        <AdminMessengerDrawer
            :open="messengerOpen"
            route-namespace="operations"
            event-prefix="operations"
            @close="messengerOpen = false"
            @unread-changed="(n) => { unreadMessenger = n; window.dispatchEvent(new CustomEvent('operations:messenger-changed')); }"
        />
    </div>
</template>

<script setup>
import AdminMessengerDrawer from '@/Components/Admin/AdminMessengerDrawer.vue';
import HustleSafeLogo from '@/Components/Brand/HustleSafeLogo.vue';
import OperationsToastHost from '@/Pages/Operations/Components/OperationsToastHost.vue';
import { useInertiaNav } from '@/composables/useInertiaNav';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const { navPending, visit: visitNav } = useInertiaNav();

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});

const page = usePage();
const logoutForm = useForm({});

const authUser = computed(() => page.props.auth?.user ?? null);
const staffUsername = computed(() => {
    const user = authUser.value;
    if (user?.username?.trim()) {
        return user.username.trim();
    }

    const emailLocal = user?.email?.split('@')[0]?.trim();

    return emailLocal || 'staff';
});
const staffInitial = computed(() => staffUsername.value.charAt(0).toUpperCase() || 'S');

function logout() {
    menuOpen.value = false;
    logoutForm.post(route('logout'));
}

const menuOpen = ref(false);
const desktopOpen = ref('');
const unreadAlerts = ref(0);
const unreadMessenger = ref(0);
const unreadSupportLive = ref(0);
const messengerOpen = ref(false);

async function refreshMessengerUnread() {
    try {
        const { data } = await window.axios.get(route('operations.api.messenger.unread-count'));
        unreadMessenger.value = data.count ?? 0;
    } catch {
        unreadMessenger.value = 0;
    }
}

function openMessengerFromQuery() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('open_messenger') !== '1') {
        return;
    }
    messengerOpen.value = true;
    const conversationId = params.get('conversation');
    if (conversationId) {
        window.dispatchEvent(new CustomEvent('operations:open-messenger', { detail: { conversationId } }));
    }
    params.delete('open_messenger');
    params.delete('conversation');
    const qs = params.toString();
    window.history.replaceState({}, '', `${window.location.pathname}${qs ? `?${qs}` : ''}${window.location.hash}`);
}

async function refreshUnreadAlerts() {
    try {
        const { data } = await window.axios.get(route('operations.api.notifications.unread-count'));
        unreadAlerts.value = data.count ?? 0;
    } catch {
        unreadAlerts.value = 0;
    }
}

async function refreshSupportUnread() {
    try {
        const { data } = await window.axios.get(route('operations.api.customer-support.unread-count'));
        unreadSupportLive.value = data.count ?? 0;
    } catch {
        unreadSupportLive.value = 0;
    }
}

let supportPollTimer = null;

onMounted(async () => {
    await refreshUnreadAlerts();
    await refreshMessengerUnread();
    await refreshSupportUnread();
    openMessengerFromQuery();
    window.addEventListener('operations:notifications-changed', refreshUnreadAlerts);
    window.addEventListener('operations:messenger-changed', refreshMessengerUnread);
    window.addEventListener('operations:support-changed', refreshSupportUnread);
    supportPollTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            void refreshSupportUnread();
        }
    }, 20000);
});

onBeforeUnmount(() => {
    window.removeEventListener('operations:notifications-changed', refreshUnreadAlerts);
    window.removeEventListener('operations:messenger-changed', refreshMessengerUnread);
    window.removeEventListener('operations:support-changed', refreshSupportUnread);
    if (supportPollTimer) {
        window.clearInterval(supportPollTimer);
    }
});

import { matchPathPrefix } from '@/utils/navPathMatch';

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
            { label: 'Alert centre', hint: 'Personal inbox, critical banners, preferences', href: route('operations.notifications.index'), match: (p) => p.startsWith('/operations/notifications') },
            { label: 'My Tasks', hint: 'Assigned flags, referrals, disputes, and escalations', href: route('operations.tasks.index'), match: (p) => p.startsWith('/operations/tasks') },
            { label: 'Account', hint: 'Profile, security, privacy, leave and payroll', href: route('operations.account.index'), match: (p) => p.startsWith('/operations/account') || p.startsWith('/operations/hr') },
            { label: 'Knowledge base', hint: 'Policies, precedents, and how-to guides', href: route('operations.knowledge-base.index'), match: (p) => p.startsWith('/operations/knowledge-base') },
        ],
    },
    {
        label: 'Chat',
        items: [
            { label: 'Live support', hint: 'Real-time customer chats assigned to you', href: route('operations.customer-support.index'), match: (p) => p.startsWith('/operations/customer-support'), badge: () => unreadSupportLive.value },
            { label: 'Support tickets', hint: 'Create and manage customer support tickets', href: route('operations.support-tickets.index'), match: (p) => p.startsWith('/operations/support-tickets') },
            { label: 'Team chat', hint: 'Group channel for the operations team', href: route('operations.team-chat.index'), match: (p) => p.startsWith('/operations/team-chat') },
            { label: 'Direct messages', hint: 'Private 1:1 chats with admins', action: 'messenger', match: () => false },
        ],
    },
    {
        label: 'Moderation',
        items: [
            { label: 'Onboarding quality control', hint: '48-hour signup profile quality reviews', href: route('operations.onboarding-quality.index'), match: (p) => matchPathPrefix(p, '/operations/onboarding-quality', { exclude: ['/operations/onboarding-quality/flagged-profiles'] }) },
            { label: 'Flagged profiles', hint: 'Accounts flagged for monitoring during onboarding', href: route('operations.onboarding-quality.flagged'), match: (p) => p.startsWith('/operations/onboarding-quality/flagged-profiles') },
            { label: 'Moderation centre', hint: 'Tabbed quest & proposal queues with slide-in actions', href: route('operations.moderation.index'), match: (p) => p.startsWith('/operations/moderation') },
            { label: 'Portfolio review', hint: 'Review freelancer portfolios and media for fraud or abuse', href: route('operations.portfolio-review.index'), match: (p) => p.startsWith('/operations/portfolio-review') },
            { label: 'Reviews', hint: 'Authenticity engine, amendments, manipulation dashboard', href: route('operations.reviews.index'), match: (p) => p.startsWith('/operations/reviews') },
            { label: 'Review integrity', hint: 'Coordinated review manipulation patterns', href: route('operations.review-integrity.index'), match: (p) => p.startsWith('/operations/review-integrity') },
            { label: 'Content patrol', hint: 'Proactive sampled Quest & proposal review', href: route('operations.patrol.index'), match: (p) => p.startsWith('/operations/patrol') && !p.startsWith('/operations/user-activity-patrol') },
            { label: 'User activity patrol', hint: 'Auto-detected user anomalies — disputes, velocity, identity', href: route('operations.user-activity-patrol.index'), match: (p) => p.startsWith('/operations/user-activity-patrol') },
            { label: 'Badge requests', hint: 'Manual Top Rated & talent badge reviews', href: route('operations.badge-requests.index'), match: (p) => p.startsWith('/operations/badge-requests') },
            { label: 'Conversation monitoring', hint: 'Flagged quest & Q&A messages — assign, warn, suspend', href: route('operations.conversation-monitoring.index'), match: (p) => p.startsWith('/operations/conversation-monitoring') },
        ],
    },
    {
        label: 'People',
        items: [
            { label: 'Users', hint: 'User context, warnings, and 72-hour suspensions', href: route('operations.users.index'), match: (p) => p.startsWith('/operations/users') },
            { label: 'Verifications', hint: 'KYC, BVN, NIN, and utility review queue', href: route('operations.verifications.index'), match: (p) => p.startsWith('/operations/verifications') },
            { label: 'Trust & risk', hint: 'Risk queue, watchlist, fraud network graph', href: route('operations.trust.index'), match: (p) => p.startsWith('/operations/trust') },
            { label: 'Freelancer quality', hint: 'Performance trends and coaching actions', href: route('operations.quality.index'), match: (p) => p.startsWith('/operations/quality') },
            { label: 'Onboarding assist', hint: 'Stuck users and retention outreach', href: route('operations.onboarding.index'), match: (p) => matchPathPrefix(p, '/operations/onboarding', { exclude: ['/operations/onboarding-quality'] }) },
            { label: 'Proactive outreach', hint: 'Retention queue with templated outreach', href: route('operations.outreach.index'), match: (p) => p.startsWith('/operations/outreach') || p.startsWith('/operations/response-templates') },
        ],
    },
    {
        label: 'Cases',
        items: [
            { label: 'Disputes', hint: 'Mediation queue and evidence review', href: route('operations.disputes.index'), match: (p) => p.startsWith('/operations/disputes') },
            { label: 'Escrow anomalies', hint: 'Stalled contracts before formal disputes', href: route('operations.escrow-anomalies.index'), match: (p) => p.startsWith('/operations/escrow-anomalies') },
            { label: 'Sanction appeals', hint: 'Warnings, restrictions, and suspensions', href: route('operations.sanction-appeals.index'), match: (p) => p.startsWith('/operations/sanction-appeals') },
            { label: 'Payment monitoring', hint: 'Anomaly detection queue for escrow and payouts', href: route('operations.payment-monitoring.index'), match: (p) => p.startsWith('/operations/payment-monitoring') },
            { label: 'Payments', hint: 'Limited escrow and payout support view', href: route('operations.payments.index'), match: (p) => p.startsWith('/operations/payments') && !p.includes('payment-monitoring') },
            { label: 'Payout exceptions', hint: 'Failed payouts and Super Admin escalations', href: route('operations.payout-exceptions.index'), match: (p) => p.startsWith('/operations/payout-exceptions') },
        ],
    },
    {
        label: 'Insights',
        items: [
            { label: 'Support hub', hint: 'Global search, ticket queues, CS chats, disputes', href: route('operations.support.index'), match: (p) => p === '/operations/support' || p.startsWith('/operations/support/') },
            { label: 'Communications log', hint: 'Banners, mass emails, and scheduled sends', href: route('operations.communications-log.index'), match: (p) => p.startsWith('/operations/communications-log') },
            { label: 'Category health', hint: 'Volume, fill rates, and dispute trends', href: route('operations.category-health.index'), match: (p) => p.startsWith('/operations/category-health') },
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
