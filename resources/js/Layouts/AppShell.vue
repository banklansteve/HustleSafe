<template>
    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 text-slate-900">
        <div
            v-if="visibleAnnouncement"
            class="sticky top-0 z-50 border-b px-4 py-2 text-sm font-bold shadow-sm"
            :class="announcementClass"
            role="status"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
                <div class="min-w-0">
                    <span>{{ visibleAnnouncement.message }}</span>
                    <a
                        v-if="visibleAnnouncement.link_url"
                        :href="visibleAnnouncement.link_url"
                        class="ml-2 underline decoration-current underline-offset-4"
                    >
                        {{ visibleAnnouncement.link_text || 'Learn more' }}
                    </a>
                </div>
                <button
                    v-if="visibleAnnouncement.dismissible"
                    type="button"
                    class="rounded-full p-1 transition hover:bg-white/30"
                    aria-label="Dismiss announcement"
                    @click="dismissAnnouncement"
                >
                    <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                </button>
            </div>
        </div>

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
                    <Link
                        v-if="adminEntryUrl"
                        :href="adminEntryUrl"
                        prefetch="false"
                        preserve-scroll
                        class="inline-flex items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-bold shadow-sm transition"
                        :class="pillClass(adminActive)"
                    >
                        <ChartPieIcon class="h-5 w-5 opacity-80" aria-hidden="true" />
                        Admin
                    </Link>
                    <Link
                        v-if="operationsEntryUrl"
                        :href="operationsEntryUrl"
                        prefetch="false"
                        preserve-scroll
                        class="inline-flex items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-bold shadow-sm transition"
                        :class="pillClass(operationsConsoleActive)"
                    >
                        <ChartBarIcon class="h-5 w-5 opacity-80" aria-hidden="true" />
                        Operations
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
                <div ref="notifRoot" class="relative">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                        :aria-expanded="notifOpen"
                        aria-haspopup="true"
                        aria-label="Notifications"
                        @click.stop="notifOpen = !notifOpen"
                    >
                        <BellAlertIcon class="h-6 w-6" aria-hidden="true" />
                    </button>
                    <span
                        v-if="unreadNotificationsCount > 0"
                        class="pointer-events-none absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-secondary-500 px-1 text-[10px] font-black text-white ring-2 ring-white"
                    >
                        {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
                    </span>
                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 translate-y-1"
                    >
                        <div
                            v-if="notifOpen"
                            class="absolute right-0 top-[calc(100%+10px)] z-50 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200/90 bg-white py-2 shadow-2xl shadow-slate-900/15 ring-1 ring-slate-100"
                            role="menu"
                            @click.stop
                        >
                            <div class="border-b border-slate-100 px-4 py-2.5">
                                <p class="text-xs font-black uppercase tracking-wide text-slate-500">
                                    Notifications
                                </p>
                            </div>
                            <ul class="max-h-80 overflow-y-auto py-1">
                                <li v-for="n in recentNotifications" :key="n.id">
                                    <button
                                        type="button"
                                        class="flex w-full flex-col gap-0.5 px-4 py-3 text-left text-sm transition hover:bg-primary-50/80 disabled:cursor-wait disabled:opacity-70"
                                        :class="n.read ? 'text-slate-600' : 'bg-secondary-50/40 font-semibold text-slate-900'"
                                        :disabled="notifBusyId === n.id"
                                        @click="openNotification(n)"
                                    >
                                        <span class="inline-flex items-center gap-2">
                                            <ReLoader4Line
                                                v-if="notifBusyId === n.id"
                                                class="h-4 w-4 shrink-0 animate-spin text-primary-600"
                                                aria-hidden="true"
                                            />
                                            <span class="text-[11px] font-black uppercase tracking-wide text-primary-800">{{ n.label }}</span>
                                            <span
                                                v-if="(n.stacked_unread || 0) > 1"
                                                class="rounded-full bg-secondary-500 px-2 py-0.5 text-[10px] font-black text-white"
                                            >{{ n.stacked_unread }}</span>
                                        </span>
                                        <span class="font-semibold leading-snug text-slate-900">{{ n.line || 'View details' }}</span>
                                        <span v-if="n.preview" class="line-clamp-2 text-xs font-medium text-slate-500">{{ n.preview }}</span>
                                        <span class="text-[10px] font-semibold text-slate-400">{{ formatNotifWhen(n.created_at) }}</span>
                                    </button>
                                </li>
                                <li v-if="recentNotifications.length === 0" class="px-4 py-6 text-center text-sm font-semibold text-slate-500">
                                    You are all caught up.
                                </li>
                            </ul>
                            <div class="border-t border-slate-100 px-2 py-2">
                                <Link
                                    :href="`${route('dashboard')}#notifications`"
                                    class="block rounded-xl px-3 py-2 text-center text-xs font-black text-primary-800 hover:bg-primary-50"
                                    @click="notifOpen = false"
                                >
                                    View all on dashboard
                                </Link>
                            </div>
                        </div>
                    </Transition>
                </div>

                    <NavUserMenu />
                </div>
            </div>
        </header>

        <AppToastHost />

        <Teleport to="body">
            <div
                v-if="systemBusy"
                class="pointer-events-none fixed inset-x-0 top-16 z-[60] flex justify-center px-4"
                role="status"
                aria-live="polite"
            >
                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200/90 bg-white/95 px-4 py-2 text-[11px] font-black uppercase tracking-wide text-slate-700 shadow-lg shadow-slate-900/10 ring-1 ring-slate-100">
                    <ReLoader4Line class="h-4 w-4 shrink-0 animate-spin text-primary-600" aria-hidden="true" />
                    {{ systemBusyLabel }}
                </span>
            </div>
        </Teleport>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8 lg:py-12">
            <div
                v-if="impersonation"
                class="mb-6 flex flex-col gap-3 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-4 text-sm font-bold text-rose-950 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                role="alert"
            >
                <span>
                    Impersonation active: {{ impersonation.admin_name }} is viewing the platform as
                    <span class="font-black">{{ impersonation.user_name }}</span>.
                </span>
                <button type="button" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-black uppercase text-white" @click="stopImpersonation">
                    End impersonation
                </button>
            </div>
            <div
                v-if="page.props.flash?.proposal_next_steps && isFreelancer"
                class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50/95 px-4 py-4 text-sm font-semibold text-emerald-950 shadow-sm ring-1 ring-emerald-100 sm:px-5"
                role="status"
            >
                <p class="text-xs font-black uppercase tracking-wide text-emerald-900">What happens next</p>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-[13px] leading-relaxed text-emerald-950/95">
                    <li>The client gets an email and an in-app alert with your proposal.</li>
                    <li>They may message you on the quest thread — keep replies on-platform.</li>
                    <li>You can refine this quote until the edit window closes; after that, changes need a new conversation with the client.</li>
                    <li>If they accept, they fund escrow before work should begin.</li>
                </ol>
            </div>
            <div
                v-if="page.props.flash?.quest_submitted_next_steps && showClientTools"
                class="mb-6 rounded-2xl border border-sky-200 bg-sky-50/95 px-4 py-4 text-sm font-semibold text-sky-950 shadow-sm ring-1 ring-sky-100 sm:px-5"
                role="status"
            >
                <p class="text-xs font-black uppercase tracking-wide text-sky-900">What happens next</p>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-[13px] leading-relaxed text-sky-950/95">
                    <li>Matching freelancers were notified by email and in-app.</li>
                    <li>Watch your inbox and notifications for questions and proposals.</li>
                    <li>Review proposals from <span class="font-black">My quests</span>, shortlist favourites, then accept one to move into escrow.</li>
                    <li>You can still edit the brief until the client edit window shown on the quest page ends.</li>
                </ol>
            </div>
            <div
                v-if="clientNudgeItems.length"
                class="mb-6 rounded-2xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm font-semibold text-amber-950 shadow-sm ring-1 ring-amber-100 sm:px-5 sm:py-4"
                role="status"
            >
                <p class="text-xs font-black uppercase tracking-wide text-amber-900">Your attention</p>
                <ul class="mt-3 space-y-3">
                    <li v-for="(item, i) in clientNudgeItems" :key="i" class="list-none rounded-xl border border-amber-100/80 bg-white/70 px-3 py-2.5 ring-1 ring-white/60">
                        <p class="text-[13px] font-semibold leading-snug text-amber-950">
                            {{ item.message }}
                        </p>
                        <Link
                            v-if="item.action_url"
                            :href="item.action_url"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-black uppercase tracking-wide text-amber-900 underline decoration-amber-400 underline-offset-2 hover:text-amber-800"
                        >
                            {{ item.action_label || 'Open' }}
                            <span aria-hidden="true">→</span>
                        </Link>
                    </li>
                </ul>
            </div>
            <div
                v-if="freelancerNudgeItems.length && !hideWorkspaceNudgeOnAccount && !hideWorkspaceNudgeOnQuestWorkspacePages"
                class="mb-6 rounded-2xl border border-secondary-200/80 bg-secondary-50/90 px-4 py-3 text-sm font-semibold text-secondary-950 shadow-sm ring-1 ring-secondary-100 sm:px-5 sm:py-4"
                role="status"
            >
                <p class="text-xs font-black uppercase tracking-wide text-secondary-800">Action needed</p>
                <ul class="mt-3 space-y-3">
                    <li v-for="(item, i) in freelancerNudgeItems" :key="i" class="list-none rounded-xl border border-secondary-100/80 bg-white/60 px-3 py-2.5 ring-1 ring-white/60">
                        <p class="text-[13px] font-semibold leading-snug text-secondary-950">
                            {{ item.message }}
                        </p>
                        <Link
                            v-if="item.action_url"
                            :href="item.action_url"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-black uppercase tracking-wide text-secondary-900 underline decoration-secondary-400 underline-offset-2 hover:text-secondary-700"
                        >
                            {{ item.action_label || 'Fix this' }}
                            <span aria-hidden="true">→</span>
                        </Link>
                    </li>
                </ul>
            </div>
            <slot />
        </main>
    </div>
</template>

<script setup>
import NavUserMenu from '@/Components/Layout/NavUserMenu.vue';
import AppToastHost from '@/Components/Ui/AppToastHost.vue';
import { useNotificationVisit } from '@/composables/useNotificationVisit';
import { pathMatches, usePathname } from '@/composables/usePathname';
import { Link, router, usePage } from '@inertiajs/vue3';
import { BellAlertIcon, BriefcaseIcon, ChartBarIcon, ChartPieIcon, ClipboardDocumentListIcon, HomeIcon, MagnifyingGlassIcon, PlusCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const pathname = usePathname(page);

const { busyId: notifBusyId, visit: visitNotification } = useNotificationVisit();

const notifRoot = ref(null);
const notifOpen = ref(false);
const inertiaNavPending = ref(false);

const systemBusy = computed(() => inertiaNavPending.value || notifBusyId.value !== null);
const systemBusyLabel = computed(() => (notifBusyId.value ? 'Opening notification…' : 'Loading…'));

let removeInertiaListeners = [];
let notifPollTimer = null;

async function openNotification(n) {
    notifOpen.value = false;
    const merge = Array.isArray(n.related_ids)
        ? n.related_ids.filter((x) => x && x !== n.id).join(',')
        : '';
    await visitNotification(n.id, merge || null);
}

const recentNotifications = computed(() => page.props.recentNotifications ?? []);

const unreadNotificationsCount = computed(() => Number(page.props.unreadNotificationsCount ?? 0) || 0);

function formatNotifWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return '';
    }
}

function onDocClick(e) {
    if (!notifOpen.value || !notifRoot.value) {
        return;
    }
    if (!notifRoot.value.contains(e.target)) {
        notifOpen.value = false;
    }
}

onMounted(() => {
    try {
        dismissedAnnouncementIds.value = JSON.parse(window.localStorage?.getItem('dismissed-announcements') || '[]');
    } catch {
        dismissedAnnouncementIds.value = [];
    }
    document.addEventListener('click', onDocClick);
    removeInertiaListeners = [
        router.on('start', () => {
            inertiaNavPending.value = true;
        }),
        router.on('finish', () => {
            inertiaNavPending.value = false;
        }),
    ];

    if (page.props.auth?.user) {
        const tick = () => {
            if (document.visibilityState !== 'visible' || notifOpen.value) {
                return;
            }
            router.reload({ preserveScroll: true, preserveState: true });
        };
        window.addEventListener('focus', tick);
        notifPollTimer = window.setInterval(tick, 35000);
        removeInertiaListeners.push(() => window.removeEventListener('focus', tick));
    }
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    if (notifPollTimer) {
        window.clearInterval(notifPollTimer);
    }
    removeInertiaListeners.forEach((fn) => {
        if (typeof fn === 'function') {
            fn();
        }
    });
});

/** Avoid duplicating category/setup prompts already shown on Account Hub. */
const hideWorkspaceNudgeOnAccount = computed(() => pathname.value.startsWith('/account'));

/** Quest detail + Explore already embed the same checklist; hide shell banner to avoid double alerts. */
const hideWorkspaceNudgeOnQuestWorkspacePages = computed(() => {
    if (page.props.auth?.user?.role?.slug !== 'freelancer') {
        return false;
    }
    const p = pathname.value;
    if (p === '/quests/explore') {
        return true;
    }
    const m = p.match(/^\/quests\/([^/]+)$/);
    if (!m) {
        return false;
    }
    const reserved = new Set(['create', 'explore', 'field-profile']);

    return !reserved.has(m[1]);
});

const freelancerNudgeItems = computed(() => {
    const ws = page.props.freelancerWorkspace;
    if (!ws?.enabled) {
        return [];
    }
    const items = [];
    for (const b of ws.blockers || []) {
        if (b?.message) {
            items.push({
                message: b.message,
                action_label: b.action_label,
                action_url: b.action_url,
            });
        }
    }
    for (const h of ws.hints || []) {
        if (h?.message) {
            items.push({
                message: h.message,
                action_label: h.action_label,
                action_url: h.action_url,
            });
        }
    }

    return items.slice(0, 5);
});

const clientNudgeItems = computed(() => {
    const raw = page.props.client_outstanding;
    if (!Array.isArray(raw)) {
        return [];
    }

    return raw.filter((x) => x && typeof x.message === 'string');
});

const roleSlug = computed(() => page.props.auth?.user?.role?.slug ?? '');
const isFreelancer = computed(() => roleSlug.value === 'freelancer');
const showClientTools = computed(() => ['client', 'super_admin'].includes(roleSlug.value));
const adminEntryUrl = computed(() => page.props.admin_entry_url ?? null);
const operationsEntryUrl = computed(() => page.props.operations_entry_url ?? null);
const impersonation = computed(() => page.props.impersonation ?? null);
const dismissedAnnouncementIds = ref([]);
const visibleAnnouncement = computed(() => {
    const banner = page.props.announcement_banner;
    if (!banner) {
        return null;
    }

    return dismissedAnnouncementIds.value.includes(banner.id) ? null : banner;
});
const announcementClass = computed(() => ({
    info: 'border-sky-200 bg-sky-600 text-white',
    success: 'border-emerald-200 bg-emerald-600 text-white',
    warning: 'border-amber-200 bg-amber-400 text-amber-950',
    alert: 'border-rose-200 bg-rose-600 text-white',
    brand: 'border-primary-200 bg-primary-700 text-white',
}[visibleAnnouncement.value?.color || 'brand']));

const homeActive = computed(() => pathname.value === '/');

const dashboardActive = computed(() => pathMatches(pathname, route('dashboard')));

const adminActive = computed(() => pathname.value.startsWith('/admin'));

const operationsConsoleActive = computed(() => pathname.value.startsWith('/operations'));

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

async function stopImpersonation() {
    const { data } = await window.axios.post(route('impersonation.stop'));
    window.location.href = data.redirect || route('admin.users.index');
}

function dismissAnnouncement() {
    if (!visibleAnnouncement.value) {
        return;
    }
    dismissedAnnouncementIds.value = [...dismissedAnnouncementIds.value, visibleAnnouncement.value.id];
    window.localStorage?.setItem('dismissed-announcements', JSON.stringify(dismissedAnnouncementIds.value));
}
</script>
