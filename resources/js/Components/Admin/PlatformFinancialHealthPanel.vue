<template>
    <AdminPanel eyebrow="Executive finance" title="Platform financial health">
        <template #actions>
            <Link
                :href="route('admin.financial.index')"
                prefetch="false"
                class="text-[10px] font-black uppercase tracking-wide text-primary-700 hover:text-primary-900"
            >
                Financial control centre
            </Link>
        </template>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-2xl border p-4" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.cardMuted">Escrow held</p>
                <p class="mt-2 text-2xl font-black tabular-nums" :class="shell.cardTitle">{{ metrics.escrow_held ?? '—' }}</p>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Live funded contracts</p>
            </div>
            <div class="rounded-2xl border p-4" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.cardMuted">Pending withdrawals</p>
                <p class="mt-2 text-2xl font-black tabular-nums" :class="shell.cardTitle">{{ metrics.pending_withdrawals ?? '—' }}</p>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Freelancer wallets awaiting payout</p>
            </div>
            <div class="rounded-2xl border p-4 sm:col-span-2 xl:col-span-1" :class="[shell.card, (metrics.financial_anomalies_high ?? 0) > 0 ? 'border-amber-300 bg-amber-50/40' : '']">
                <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.cardMuted">Flagged anomalies</p>
                <p class="mt-2 text-2xl font-black tabular-nums" :class="shell.cardTitle">{{ metrics.financial_anomalies ?? 0 }}</p>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">{{ metrics.financial_anomalies_high ?? 0 }} high severity · not the ops payment queue</p>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl border p-4" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.cardMuted">Platform fee revenue</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="font-semibold text-slate-600">Today</dt><dd class="font-black text-slate-900">{{ metrics.platform_fees_today }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="font-semibold text-slate-600">This month</dt><dd class="font-black text-slate-900">{{ metrics.platform_fees_month }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="font-semibold text-slate-600">This year</dt><dd class="font-black text-slate-900">{{ metrics.platform_fees_year }}</dd></div>
                </dl>
            </div>
            <div class="rounded-2xl border p-4" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.cardMuted">Payout volume</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="font-semibold text-slate-600">Today</dt><dd class="font-black text-slate-900">{{ metrics.payout_volume_today }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="font-semibold text-slate-600">This month</dt><dd class="font-black text-slate-900">{{ metrics.payout_volume_month }}</dd></div>
                </dl>
            </div>
        </div>

        <ul v-if="anomalyPreview.length" class="mt-4 space-y-2 rounded-2xl border p-4" :class="shell.card">
            <li v-for="(item, idx) in anomalyPreview" :key="idx" class="flex items-start justify-between gap-2 text-xs">
                <span class="font-semibold text-slate-800">{{ item.label }}<span v-if="item.quest_title" class="text-slate-500"> · {{ item.quest_title }}</span></span>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="severityClass(item.severity)">{{ item.severity }}</span>
            </li>
        </ul>

        <p class="mt-3 text-[10px] font-bold uppercase tracking-wide" :class="shell.cardMuted">Updated {{ refreshedLabel }} · refreshes every 90s</p>
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
const pollMs = 90000;
let timer = null;

const metrics = computed(() => snapshot.value?.metrics ?? {});
const anomalyPreview = computed(() => snapshot.value?.anomaly_preview ?? []);

const refreshedLabel = computed(() => {
    const at = snapshot.value?.generated_at;
    if (!at) return '—';
    try {
        return new Date(at).toLocaleTimeString('en-NG', { timeStyle: 'short', timeZone: 'Africa/Lagos' });
    } catch {
        return at;
    }
});

function severityClass(severity) {
    if (severity === 'high') return 'bg-rose-100 text-rose-900';
    if (severity === 'medium') return 'bg-amber-100 text-amber-900';
    return 'bg-slate-100 text-slate-700';
}

async function refresh() {
    try {
        const { data } = await window.axios.get(route('admin.api.platform-financial-health'));
        snapshot.value = data;
    } catch {
        /* polling only */
    }
}

onMounted(() => {
    if (!props.initial) refresh();
    timer = window.setInterval(refresh, pollMs);
});

onUnmounted(() => {
    if (timer) window.clearInterval(timer);
});
</script>
