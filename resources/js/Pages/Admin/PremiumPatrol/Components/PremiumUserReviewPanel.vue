<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-[85] flex justify-end bg-slate-900/45 backdrop-blur-sm" @click.self="emit('close')">
                <Transition enter-active-class="transition duration-250 ease-out" enter-from-class="translate-x-full" leave-active-class="transition duration-200 ease-in" leave-to-class="translate-x-full">
                    <aside v-if="open" class="flex h-full w-full max-w-2xl flex-col border-l bg-white shadow-2xl dark:bg-slate-950" role="dialog">
                        <div v-if="loading" class="flex flex-1 items-center justify-center p-8">
                            <span class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
                        </div>

                        <template v-else-if="detail">
                            <header class="shrink-0 border-b px-5 py-4">
                                <div class="flex items-start gap-4">
                                    <img :src="detail.header.avatar_url || defaultAvatar(detail.header.fullname)" alt="" class="h-14 w-14 rounded-2xl object-cover ring-2 ring-primary-100" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-primary-600">Premium user review</p>
                                        <h2 class="truncate text-lg font-black text-slate-900 dark:text-white">{{ detail.header.fullname }}</h2>
                                        <p class="text-xs font-semibold text-slate-500">{{ detail.header.location }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase dark:bg-slate-800">{{ detail.header.account_status_label }}</span>
                                            <span class="rounded-full px-2.5 py-0.5 text-[10px] font-black" :class="trustBandClass(detail.header.trust_band)">Trust {{ detail.header.trust_score }}</span>
                                            <span class="rounded-full bg-primary-100 px-2.5 py-0.5 text-[10px] font-black text-primary-800">L{{ detail.header.verification_tier }}</span>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 gap-1">
                                        <a :href="detail.header.profile_url" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Full profile" target="_blank" rel="noopener">
                                            <ArrowTopRightOnSquareIcon class="h-5 w-5" />
                                        </a>
                                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Close" @click="emit('close')">
                                            <XMarkIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                            </header>

                            <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                                <section v-if="detail.subscription" class="overflow-hidden rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 to-white dark:border-primary-800 dark:from-primary-950/40 dark:to-slate-950">
                                    <div class="border-b border-primary-100 px-4 py-2 dark:border-primary-900">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-primary-700 dark:text-primary-300">Premium subscription</p>
                                    </div>
                                    <dl class="grid gap-3 p-4 text-sm sm:grid-cols-2">
                                        <div><dt class="text-[10px] font-black uppercase text-slate-500">Plan</dt><dd class="font-bold">Premium ({{ detail.subscription.billing_label }})</dd></div>
                                        <div><dt class="text-[10px] font-black uppercase text-slate-500">Cost</dt><dd class="font-bold">{{ detail.subscription.cost_display }}</dd></div>
                                        <div><dt class="text-[10px] font-black uppercase text-slate-500">Signup date</dt><dd class="font-semibold">{{ formatDate(detail.subscription.signup_date) }}</dd></div>
                                        <div><dt class="text-[10px] font-black uppercase text-slate-500">Renewal date</dt><dd class="font-semibold">{{ formatDate(detail.subscription.renewal_date) }}</dd></div>
                                        <div><dt class="text-[10px] font-black uppercase text-slate-500">Auto-renew</dt><dd class="font-semibold">{{ detail.subscription.auto_renew ? 'ON' : 'OFF' }}</dd></div>
                                        <div><dt class="text-[10px] font-black uppercase text-slate-500">Status</dt><dd class="font-black" :class="detail.subscription.is_active ? 'text-emerald-600' : 'text-amber-600'">{{ detail.subscription.is_active ? '⭐ ACTIVE' : detail.subscription.status_label }}</dd></div>
                                        <div><dt class="text-[10px] font-black uppercase text-slate-500">Days remaining</dt><dd class="font-semibold">{{ detail.subscription.days_remaining }} days</dd></div>
                                        <div><dt class="text-[10px] font-black uppercase text-slate-500">Subscriber ID</dt><dd class="font-mono text-xs">{{ detail.subscription.subscriber_id }}</dd></div>
                                        <div><dt class="text-[10px] font-black uppercase text-slate-500">Payment method</dt><dd class="font-semibold">{{ detail.subscription.payment_provider }}</dd></div>
                                        <div v-if="detail.subscription.card_last4"><dt class="text-[10px] font-black uppercase text-slate-500">Card last 4</dt><dd class="font-semibold">•••• {{ detail.subscription.card_last4 }}</dd></div>
                                    </dl>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Verification status</h3>
                                    <ul class="mt-3 space-y-3">
                                        <li v-for="item in detail.verification_timeline" :key="item.key" class="flex gap-3 text-sm">
                                            <span class="mt-0.5 text-emerald-600">{{ item.status === 'valid' ? '✓' : '○' }}</span>
                                            <div>
                                                <p class="font-bold">{{ item.label }}</p>
                                                <p class="text-xs text-slate-500">{{ item.detail }}</p>
                                                <p v-if="item.verified_at" class="text-xs text-slate-400">{{ formatDate(item.verified_at) }}</p>
                                            </div>
                                        </li>
                                    </ul>
                                </section>

                                <section class="rounded-2xl border p-4" :class="detail.risk.level === 'high' ? 'border-red-200 bg-red-50/30' : 'border-slate-200 dark:border-slate-800'">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Risk assessment</h3>
                                    <p class="mt-3 text-sm font-black" :class="riskTextClass(detail.risk.level)">Risk score: {{ detail.risk.score }}% ({{ detail.risk.level_label }})</p>
                                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div class="h-full rounded-full transition-all" :class="riskBarClass(detail.risk.level)" :style="{ width: `${detail.risk.score}%` }" />
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Trust score: {{ detail.risk.trust_score }} · Patrol signals: {{ detail.risk.patrol_risk }}</p>
                                    <ul class="mt-3 space-y-1.5 text-xs font-semibold">
                                        <li v-for="(factor, idx) in detail.risk.factors" :key="idx" :class="factor.ok ? 'text-slate-600' : 'text-orange-700'">{{ factor.ok ? '✓' : '✗' }} {{ factor.label }}</li>
                                    </ul>
                                    <p v-if="detail.risk.flagged" class="mt-3 rounded-xl bg-orange-50 px-3 py-2 text-xs font-bold text-orange-800 dark:bg-orange-950/40">{{ detail.risk.flag_summary }}</p>
                                    <p v-else class="mt-3 text-xs font-semibold text-emerald-700">No anomalies detected.</p>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Activity (last 30 days)</h3>
                                    <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                                        <p><span class="font-black">Proposals:</span> {{ detail.activity.proposals_30d }}{{ detail.activity.is_premium ? ' (unlimited)' : '' }}</p>
                                        <p><span class="font-black">Contracts won:</span> {{ detail.activity.contracts_won_30d }}</p>
                                        <p><span class="font-black">Jobs completed:</span> {{ detail.activity.jobs_completed_30d }}</p>
                                        <p><span class="font-black">Earnings:</span> {{ detail.activity.earnings_30d_display }}</p>
                                    </div>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Disputes & complaints</h3>
                                    <p v-if="detail.disputes.count === 0" class="mt-3 text-sm font-semibold text-emerald-700">All clear — no disputes.</p>
                                    <ul v-else class="mt-3 space-y-3">
                                        <li v-for="d in detail.disputes.items" :key="d.id" class="rounded-xl border p-3 text-sm">
                                            <p class="font-bold">{{ d.quest_title }}</p>
                                            <p class="text-xs text-slate-500">{{ d.status }} · {{ formatDate(d.created_at) }}</p>
                                        </li>
                                    </ul>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Premium purchase history</h3>
                                    <ul class="mt-3 space-y-3">
                                        <li v-for="(p, idx) in detail.purchase_history" :key="p.id" class="rounded-xl border p-3 text-sm" :class="p.possible_duplicate ? 'border-amber-300 bg-amber-50/50' : ''">
                                            <p class="font-bold">[{{ idx + 1 }}] {{ formatDate(p.paid_at) }} — {{ p.amount_display }}</p>
                                            <p class="text-xs text-slate-500">{{ p.reference }} · {{ p.status }}</p>
                                        </li>
                                    </ul>
                                </section>

                                <section v-if="detail.watchlist" class="rounded-2xl border border-orange-200 bg-orange-50/30 p-4">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-orange-700">Watchlist</h3>
                                    <p class="mt-2 text-sm font-bold">{{ detail.watchlist.reason }}</p>
                                    <p class="mt-1 text-xs text-slate-600">Expires {{ formatDate(detail.watchlist.expires_at) }}</p>
                                </section>

                                <section v-if="detail.related_accounts.length" class="rounded-2xl border border-orange-200 p-4">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-orange-700">Related accounts</h3>
                                    <ul class="mt-3 space-y-2">
                                        <li v-for="rel in detail.related_accounts" :key="rel.user_id" class="rounded-xl border p-3 text-sm">
                                            <p class="font-bold">{{ rel.name }}</p>
                                            <p class="text-xs text-slate-500">Risk {{ rel.risk_score }}%</p>
                                        </li>
                                    </ul>
                                </section>
                            </div>

                            <footer class="shrink-0 border-t bg-slate-50 p-4 dark:bg-slate-900/50">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="rounded-xl bg-red-700 px-3 py-2 text-[10px] font-black uppercase text-white disabled:opacity-50" :disabled="actionSubmitting" @click="openAction('suspend')">Suspend</button>
                                    <button type="button" class="rounded-xl bg-amber-600 px-3 py-2 text-[10px] font-black uppercase text-white disabled:opacity-50" :disabled="actionSubmitting" @click="openAction('refund')">Refund</button>
                                    <button type="button" class="rounded-xl border px-3 py-2 text-[10px] font-black uppercase disabled:opacity-50" :class="shell.btnGhost" :disabled="actionSubmitting" @click="openAction('investigate')">Investigate</button>
                                    <button type="button" class="rounded-xl border px-3 py-2 text-[10px] font-black uppercase disabled:opacity-50" :class="shell.btnGhost" :disabled="actionSubmitting" @click="openAction('watchlist')">Watchlist</button>
                                </div>
                            </footer>
                        </template>
                    </aside>
                </Transition>
            </div>
        </Transition>

        <div v-if="actionModal" class="fixed inset-0 z-[95] flex items-center justify-center bg-slate-950/60 p-4" @click.self="actionModal = null">
            <form class="w-full max-w-md rounded-2xl border bg-white p-5 shadow-2xl dark:bg-slate-950" @submit.prevent="submitAction">
                <h3 class="text-base font-black capitalize">{{ actionModal }} premium</h3>
                <label class="mt-4 block text-xs font-bold text-slate-500">
                    Reason
                    <select v-model="actionForm.reason_code" required class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input">
                        <option v-for="r in reasonOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                </label>
                <label v-if="actionModal === 'investigate'" class="mt-3 block text-xs font-bold text-slate-500">
                    Severity
                    <select v-model="actionForm.severity" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </label>
                <label v-if="actionModal === 'watchlist'" class="mt-3 block text-xs font-bold text-slate-500">
                    Duration (days)
                    <input v-model.number="actionForm.watchlist_days" type="number" min="1" max="365" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                </label>
                <label class="mt-3 block text-xs font-bold text-slate-500">
                    Notes
                    <textarea v-model="actionForm.reason_notes" rows="3" maxlength="1000" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                </label>
                <div class="mt-5 flex gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white disabled:opacity-50 dark:bg-white dark:text-slate-900" :disabled="actionSubmitting">
                        <span v-if="actionSubmitting" class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                        Confirm
                    </button>
                    <button type="button" class="rounded-xl border px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost" @click="actionModal = null">Cancel</button>
                </div>
            </form>
        </div>
    </Teleport>
</template>

<script setup>
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { ArrowTopRightOnSquareIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    userId: { type: [Number, String], default: null },
    reasonCodes: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'action-complete']);

const { shell } = useInjectedAdminTheme();

const loading = ref(false);
const detail = ref(null);
const actionModal = ref(null);
const actionSubmitting = ref(false);

const actionForm = reactive({
    reason_code: 'investigation',
    reason_notes: '',
    severity: 'medium',
    watchlist_days: 90,
    send_notification: true,
});

const reasonOptions = computed(() => {
    if (actionModal.value === 'refund') {
        return [
            { value: 'duplicate', label: 'Duplicate charge' },
            { value: 'billing_error', label: 'Billing error' },
            { value: 'fraud', label: 'Fraud / dispute' },
            { value: 'user_request', label: 'User request' },
        ];
    }
    return props.reasonCodes.premium_suspend || [];
});

watch([() => props.open, () => props.userId], ([isOpen, id]) => {
    if (isOpen && id) {
        loadDetail(id);
    }
    if (!isOpen) {
        detail.value = null;
        actionModal.value = null;
    }
}, { immediate: true });

async function loadDetail(userId) {
    loading.value = true;
    detail.value = null;
    try {
        const { data } = await axios.get(route('admin.api.premium-patrol.premium-user', userId));
        detail.value = data;
    } finally {
        loading.value = false;
    }
}

function defaultAvatar(name) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'user')}&background=0d9488&color=fff`;
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function trustBandClass(band) {
    if (band === 'green') return 'bg-emerald-100 text-emerald-800';
    if (band === 'red') return 'bg-red-100 text-red-800';
    return 'bg-amber-100 text-amber-800';
}

function riskTextClass(level) {
    if (level === 'high') return 'text-red-700';
    if (level === 'medium') return 'text-orange-700';
    return 'text-emerald-700';
}

function riskBarClass(level) {
    if (level === 'high') return 'bg-red-500';
    if (level === 'medium') return 'bg-orange-500';
    return 'bg-emerald-500';
}

function openAction(type) {
    actionModal.value = type;
    actionForm.reason_code = type === 'refund' ? 'duplicate' : (type === 'watchlist' ? 'watchlist' : 'investigation');
    actionForm.reason_notes = '';
}

function submitAction() {
    if (!props.userId || actionSubmitting.value) return;

    const routes = {
        suspend: 'admin.premium-patrol.premium-users.suspend',
        refund: 'admin.premium-patrol.premium-users.refund',
        investigate: 'admin.premium-patrol.premium-users.investigate',
        watchlist: 'admin.premium-patrol.premium-users.watchlist',
    };

    actionSubmitting.value = true;
    const payload = { ...actionForm };
    if (actionModal.value === 'investigate') {
        payload.title = `Premium investigation — ${detail.value?.header?.username || detail.value?.header?.fullname}`;
        payload.meta = { severity: actionForm.severity };
    }

    router.post(route(routes[actionModal.value], props.userId), payload, {
        preserveScroll: true,
        onSuccess: () => {
            emit('action-complete', actionModal.value);
            actionModal.value = null;
            loadDetail(props.userId);
        },
        onFinish: () => {
            actionSubmitting.value = false;
        },
    });
}
</script>
