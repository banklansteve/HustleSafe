<template>
    <OperationsShell title="Payment monitoring" subtitle="Anomaly detection for escrow funding, smurfing, payout velocity, rapid releases, and market-rate outliers.">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-bold text-slate-600">
                Severity
                <select v-model="filters.severity" class="mt-1 block rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold" @change="reload">
                    <option value="">All</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">
                Anomaly type
                <select v-model="filters.anomaly_type" class="mt-1 block rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold" @change="reload">
                    <option value="">All</option>
                    <option v-for="t in anomalyTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">
                Sort
                <select v-model="filters.sort" class="mt-1 block rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold" @change="reload">
                    <option value="severity_desc">Severity</option>
                    <option value="amount_desc">Amount (high)</option>
                    <option value="date_asc">Oldest</option>
                </select>
            </label>
        </div>

        <OperationsQueueTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            v-model:search="search"
            v-model:per-page="perPage"
            :page="page"
            :total="total"
            :total-pages="totalPages"
            empty-message="No payment anomalies detected for current filters."
            @page="(p) => { page = p; reload(); }"
            @open="openFlagModal"
        >
            <template #cell-anomaly_label="{ row }">
                <span class="font-semibold text-slate-950">{{ row.anomaly_label }}</span>
                <span v-if="row.has_pending_flag" class="mt-1 block text-[10px] font-black uppercase text-primary-700">Flagged for review</span>
            </template>
            <template #cell-severity="{ row }">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="severityClass(row.severity)">{{ row.severity }}</span>
            </template>
            <template #cell-amount_minor="{ row }">
                <span class="font-black text-slate-900">{{ formatNgn(row.amount_minor) }}</span>
            </template>
            <template #cell-transaction_reference="{ row }">
                <span class="text-xs font-semibold text-slate-600">{{ row.transaction_reference || '—' }}</span>
            </template>
            <template #cell-market="{ row }">
                <div v-if="row.market_band" class="max-w-[10rem]">
                    <div class="relative h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="absolute inset-y-0 rounded-full bg-primary-200/80" :style="bandStyle(row)" />
                        <div class="absolute top-0 bottom-0 w-0.5 bg-primary-700" :style="{ left: contractMarker(row) }" />
                    </div>
                    <p class="mt-1 text-[10px] font-semibold text-slate-500">
                        Median {{ formatNgn(row.market_band.median) }}
                        <span v-if="row.metadata?.deviation_percent != null"> · {{ row.metadata.deviation_percent }}%</span>
                    </p>
                </div>
                <span v-else class="text-xs text-slate-400">—</span>
            </template>
            <template #actions="{ row }">
                <button
                    type="button"
                    class="rounded-lg px-2 py-1 text-[10px] font-black uppercase"
                    :class="row.has_pending_flag ? 'bg-slate-200 text-slate-500' : 'bg-amber-600 text-white'"
                    :disabled="row.has_pending_flag || flagBusy"
                    @click.stop="openFlagModal(row)"
                >
                    {{ row.has_pending_flag ? 'Flagged' : 'Raise flag' }}
                </button>
            </template>
        </OperationsQueueTable>

        <div
            v-if="flagModalOpen"
            class="fixed inset-0 z-[80] flex items-end justify-center bg-slate-950/50 p-4 backdrop-blur-sm sm:items-center"
            @click.self="closeFlagModal"
        >
            <div class="w-full max-w-lg rounded-[1.75rem] bg-white p-5 shadow-2xl ring-1 ring-slate-200">
                <h3 class="font-display text-lg font-black text-slate-950">Raise payment review flag</h3>
                <p class="mt-1 text-sm font-semibold text-slate-600">
                    {{ flagTarget?.anomaly_label }} · {{ flagTarget?.severity }} severity
                </p>
                <p class="mt-2 text-xs text-slate-500">This does not pause or alter the transaction. Super Admin will review your note.</p>
                <textarea
                    v-model="concernNote"
                    rows="5"
                    maxlength="500"
                    class="mt-4 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-900"
                    placeholder="Describe your concern (8–500 characters)…"
                />
                <p class="mt-1 text-right text-[10px] font-bold text-slate-400">{{ concernNote.length }}/500</p>
                <p v-if="flagError" class="mt-2 text-sm font-bold text-rose-700">{{ flagError }}</p>
                <div class="mt-4 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-black text-slate-700" @click="closeFlagModal">Cancel</button>
                    <button type="button" class="flex-1 rounded-xl bg-primary-700 py-2.5 text-sm font-black text-white disabled:opacity-50" :disabled="flagBusy || concernNote.length < 8" @click="submitFlag">
                        {{ flagBusy ? 'Submitting…' : 'Submit flag' }}
                    </button>
                </div>
            </div>
        </div>
    </OperationsShell>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';

const columns = [
    { key: 'anomaly_label', label: 'Signal' },
    { key: 'severity', label: 'Severity' },
    { key: 'amount_minor', label: 'Amount' },
    { key: 'transaction_reference', label: 'Reference' },
    { key: 'market', label: 'Market band' },
];

const rows = ref([]);
const loading = ref(false);
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const total = ref(0);
const totalPages = ref(1);
const anomalyTypes = ref([]);

const filters = reactive({
    severity: '',
    anomaly_type: '',
    sort: 'severity_desc',
});

const flagModalOpen = ref(false);
const flagTarget = ref(null);
const concernNote = ref('');
const flagBusy = ref(false);
const flagError = ref('');

onMounted(reload);

watch(search, () => {
    page.value = 1;
    reload();
});

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.payment-monitoring.listing'), {
            params: {
                page: page.value,
                per_page: perPage.value,
                q: search.value,
                ...filters,
            },
        });
        rows.value = data.items ?? [];
        total.value = data.meta?.total ?? 0;
        totalPages.value = data.meta?.last_page ?? 1;
        anomalyTypes.value = data.anomaly_types ?? [];
    } finally {
        loading.value = false;
    }
}

function openFlagModal(row) {
    if (row.has_pending_flag) return;
    flagTarget.value = row;
    concernNote.value = '';
    flagError.value = '';
    flagModalOpen.value = true;
}

function closeFlagModal() {
    flagModalOpen.value = false;
    flagTarget.value = null;
}

async function submitFlag() {
    if (!flagTarget.value) return;
    const target = flagTarget.value;
    const snapshot = { ...target };
    flagBusy.value = true;
    flagError.value = '';

    const optimistic = rows.value.map((r) =>
        r.anomaly_fingerprint === target.anomaly_fingerprint ? { ...r, has_pending_flag: true } : r,
    );
    rows.value = optimistic;

    try {
        await window.axios.post(route('operations.api.payment-monitoring.flag'), {
            anomaly_fingerprint: target.anomaly_fingerprint,
            anomaly_type: target.anomaly_type,
            severity: target.severity,
            payment_escrow_id: target.payment_escrow_id,
            quest_id: target.quest_id,
            wallet_transaction_id: target.wallet_transaction_id,
            transaction_reference: target.transaction_reference,
            concern_note: concernNote.value,
            signal_payload: {
                metadata: target.metadata,
                market_band: target.market_band,
                quest_title: target.quest_title,
                quest_reference: target.quest_reference,
            },
        });
        closeFlagModal();
    } catch (e) {
        rows.value = rows.value.map((r) =>
            r.anomaly_fingerprint === snapshot.anomaly_fingerprint ? snapshot : r,
        );
        flagError.value = e?.response?.data?.message || e?.response?.data?.errors?.concern_note?.[0] || 'Could not submit flag.';
    } finally {
        flagBusy.value = false;
    }
}

function formatNgn(minor) {
    return `₦${(Number(minor || 0) / 100).toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function severityClass(severity) {
    return {
        high: 'bg-rose-100 text-rose-900',
        medium: 'bg-amber-100 text-amber-900',
        low: 'bg-slate-100 text-slate-700',
    }[severity] || 'bg-slate-100 text-slate-700';
}

function bandStyle(row) {
    const b = row.market_band;
    if (!b || b.max <= b.min) return { left: '0%', width: '100%' };
    const width = Math.max(8, ((b.p75 - b.p25) / (b.max - b.min)) * 100);
    const left = ((b.p25 - b.min) / (b.max - b.min)) * 100;
    return { left: `${left}%`, width: `${width}%` };
}

function contractMarker(row) {
    const b = row.market_band;
    const contract = row.metadata?.contract_amount_minor ?? row.amount_minor;
    if (!b || b.max <= b.min) return '50%';
    const pct = Math.max(0, Math.min(100, ((contract - b.min) / (b.max - b.min)) * 100));
    return `${pct}%`;
}
</script>
