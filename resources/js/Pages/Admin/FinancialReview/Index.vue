<template>
    <AdminShell
        title="Financial review queue"
        subtitle="Staff-raised payment review flags from the operations payment monitoring queue."
    >
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-bold" :class="shell.cardMuted">
                Status
                <select v-model="filters.status" class="mt-1 block rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="reload">
                    <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </label>
            <label class="text-xs font-bold" :class="shell.cardMuted">
                Severity
                <select v-model="filters.severity" class="mt-1 block rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="reload">
                    <option value="">All</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </label>
            <label class="text-xs font-bold" :class="shell.cardMuted">
                Anomaly
                <select v-model="filters.anomaly_type" class="mt-1 block rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" @change="reload">
                    <option value="">All</option>
                    <option v-for="t in anomalyTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </label>
            <label class="text-xs font-bold" :class="shell.cardMuted">
                From
                <AdminDateInput v-model="filters.from" wrapper-class="mt-1" @change="reload" />
            </label>
            <label class="text-xs font-bold" :class="shell.cardMuted">
                To
                <AdminDateInput v-model="filters.to" wrapper-class="mt-1" @change="reload" />
            </label>
            <p v-if="pendingCount > 0" class="ml-auto rounded-full bg-amber-500 px-3 py-1 text-xs font-black text-white">
                {{ pendingCount }} pending
            </p>
        </div>

        <div class="overflow-x-auto rounded-2xl border" :class="shell.card">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b text-[10px] font-black uppercase tracking-wider" :class="shell.tableDivide">
                    <tr>
                        <th class="px-4 py-3">Signal</th>
                        <th class="px-4 py-3">Severity</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Staff</th>
                        <th class="px-4 py-3">Raised</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="border-b transition"
                        :class="[shell.tableDivide, row.is_unreviewed ? 'bg-amber-50/50 dark:bg-amber-950/20' : '']"
                    >
                        <td class="px-4 py-3">
                            <p class="font-bold" :class="shell.cardTitle">{{ row.anomaly_label }}</p>
                            <p class="text-xs" :class="shell.cardMuted">{{ row.transaction_reference || '—' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="severityClass(row.severity)">{{ row.severity }}</span>
                        </td>
                        <td class="px-4 py-3 font-black" :class="shell.cardTitle">{{ formatNgn(row.amount_minor) }}</td>
                        <td class="px-4 py-3">
                            <p class="font-semibold" :class="shell.cardTitle">{{ row.staff_admin?.name }}</p>
                            <p class="text-xs" :class="shell.cardMuted">{{ row.staff_admin?.email }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs font-semibold" :class="shell.cardMuted">{{ formatWhen(row.created_at) }}</td>
                        <td class="px-4 py-3 text-xs font-black uppercase" :class="shell.cardTitle">{{ row.resolution_status }}</td>
                        <td class="px-4 py-3">
                            <button
                                v-if="row.resolution_status === 'pending'"
                                type="button"
                                class="rounded-lg bg-primary-700 px-3 py-1.5 text-[10px] font-black uppercase text-white"
                                @click="openResolve(row)"
                            >
                                Review
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!loading && !rows.length">
                        <td colspan="7" class="px-4 py-8 text-center font-semibold" :class="shell.cardMuted">No flags in this queue.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="totalPages > 1" class="mt-4 flex justify-center gap-2">
            <button
                v-for="p in totalPages"
                :key="p"
                type="button"
                class="rounded-lg px-3 py-1 text-xs font-black"
                :class="p === page ? shell.btnPrimary : shell.btnGhost"
                @click="page = p; reload()"
            >
                {{ p }}
            </button>
        </div>

        <div v-if="resolveOpen && selected" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" @click.self="resolveOpen = false">
            <div class="w-full max-w-xl rounded-2xl border p-6 shadow-2xl" :class="shell.card">
                <h3 class="text-lg font-black" :class="shell.cardTitle">Review flag #{{ selected.id }}</h3>
                <p class="mt-2 text-sm font-semibold" :class="shell.cardMuted">{{ selected.anomaly_label }} · {{ formatNgn(selected.amount_minor) }}</p>
                <p class="mt-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-900/50" :class="shell.cardMuted">
                    <span class="font-black text-slate-800 dark:text-slate-200">Staff concern:</span>
                    {{ selected.concern_note }}
                </p>
                <textarea
                    v-model="resolutionNote"
                    rows="3"
                    class="mt-4 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
                    :class="shell.input"
                    placeholder="Dismissal reason (required when dismissing)…"
                />
                <div class="mt-4 grid gap-2 sm:grid-cols-3">
                    <button type="button" class="rounded-xl bg-emerald-600 py-2.5 text-sm font-black text-white" :disabled="resolveBusy" @click="resolve('reviewed')">Mark reviewed</button>
                    <button type="button" class="rounded-xl bg-rose-600 py-2.5 text-sm font-black text-white" :disabled="resolveBusy" @click="resolve('escalate')">Escalate</button>
                    <button type="button" class="rounded-xl border py-2.5 text-sm font-black" :class="shell.btnGhost" :disabled="resolveBusy" @click="resolve('dismiss')">Dismiss</button>
                </div>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import { onMounted, reactive, ref } from 'vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { formatHumanDateTime } from '@/utils/formatHumanDateTime';

const { shell } = useInjectedAdminTheme();

const rows = ref([]);
const loading = ref(false);
const page = ref(1);
const totalPages = ref(1);
const pendingCount = ref(0);
const anomalyTypes = ref([]);
const statusOptions = ref([]);

const filters = reactive({
    status: 'pending',
    severity: '',
    anomaly_type: '',
    from: '',
    to: '',
    sort: 'date_desc',
});

const resolveOpen = ref(false);
const selected = ref(null);
const resolutionNote = ref('');
const resolveBusy = ref(false);

onMounted(reload);

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('admin.api.financial-review.listing'), {
            params: { page: page.value, per_page: 25, ...filters },
        });
        rows.value = data.items ?? [];
        totalPages.value = data.meta?.last_page ?? 1;
        pendingCount.value = data.meta?.pending_count ?? 0;
        anomalyTypes.value = data.anomaly_types ?? [];
        statusOptions.value = data.status_options ?? [];
    } finally {
        loading.value = false;
    }
}

function openResolve(row) {
    selected.value = row;
    resolutionNote.value = '';
    resolveOpen.value = true;
}

async function resolve(action) {
    if (!selected.value) return;
    resolveBusy.value = true;
    try {
        await window.axios.post(route('admin.api.financial-review.resolve', selected.value.id), {
            action,
            resolution_note: resolutionNote.value || null,
        });
        resolveOpen.value = false;
        await reload();
    } finally {
        resolveBusy.value = false;
    }
}

function formatNgn(minor) {
    return `₦${(Number(minor || 0) / 100).toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function formatWhen(iso) {
    return iso ? formatHumanDateTime(iso) : '—';
}

function severityClass(severity) {
    return {
        high: 'bg-rose-100 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200',
        medium: 'bg-amber-100 text-amber-900',
        low: 'bg-slate-100 text-slate-700',
    }[severity] || '';
}
</script>
