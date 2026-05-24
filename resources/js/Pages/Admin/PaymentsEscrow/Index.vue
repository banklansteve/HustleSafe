<template>
    <AdminShell title="Payments & Escrow" subtitle="Paystack-backed escrows, user wallets, and auditable transaction logs.">
        <div class="space-y-5">
            <div class="grid gap-3 md:grid-cols-4">
                <div v-for="item in summaryTiles" :key="item.label" class="rounded-3xl border border-primary-100 bg-primary-50/50 p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-wider text-primary-800">{{ item.label }}</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ item.value }}</p>
                </div>
            </div>

            <AdminTabbedPage v-model="activeTab" :tabs="tabs" id-prefix="payments-tab" aria-label="Payments sections">
            <AdminTabPanel :current-tab="activeTab" value="escrows" id-prefix="payments-tab">
                <AdminPanel title="Payment escrows" description="Quest-linked escrow records from Paystack funding.">
                    <div class="mb-4 flex flex-wrap gap-2">
                        <input v-model="escrowQ" type="search" placeholder="Search…" class="min-w-[12rem] flex-1 rounded-2xl border px-4 py-2 text-sm font-semibold" :class="shell.input" @input="reloadEscrows" />
                        <select v-model="escrowStatus" class="rounded-2xl border px-3 py-2 text-sm font-bold" :class="shell.input" @change="reloadEscrows">
                            <option value="">All statuses</option>
                            <option v-for="s in status_options" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase text-slate-500">
                                    <th class="px-3 py-2">Ref</th>
                                    <th class="px-3 py-2">Quest</th>
                                    <th class="px-3 py-2">Parties</th>
                                    <th class="px-3 py-2">Amount</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in escrows.data" :key="row.id">
                                    <td class="px-3 py-3 font-mono text-xs">{{ row.reference }}</td>
                                    <td class="px-3 py-3 font-bold">{{ row.quest_title }}</td>
                                    <td class="px-3 py-3 text-xs">{{ row.client }} → {{ row.freelancer }}</td>
                                    <td class="px-3 py-3 font-black">{{ row.amount }}</td>
                                    <td class="px-3 py-3"><StatusPill :status="row.status" /></td>
                                    <td class="px-3 py-3">
                                        <div v-if="row.status === 'funded'" class="flex flex-wrap gap-1">
                                            <button type="button" class="rounded-lg bg-emerald-600 px-2 py-1 text-[10px] font-black uppercase text-white" @click="actionEscrow(row, 'release')">Release</button>
                                            <button type="button" class="rounded-lg bg-rose-600 px-2 py-1 text-[10px] font-black uppercase text-white" @click="actionEscrow(row, 'refund')">Refund</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="wallets" id-prefix="payments-tab">
                <AdminPanel title="User wallets" description="Balances and lock controls.">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase text-slate-500">
                                    <th class="px-3 py-2">User</th>
                                    <th class="px-3 py-2">Balance</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in wallets.data" :key="row.id">
                                    <td class="px-3 py-3">
                                        <p class="font-bold">{{ row.user_name }}</p>
                                        <p class="text-xs text-slate-500">{{ row.user_email }}</p>
                                    </td>
                                    <td class="px-3 py-3 font-black">{{ row.balance }}</td>
                                    <td class="px-3 py-3"><StatusPill :status="row.status" /></td>
                                    <td class="px-3 py-3">
                                        <button
                                            v-if="!row.is_locked"
                                            type="button"
                                            class="rounded-lg border px-2 py-1 text-[10px] font-black uppercase"
                                            @click="lockWallet(row)"
                                        >
                                            Lock
                                        </button>
                                        <button v-else type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click="unlockWallet(row)">
                                            Unlock
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="transactions" id-prefix="payments-tab">
                <AdminPanel title="Wallet transactions" description="Double-entry style ledger per user.">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase text-slate-500">
                                    <th class="px-3 py-2">When</th>
                                    <th class="px-3 py-2">User</th>
                                    <th class="px-3 py-2">Type</th>
                                    <th class="px-3 py-2">Amount</th>
                                    <th class="px-3 py-2">Balance after</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="tx in transactions.data" :key="tx.id">
                                    <td class="px-3 py-3 text-xs">{{ formatDate(tx.occurred_at) }}</td>
                                    <td class="px-3 py-3 font-bold">{{ tx.user }}</td>
                                    <td class="px-3 py-3">{{ tx.type }} <span class="text-slate-400">({{ tx.direction }})</span></td>
                                    <td class="px-3 py-3 font-black">{{ tx.amount }}</td>
                                    <td class="px-3 py-3">{{ tx.balance_after }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AdminPanel>
            </AdminTabPanel>
            </AdminTabbedPage>
        </div>
    </AdminShell>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminShell from '@/Layouts/AdminShell.vue';
import AdminTabbedPage from '@/Components/Admin/AdminTabbedPage.vue';
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import StatusPill from '@/Components/Admin/StatusPill.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';

const props = defineProps({
    tab: { type: String, default: 'escrows' },
    summary: { type: Object, required: true },
    escrows: { type: Object, required: true },
    wallets: { type: Object, required: true },
    transactions: { type: Object, required: true },
    status_options: { type: Array, default: () => [] },
});

const shell = useInjectedAdminTheme();
const activeTab = ref(props.tab);
watch(
    () => props.tab,
    (tab) => {
        if (tab && ['escrows', 'wallets', 'transactions'].includes(tab)) {
            activeTab.value = tab;
        }
    },
);
const escrowQ = ref('');
const escrowStatus = ref('');

const tabs = [
    { key: 'escrows', label: 'Escrows' },
    { key: 'wallets', label: 'Wallets' },
    { key: 'transactions', label: 'Transactions' },
];

const summaryTiles = computed(() => [
    { label: 'Escrow held', value: props.summary.escrow_held },
    { label: 'Released', value: props.summary.released_total },
    { label: 'Wallet balances', value: props.summary.wallet_balances },
    { label: 'Paystack', value: props.summary.paystack_enabled ? 'Sandbox on' : 'Not configured' },
]);

function reloadEscrows() {
    router.get(route('admin.payments-escrow.index'), { tab: 'escrows', q: escrowQ.value, status: escrowStatus.value }, { preserveState: true, only: ['escrows'] });
}

async function actionEscrow(row, action) {
    const reason = window.prompt(action === 'release' ? 'Reason for force release (min 10 chars)' : 'Reason for refund (min 10 chars)');
    if (!reason || reason.length < 10) return;
    const url =
        action === 'release'
            ? route('admin.payments-escrow.escrows.release', row.id)
            : route('admin.payments-escrow.escrows.refund', row.id);
    try {
        await window.axios.post(url, { reason });
        router.reload({ only: ['escrows', 'summary', 'transactions'] });
        window.dispatchEvent(new CustomEvent('admin:toast', { detail: { type: 'success', message: 'Action completed.' } }));
    } catch (e) {
        window.dispatchEvent(new CustomEvent('admin:toast', { detail: { type: 'error', message: e?.response?.data?.message || 'Action failed.' } }));
    }
}

async function lockWallet(row) {
    const reason = window.prompt('Lock reason (min 10 chars)');
    if (!reason || reason.length < 10) return;
    await window.axios.post(route('admin.payments-escrow.wallet.lock', row.user_id), { reason });
    router.reload({ only: ['wallets'] });
}

async function unlockWallet(row) {
    await window.axios.post(route('admin.payments-escrow.wallet.unlock', row.user_id));
    router.reload({ only: ['wallets'] });
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
</script>
