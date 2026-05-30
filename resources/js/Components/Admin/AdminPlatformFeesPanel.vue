<template>
    <AdminSlideOver
        :open="open"
        title="Platform fee ledger"
        eyebrow="Revenue intelligence"
        width-class="max-w-3xl"
        @close="emit('close')"
    >
        <div class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div v-for="tile in tiles" :key="tile.label" class="rounded-2xl border border-primary-200/80 bg-gradient-to-br from-white to-primary-50/60 p-4 shadow-sm dark:border-primary-500/20 dark:from-slate-900 dark:to-primary-950/30">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ tile.label }}</p>
                    <p class="mt-2 font-display text-2xl font-bold text-slate-900 dark:text-white">{{ tile.value }}</p>
                    <p class="mt-1 text-[11px] font-semibold text-slate-500">Earned fees (released)</p>
                </div>
            </div>

            <div class="rounded-2xl border p-4" :class="shell.card">
                <div class="grid gap-3 md:grid-cols-2">
                    <input
                        v-model="filters.q"
                        type="search"
                        placeholder="Quest, client, freelancer, contract ref…"
                        class="rounded-xl border px-3 py-2.5 text-sm font-semibold md:col-span-2"
                        :class="shell.input"
                        @input="debouncedFetch"
                    />
                    <AdminDateInput v-model="filters.from" button-class="rounded-xl border px-3 py-2.5 text-sm font-semibold" @change="fetchRows" />
                    <AdminDateInput v-model="filters.to" button-class="rounded-xl border px-3 py-2.5 text-sm font-semibold" @change="fetchRows" />
                    <select v-model="filters.fee_status" class="rounded-xl border px-3 py-2.5 text-sm font-bold" :class="shell.input" @change="fetchRows">
                        <option value="">All fee states</option>
                        <option value="earned">Earned (released)</option>
                        <option value="pending">Not yet earned</option>
                    </select>
                    <select v-model="filters.sort" class="rounded-xl border px-3 py-2.5 text-sm font-bold" :class="shell.input" @change="fetchRows">
                        <option v-for="opt in sortOptions" :key="opt.key" :value="opt.key">{{ opt.label }}</option>
                    </select>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-full border px-3 py-1.5 text-xs font-black uppercase tracking-wide"
                        :class="filters.direction === 'desc' ? 'border-primary-600 bg-primary-600 text-white' : shell.cardMuted"
                        @click="toggleDirection"
                    >
                        {{ filters.direction === 'desc' ? 'Newest first' : 'Oldest first' }}
                    </button>
                    <p class="text-xs font-semibold" :class="shell.cardMuted">Reference fee rate {{ feePercent }}%</p>
                    <a
                        :href="exportUrl"
                        class="ml-auto inline-flex rounded-full border border-primary-300 bg-white px-3 py-1.5 text-xs font-black uppercase tracking-wide text-primary-800 hover:bg-primary-50 dark:border-primary-500/40 dark:bg-transparent dark:text-primary-200"
                    >
                        Export CSV
                    </a>
                </div>
            </div>

            <div v-if="loading" class="rounded-2xl border p-8 text-center text-sm font-bold" :class="shell.card">Loading fee records…</div>
            <div v-else-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">{{ error }}</div>
            <div v-else class="overflow-hidden rounded-2xl border" :class="shell.card">
                <div class="max-h-[min(70vh,520px)] overflow-y-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="sticky top-0 z-10 text-[10px] font-black uppercase tracking-wider backdrop-blur" :class="shell.tableHead">
                            <tr>
                                <th class="px-3 py-3">Quest / contract</th>
                                <th class="px-3 py-3">Parties</th>
                                <th class="px-3 py-3">Totals</th>
                                <th class="px-3 py-3">Fee</th>
                                <th class="px-3 py-3">Timeline</th>
                                <th class="px-3 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody :class="['divide-y', shell.tableDivide]">
                            <tr v-for="row in rows" :key="row.id" class="align-top transition hover:bg-primary-50/50 dark:hover:bg-white/[0.03]">
                                <td class="px-3 py-3">
                                    <a
                                        v-if="row.admin_quest_url"
                                        :href="row.admin_quest_url"
                                        class="font-black text-primary-700 hover:underline dark:text-primary-300"
                                    >
                                        {{ row.quest_title }}
                                    </a>
                                    <p v-else class="font-black text-slate-900 dark:text-white">{{ row.quest_title }}</p>
                                    <p class="mt-0.5 font-mono text-[10px] text-slate-500">{{ row.contract_ref }}</p>
                                    <p class="mt-1 text-[10px] font-bold text-slate-500">Proposal #{{ row.proposal_id }}</p>
                                    <a
                                        v-if="row.id"
                                        :href="route('admin.contracts.receipt', row.id)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-1 inline-block text-[10px] font-black uppercase tracking-wide text-slate-500 underline"
                                    >
                                        VAT receipt
                                    </a>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-bold">{{ row.client }}</p>
                                    <p class="text-[10px] text-slate-500">{{ row.client_email }}</p>
                                    <p class="mt-2 font-bold">{{ row.freelancer }}</p>
                                    <p class="text-[10px] text-slate-500">{{ row.freelancer_email }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p><span class="font-black">Grand</span> {{ row.grand_total }}</p>
                                    <p class="mt-1 text-slate-500">VAT {{ row.vat }} · Disc {{ row.discount }}</p>
                                    <p v-if="row.realized_platform_fee" class="mt-1 text-emerald-700">Realized {{ row.realized_platform_fee }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="text-lg font-black text-primary-700 dark:text-primary-300">{{ row.platform_fee }}</p>
                                    <p class="text-[10px] font-semibold text-slate-500">Quoted {{ row.quoted_platform_fee }}</p>
                                </td>
                                <td class="px-3 py-3 text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                                    <p>Start {{ row.job_start || '—' }}</p>
                                    <p>End {{ row.job_end || '—' }}</p>
                                    <p class="mt-1">Funded {{ formatWhen(row.funded_at) }}</p>
                                    <p v-if="row.earned_at">Earned {{ formatWhen(row.earned_at) }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide"
                                        :class="row.fee_status === 'earned' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200' : 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-100'"
                                    >
                                        {{ row.fee_status_label }}
                                    </span>
                                    <p class="mt-2 text-[10px] font-bold capitalize text-slate-500">{{ row.quest_status?.replace(/_/g, ' ') }}</p>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="6" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">No fee records match your filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <nav v-if="paginationLinks.length > 3" class="flex flex-wrap justify-center gap-2">
                <button
                    v-for="(link, idx) in paginationLinks"
                    :key="idx"
                    type="button"
                    class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                    :class="[
                        link.active ? 'bg-primary-600 text-white' : 'border border-slate-200 text-slate-700 dark:border-white/10 dark:text-slate-200',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                    :disabled="!link.url"
                    @click="goPage(link.url)"
                    v-html="link.label"
                />
            </nav>
        </div>
    </AdminSlideOver>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const { shell } = useInjectedAdminTheme();

const loading = ref(false);
const error = ref('');
const rows = ref([]);
const tiles = ref([
    { label: 'Earned today', value: '—' },
    { label: 'This week', value: '—' },
    { label: 'This month', value: '—' },
]);
const feePercent = ref(12);
const sortOptions = ref([]);
const paginationLinks = ref([]);

const filters = reactive({
    q: '',
    from: '',
    to: '',
    fee_status: '',
    sort: 'funded_at',
    direction: 'desc',
    per_page: 20,
});

const exportUrl = computed(() => route('admin.api.platform-fees.export', { ...filters }));

let debounceTimer = null;

function debouncedFetch() {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => fetchRows(), 320);
}

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}

async function fetchRows(url = null) {
    loading.value = true;
    error.value = '';
    try {
        const target = url ?? route('admin.api.platform-fees.index', { ...filters });
        const { data } = await window.axios.get(target);
        rows.value = data.rows?.data ?? [];
        paginationLinks.value = data.rows?.links ?? [];
        sortOptions.value = data.sort_options ?? [];
        feePercent.value = data.fee_percent ?? 12;
        tiles.value = [
            { label: 'Earned today', value: data.tiles?.today ?? '—' },
            { label: 'This week', value: data.tiles?.week ?? '—' },
            { label: 'This month', value: data.tiles?.month ?? '—' },
        ];
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Could not load platform fees.';
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

function toggleDirection() {
    filters.direction = filters.direction === 'desc' ? 'asc' : 'desc';
    fetchRows();
}

function goPage(url) {
    if (!url) {
        return;
    }
    fetchRows(url);
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            fetchRows();
        }
    },
);
</script>
