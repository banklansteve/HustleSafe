<template>
    <AdminPanel eyebrow="Live operations" title="Platform health">
        <template #actions>
            <span class="text-[10px] font-bold uppercase tracking-wide" :class="shell.cardMuted">
                Updated {{ refreshedLabel }}
            </span>
        </template>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                :href="route('admin.support-tickets.index')"
                prefetch="false"
                class="rounded-2xl border p-4 transition hover:border-primary-200"
                :class="[shell.card, metricTone(metrics.unresolved_tickets, 20, 50)]"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.cardMuted">Unresolved tickets</p>
                <p class="mt-2 text-3xl font-black tabular-nums" :class="shell.cardTitle">{{ metrics.unresolved_tickets ?? '—' }}</p>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Open, in progress, or awaiting customer</p>
            </Link>

            <Link
                :href="route('admin.conversation-monitoring.index')"
                prefetch="false"
                class="rounded-2xl border p-4 transition hover:border-primary-200"
                :class="[shell.card, metricTone(metrics.flagged_conversations, 5, 15)]"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.cardMuted">Flagged conversations</p>
                <p class="mt-2 text-3xl font-black tabular-nums" :class="shell.cardTitle">{{ metrics.flagged_conversations ?? '—' }}</p>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Pending moderation review</p>
            </Link>

            <Link
                :href="route('admin.kyc.index')"
                prefetch="false"
                class="rounded-2xl border p-4 transition hover:border-primary-200 sm:col-span-2 xl:col-span-1"
                :class="[shell.card, metricTone(metrics.kyc_queue_depth, 10, 30)]"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.cardMuted">KYC queue depth</p>
                <p class="mt-2 text-3xl font-black tabular-nums" :class="shell.cardTitle">{{ metrics.kyc_queue_depth ?? '—' }}</p>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Pending, in review, or flagged</p>
            </Link>
        </div>

        <div class="mt-4 rounded-2xl border p-4" :class="shell.card">
            <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.cardMuted">Staff availability by role group</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <div
                    v-for="group in staffAvailability"
                    :key="group.role_group"
                    class="rounded-xl border px-3 py-3"
                    :class="group.coverage_ok ? 'border-emerald-200/80 bg-emerald-50/40' : 'border-amber-200/80 bg-amber-50/50'"
                >
                    <p class="text-xs font-black" :class="shell.cardTitle">{{ group.label }}</p>
                    <p class="mt-1 text-sm font-bold tabular-nums" :class="shell.cardMuted">
                        {{ group.available }} available
                        <span class="font-semibold opacity-70">· {{ group.assigned }} assigned · {{ group.on_leave }} on leave</span>
                    </p>
                </div>
            </div>
        </div>
    </AdminPanel>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    initial: { type: Object, default: null },
});

const { shell } = useInjectedAdminTheme();

const snapshot = ref(props.initial ?? null);
const pollMs = 30000;
let timer = null;

const metrics = computed(() => snapshot.value?.metrics ?? {});
const staffAvailability = computed(() => snapshot.value?.staff_availability ?? []);

const refreshedLabel = computed(() => {
    const raw = snapshot.value?.generated_at;
    if (!raw) {
        return '—';
    }

    try {
        return new Date(raw).toLocaleTimeString('en-NG', { timeStyle: 'short', timeZone: 'Africa/Lagos' });
    } catch {
        return raw;
    }
});

function metricTone(value, warnAt, criticalAt) {
    const n = Number(value) || 0;
    if (n >= criticalAt) {
        return 'border-rose-200 bg-rose-50/50';
    }
    if (n >= warnAt) {
        return 'border-amber-200 bg-amber-50/40';
    }

    return 'border-slate-200/80';
}

async function refresh() {
    try {
        const { data } = await window.axios.get(route('admin.api.platform-health'));
        snapshot.value = data;
    } catch {
        // keep last good snapshot
    }
}

onMounted(() => {
    if (!snapshot.value) {
        refresh();
    }
    timer = window.setInterval(refresh, pollMs);
});

onUnmounted(() => {
    if (timer) {
        window.clearInterval(timer);
    }
});
</script>
