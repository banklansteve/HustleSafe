<template>
    <div
        id="notifications"
        class="scroll-mt-28 rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6"
        :class="panelClass"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex gap-3">
                <span
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-secondary-100 text-secondary-800 ring-1 ring-secondary-200/80"
                >
                    <BellAlertIcon class="h-5 w-5" aria-hidden="true" />
                </span>
                <div>
                    <h3 class="font-display text-base font-bold text-slate-900" :class="titleClass">
                        Notifications
                    </h3>
                    <p class="mt-1 text-sm font-medium text-slate-600">
                        {{ subtitle }}
                    </p>
                    <Link
                        :href="route('account.policy-notices.index')"
                        class="mt-2 inline-flex text-xs font-black uppercase tracking-wide text-amber-800 hover:text-amber-950"
                    >
                        View policy notices
                    </Link>
                </div>
            </div>
            <button
                v-if="localItems.length"
                type="button"
                class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-700 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-800 disabled:opacity-60"
                :disabled="clearBusy"
                @click="clearAll"
            >
                {{ clearBusy ? 'Clearing…' : 'Clear all' }}
            </button>
        </div>
        <ul class="mt-4 max-h-[28rem] space-y-3 overflow-y-auto pr-1">
            <li
                v-for="n in localItems"
                :key="n.id"
                class="rounded-xl border px-0 py-0"
                :class="n.read ? 'border-slate-100 bg-slate-50/60' : 'border-secondary-200 bg-secondary-50/70'"
            >
                <button
                    type="button"
                    class="block w-full rounded-xl px-3 py-3 text-left transition hover:bg-white/80 disabled:cursor-wait disabled:opacity-70"
                    :class="itemButtonClass"
                    :disabled="notifBusyId === n.id"
                    @click="openItem(n)"
                >
                    <span class="inline-flex items-center gap-2">
                        <ReLoader4Line
                            v-if="notifBusyId === n.id"
                            class="h-4 w-4 shrink-0 animate-spin text-primary-600"
                            aria-hidden="true"
                        />
                        <p class="text-[10px] font-bold uppercase tracking-wide text-primary-800" :class="labelClass">
                            {{ n.label }}
                        </p>
                    </span>
                    <p class="mt-1 text-sm font-semibold text-slate-900" :class="lineClass">
                        {{ n.line || summarizeNotification(n.data) }}
                    </p>
                    <p class="mt-1.5 text-xs font-medium text-slate-500" :class="whenClass">
                        {{ formatWhen(n.created_at) }}
                    </p>
                </button>
            </li>
            <li
                v-if="localItems.length === 0"
                class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-8 text-center text-sm font-semibold text-slate-600"
            >
                {{ emptyMessage }}
            </li>
        </ul>
    </div>
</template>

<script setup>
import { useNotificationVisit } from '@/composables/useNotificationVisit';
import { useUserNotificationEcho } from '@/composables/useUserNotificationEcho';
import { BellAlertIcon } from '@heroicons/vue/24/outline';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    notifications: { type: Array, default: () => [] },
    subtitle: { type: String, default: 'Updates that need your attention.' },
    emptyMessage: { type: String, default: 'All caught up — we will ping you when something needs attention.' },
    panelClass: { type: String, default: '' },
    titleClass: { type: String, default: '' },
    itemButtonClass: { type: String, default: '' },
    labelClass: { type: String, default: '' },
    lineClass: { type: String, default: '' },
    whenClass: { type: String, default: '' },
});

const page = usePage();
const { busyId: notifBusyId, visit: visitNotification } = useNotificationVisit();
const localItems = ref([...props.notifications]);
const clearBusy = ref(false);

watch(
    () => props.notifications,
    (items) => {
        localItems.value = [...(items ?? [])];
    },
    { deep: true },
);

async function refreshNav() {
    try {
        const { data } = await axios.get(route('api.notifications.nav'), { timeout: 8000 });
        localItems.value = data.recentNotifications ?? [];
    } catch {
        /* best-effort */
    }
}

useUserNotificationEcho(page.props.auth?.user?.id, page.props.broadcast, refreshNav);

let removeChangedListener = null;
onMounted(() => {
    const handler = () => refreshNav();
    window.addEventListener('app:notifications-changed', handler);
    removeChangedListener = () => window.removeEventListener('app:notifications-changed', handler);
});

onBeforeUnmount(() => {
    removeChangedListener?.();
});

function openItem(n) {
    void visitNotification(n.id);
}

async function clearAll() {
    if (clearBusy.value || !localItems.value.length) {
        return;
    }
    clearBusy.value = true;
    try {
        await axios.delete(route('api.notifications.clear'));
        localItems.value = [];
        window.dispatchEvent(new CustomEvent('app:notifications-changed'));
    } finally {
        clearBusy.value = false;
    }
}

function formatWhen(iso) {
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
        return iso;
    }
}

function summarizeNotification(data) {
    if (!data || typeof data !== 'object') {
        return '';
    }

    return String(data.body || data.message || data.preview || '');
}
</script>
