<template>
    <AppShell title="Wallet" subtitle="Your NGN balance, escrow releases, and bank withdrawals.">
        <div class="mx-auto max-w-4xl space-y-6">
            <section class="rounded-3xl border border-primary-100 bg-gradient-to-br from-primary-50 via-white to-teal-50/40 p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-800">Available balance</p>
                <p class="mt-2 font-display text-4xl font-black text-slate-900">{{ wallet.balance }}</p>
                <p class="mt-2 text-sm font-semibold text-slate-600">
                    <span v-if="wallet.is_locked" class="text-rose-700">Wallet locked — {{ wallet.lock_reason || 'contact support' }}</span>
                    <span v-else>Withdraw to your Nigerian bank account anytime.</span>
                </p>
                <p class="mt-1 text-xs font-bold text-slate-500">Min withdrawal {{ withdrawalMin }} · Fee {{ withdrawalFee }}</p>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-black uppercase tracking-wide text-slate-800">Withdraw funds</h2>
                    <form class="mt-4 space-y-3" @submit.prevent="submitWithdraw">
                        <div>
                            <label class="text-xs font-bold text-slate-600">Amount (₦)</label>
                            <input
                                v-model="withdrawForm.amount"
                                type="number"
                                min="1000"
                                step="0.01"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                                :disabled="wallet.is_locked || withdrawForm.processing"
                            />
                            <InputError :message="withdrawForm.errors.amount" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Bank account</label>
                            <select
                                v-model="withdrawForm.bank_account_id"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                                :disabled="!bankAccounts.length"
                            >
                                <option value="">Select account</option>
                                <option v-for="b in bankAccounts" :key="b.id" :value="b.id">
                                    {{ b.bank_name }} · {{ b.account_name }} ({{ b.account_number_masked }})
                                </option>
                            </select>
                            <InputError :message="withdrawForm.errors.bank_account_id" />
                        </div>
                        <button
                            type="submit"
                            class="w-full rounded-xl bg-primary-700 px-4 py-3 text-sm font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800 disabled:opacity-50"
                            :disabled="wallet.is_locked || withdrawForm.processing || !bankAccounts.length"
                        >
                            {{ withdrawForm.processing ? 'Processing…' : 'Withdraw to bank' }}
                        </button>
                    </form>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-black uppercase tracking-wide text-slate-800">Bank account</h2>
                    <form class="mt-4 space-y-3" @submit.prevent="submitBank">
                        <div>
                            <label class="text-xs font-bold text-slate-600">Bank</label>
                            <select v-model="bankForm.bank_code" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold" @change="onBankPick">
                                <option value="">Select bank</option>
                                <option v-for="b in banks" :key="b.code" :value="b.code">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Account number</label>
                            <input
                                v-model="bankForm.account_number"
                                type="text"
                                maxlength="10"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold"
                                @blur="resolveAccount"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-600">Account name</label>
                            <input v-model="bankForm.account_name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold" readonly />
                            <p v-if="resolving" class="mt-1 text-xs font-bold text-primary-700">Resolving account…</p>
                        </div>
                        <button type="submit" class="w-full rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm font-black uppercase text-primary-900 hover:bg-primary-100 disabled:opacity-50" :disabled="bankForm.processing">
                            Save bank account
                        </button>
                    </form>
                </section>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-black uppercase tracking-wide text-slate-800">Transaction history</h2>
                <div v-if="!transactions.length" class="mt-4 rounded-xl bg-slate-50 px-4 py-8 text-center text-sm font-semibold text-slate-500">
                    No wallet activity yet. Escrow releases and withdrawals appear here.
                </div>
                <ul v-else class="mt-4 divide-y divide-slate-100">
                    <li v-for="tx in transactions" :key="tx.id" class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="text-sm font-black text-slate-900">{{ tx.description || tx.type.replace(/_/g, ' ') }}</p>
                            <p class="text-xs font-semibold text-slate-500">{{ tx.reference }} · {{ formatDate(tx.occurred_at) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-black" :class="tx.direction === 'credit' ? 'text-emerald-700' : tx.direction === 'debit' ? 'text-rose-700' : 'text-slate-700'">
                                {{ tx.direction === 'credit' ? '+' : tx.direction === 'debit' ? '−' : '' }}{{ tx.amount }}
                            </p>
                            <p class="text-xs font-bold text-slate-500">Bal {{ tx.balance_after }}</p>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    wallet: { type: Object, required: true },
    transactions: { type: Array, default: () => [] },
    bankAccounts: { type: Array, default: () => [] },
    banks: { type: Array, default: () => [] },
    withdrawalMin: { type: String, default: '' },
    withdrawalFee: { type: String, default: '' },
});

const page = usePage();
const resolving = ref(false);

const withdrawForm = useForm({
    amount: '',
    bank_account_id: props.bankAccounts[0]?.id ?? '',
});

const bankForm = useForm({
    bank_code: '',
    bank_name: '',
    account_number: '',
    account_name: '',
});

function onBankPick() {
    const bank = props.banks.find((b) => b.code === bankForm.bank_code);
    bankForm.bank_name = bank?.name ?? '';
}

async function resolveAccount() {
    if (bankForm.bank_code.length < 2 || bankForm.account_number.length !== 10) {
        return;
    }
    resolving.value = true;
    try {
        const { data } = await window.axios.post(route('wallet.resolve-account'), {
            bank_code: bankForm.bank_code,
            account_number: bankForm.account_number,
        });
        bankForm.account_name = data.account_name ?? '';
    } catch {
        bankForm.account_name = '';
    } finally {
        resolving.value = false;
    }
}

function submitBank() {
    bankForm.post(route('wallet.bank-accounts.store'), { preserveScroll: true });
}

function submitWithdraw() {
    withdrawForm.post(route('wallet.withdraw'), { preserveScroll: true });
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}
</script>
