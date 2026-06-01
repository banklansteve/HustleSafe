<template>
    <AppShell>
        <Head title="Pro membership" />

        <div class="mx-auto max-w-3xl space-y-8">
            <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
                <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">Freelancer Pro</p>
                <h1 class="font-display mt-3 text-3xl font-black tracking-tight">
                    {{ pro.subscription.is_pro ? 'Your Pro membership' : 'Upgrade to Pro' }}
                </h1>
                <p class="mt-4 max-w-xl text-base font-semibold leading-relaxed text-teal-50">
                    Unlimited proposals, Pro badge, priority visibility, and early access to exclusive quests.
                </p>
            </div>

            <section v-if="!pro.subscription.is_pro" class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100">
                <p class="text-xs font-black uppercase tracking-wide text-slate-500">Free plan usage</p>
                <p class="mt-2 text-sm font-semibold text-slate-700">
                    {{ pro.quota.used }} of {{ pro.quota.limit }} proposals used this month
                </p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-primary-600 transition-all" :style="{ width: `${pro.quota.percent_used}%` }" />
                </div>
            </section>

            <section v-else class="rounded-3xl border border-emerald-200 bg-emerald-50/80 p-6">
                <p class="text-xs font-black uppercase text-emerald-800">Active Pro</p>
                <p class="mt-2 text-sm font-semibold text-emerald-950">
                    Renews {{ formatWhen(pro.subscription.renewal_date) }} · {{ pro.subscription.billing_cycle_label }}
                </p>
                <p class="mt-1 text-xs text-emerald-800">Manual renewal — auto-renew is off. Pro fees are non-refundable.</p>
                <button type="button" class="mt-4 rounded-xl border border-rose-200 bg-white px-4 py-2 text-xs font-black uppercase text-rose-700" @click="cancelOpen = true">
                    Cancel subscription
                </button>
            </section>

            <ul class="grid gap-3 sm:grid-cols-2">
                <li v-for="(benefit, i) in pro.benefits" :key="i" class="rounded-2xl border border-slate-100 bg-white px-4 py-3 text-sm font-semibold text-slate-700">
                    {{ benefit }}
                </li>
            </ul>

            <section v-if="!pro.subscription.is_pro" class="grid gap-4 sm:grid-cols-2">
                <article class="rounded-3xl border p-6 ring-1 ring-slate-100">
                    <p class="text-xs font-black uppercase text-slate-500">Monthly</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ pro.pricing.monthly_display }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Billed monthly · cancel anytime</p>
                    <button type="button" class="mt-6 w-full rounded-xl bg-primary-600 px-4 py-3 text-sm font-black uppercase text-white disabled:opacity-50" :disabled="upgradeForm.processing" @click="upgrade('month')">
                        Upgrade now
                    </button>
                </article>
                <article class="rounded-3xl border-2 border-amber-300 bg-amber-50/40 p-6">
                    <p class="text-xs font-black uppercase text-amber-800">Annual · save {{ pro.pricing.annual_savings_percent }}%</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ pro.pricing.annual_display }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Best value · 12 months access</p>
                    <button type="button" class="mt-6 w-full rounded-xl bg-amber-600 px-4 py-3 text-sm font-black uppercase text-white disabled:opacity-50" :disabled="upgradeForm.processing" @click="upgrade('year')">
                        Upgrade now
                    </button>
                </article>
            </section>

            <div v-if="cancelOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center">
                <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl">
                    <h2 class="text-lg font-black text-slate-900">Cancel Pro?</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Cancellation takes effect immediately. You revert to the free tier. Pro fees are non-refundable.</p>
                    <textarea v-model="cancelForm.reason" rows="3" class="mt-4 w-full rounded-xl border px-3 py-2 text-sm" placeholder="Reason (optional)" />
                    <div class="mt-4 flex gap-2">
                        <button type="button" class="flex-1 rounded-xl bg-rose-600 py-2.5 text-xs font-black uppercase text-white" :disabled="cancelForm.processing" @click="submitCancel">Confirm cancel</button>
                        <button type="button" class="flex-1 rounded-xl border py-2.5 text-xs font-black uppercase" @click="cancelOpen = false">Keep Pro</button>
                    </div>
                </div>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    pro: { type: Object, required: true },
});

const cancelOpen = ref(false);
const upgradeForm = useForm({ billing_cycle: 'month' });
const cancelForm = useForm({ reason: '' });

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-NG', { dateStyle: 'medium' });
}

function upgrade(cycle) {
    upgradeForm.billing_cycle = cycle;
    upgradeForm.post(route('freelancer.pro.upgrade'));
}

function submitCancel() {
    cancelForm.post(route('freelancer.pro.cancel'), {
        onSuccess: () => { cancelOpen.value = false; },
    });
}
</script>
