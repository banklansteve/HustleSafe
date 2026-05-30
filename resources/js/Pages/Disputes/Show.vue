<template>
    <AppShell>
        <Head :title="`Dispute · ${dispute.quest.title}`" />

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <BackChevronLink :href="route('quests.show', dispute.quest.route_key)" aria-label="Back to quest" />
                <Link
                    :href="route('disputes.index')"
                    class="text-xs font-black uppercase tracking-wide text-primary-800 underline underline-offset-2"
                >
                    All disputes
                </Link>
            </div>

            <header class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">{{ dispute.status }} · {{ dispute.phase }}</p>
                <h1 class="font-display mt-2 text-2xl font-black text-slate-900">
                    {{ dispute.quest.title }}
                </h1>
                <p class="mt-2 text-sm font-semibold text-slate-700">
                    {{ dispute.reason_label }}
                </p>
                <div v-if="dispute.response_required_by" class="mt-3 text-xs font-bold text-amber-900">
                    Response window · {{ formatWhen(dispute.response_required_by) }}
                </div>
                <div v-if="dispute.ruling_required_by" class="mt-1 text-xs font-bold text-rose-900">
                    Formal review closes · {{ formatWhen(dispute.ruling_required_by) }}
                </div>
            </header>

            <SlaExpectationNotice v-if="sla_expectation" :message="sla_expectation" />

            <section class="rounded-2xl border border-slate-100 bg-slate-50/80 p-5 ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-600">
                    Policy snapshot
                </h2>
                <ul class="mt-2 list-disc space-y-1 pl-4 text-xs font-semibold text-slate-700">
                    <li>Minimum value ₦{{ (policy.minimum_disputed_amount_minor / 100).toLocaleString() }}</li>
                    <li>Self-resolution ping-pong · {{ policy.self_resolution_hours }}h per turn</li>
                    <li>Escalation then neutral default split if {{ policy.formal_ruling_hours }}h elapses (stub disbursement)</li>
                    <li>Platform fee reference {{ policy.platform_fee_percent }}% · appeals {{ policy.max_appeals }}</li>
                    <li>{{ policy.suspension_threshold }} lost disputes may trigger account review</li>
                </ul>
                <a :href="workflow_doc_url" class="mt-3 inline-block text-xs font-black uppercase tracking-wide text-primary-800 underline" target="_blank" rel="noopener noreferrer">Read full workflow</a>
            </section>

            <section v-if="can_participate && isOpen" class="space-y-4 rounded-2xl border border-primary-100 bg-primary-50/40 p-5 ring-1 ring-primary-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-primary-900">
                    Post structured update
                </h2>
                <form class="space-y-3" @submit.prevent="submitMessage">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-bold uppercase text-slate-600">Kind</label>
                            <select v-model="messageForm.kind" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm">
                                <option value="narrative">Narrative</option>
                                <option value="evidence">Evidence bundle</option>
                                <option value="structured_response">Structured response</option>
                                <option value="settlement_note">Settlement note</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase text-slate-600">Structured key (optional)</label>
                            <input v-model="messageForm.structured_key" type="text" class="mt-1 w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="e.g. milestone-2" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-600">Body</label>
                        <textarea v-model="messageForm.body" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-sm shadow-sm" />
                    </div>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white disabled:opacity-50"
                        :disabled="messageForm.processing"
                    >
                        <ReLoader4Line v-if="messageForm.processing" class="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                        Post update
                    </button>
                </form>

                <form class="space-y-3 border-t border-primary-100/80 pt-4" @submit.prevent="submitSettlement">
                    <h3 class="text-xs font-black uppercase tracking-wide text-primary-900">
                        Proposed fund split (client %)</h3>
                    <div class="flex flex-wrap items-end gap-2">
                        <input v-model.number="settlementForm.client_share_percent" type="number" min="0" max="100" class="w-24 rounded-xl border-slate-200 text-sm font-bold shadow-sm" />
                        <input v-model="settlementForm.note" type="text" class="min-w-[12rem] flex-1 rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Short note (optional)" />
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white disabled:opacity-50"
                            :disabled="settlementForm.processing"
                        >
                            <ReLoader4Line v-if="settlementForm.processing" class="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                            Offer split
                        </button>
                    </div>
                </form>

                <form class="border-t border-primary-100/80 pt-4" @submit.prevent="submitMutual">
                    <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                        <input v-model="mutualForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                        <span>I agree to resolve this dispute without a formal ruling (mutual close).</span>
                    </label>
                    <button
                        type="submit"
                        class="mt-3 inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white disabled:opacity-50"
                        :disabled="mutualForm.processing || !mutualForm.confirm"
                    >
                        <ReLoader4Line v-if="mutualForm.processing" class="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                        Record mutual resolve
                    </button>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Settlement offers
                </h2>
                <ul class="mt-3 space-y-3">
                    <li v-for="o in dispute.settlement_offers" :key="o.id" class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-semibold text-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span>Client {{ o.client_share_percent }}% · {{ o.status }}</span>
                            <span class="text-xs text-slate-500">by {{ o.offered_by?.name }}</span>
                        </div>
                        <p v-if="o.note" class="mt-1 text-xs font-medium text-slate-600">
                            {{ o.note }}
                        </p>
                        <div v-if="o.accept_url && o.decline_url" class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-full bg-emerald-700 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white disabled:opacity-50"
                                :disabled="offerBusyId === o.id"
                                @click="acceptOffer(o)"
                            >
                                <ReLoader4Line v-if="offerBusyId === o.id && offerBusyAction === 'accept'" class="mr-1 inline h-3 w-3 animate-spin" aria-hidden="true" />
                                Accept
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-rose-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-rose-800 disabled:opacity-50"
                                :disabled="offerBusyId === o.id"
                                @click="declineOffer(o)"
                            >
                                <ReLoader4Line v-if="offerBusyId === o.id && offerBusyAction === 'decline'" class="mr-1 inline h-3 w-3 animate-spin" aria-hidden="true" />
                                Decline
                            </button>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Private thread
                </h2>
                <ul class="mt-4 space-y-4">
                    <li v-for="m in dispute.messages" :key="m.id" class="rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-3">
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                            {{ m.kind }} · {{ m.user ? m.user.name : 'System' }} · {{ formatWhen(m.created_at) }}
                        </p>
                        <p v-if="m.body" class="mt-2 whitespace-pre-wrap text-sm font-medium text-slate-800">
                            {{ m.body }}
                        </p>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Audit trail
                </h2>
                <ul class="mt-3 space-y-2 text-xs font-semibold text-slate-600">
                    <li v-for="(e, i) in dispute.events" :key="i">
                        <span class="font-black text-slate-900">{{ e.action }}</span>
                        · {{ formatWhen(e.created_at) }}
                        <span v-if="e.actor">· {{ e.actor.name }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import SlaExpectationNotice from '@/Components/Platform/SlaExpectationNotice.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { computed, ref } from 'vue';

const props = defineProps({
    dispute: { type: Object, required: true },
    can_participate: { type: Boolean, default: false },
    philosophy: { type: Object, default: () => ({}) },
    policy: { type: Object, required: true },
    workflow_doc_url: { type: String, default: '/docs/dispute-workflow.md' },
    sla_expectation: { type: String, default: '' },
});

const isOpen = computed(() => !['resolved', 'closed_withdrawn'].includes(props.dispute.status));

const messageForm = useForm({
    kind: 'narrative',
    body: '',
    structured_key: '',
    structured_payload: null,
});

const settlementForm = useForm({
    client_share_percent: 50,
    note: '',
});

const mutualForm = useForm({
    confirm: false,
});

const offerBusyId = ref(null);
const offerBusyAction = ref(null);

function submitMessage() {
    messageForm.post(props.dispute.urls.message, { preserveScroll: true, onSuccess: () => messageForm.reset('body') });
}

function submitSettlement() {
    settlementForm.post(props.dispute.urls.settlement, { preserveScroll: true });
}

function submitMutual() {
    mutualForm.transform((d) => ({ confirm: !!d.confirm })).post(props.dispute.urls.mutual_resolve, { preserveScroll: true });
}

function acceptOffer(o) {
    offerBusyId.value = o.id;
    offerBusyAction.value = 'accept';
    router.post(o.accept_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            offerBusyId.value = null;
            offerBusyAction.value = null;
        },
    });
}

function declineOffer(o) {
    offerBusyId.value = o.id;
    offerBusyAction.value = 'decline';
    router.post(o.decline_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            offerBusyId.value = null;
            offerBusyAction.value = null;
        },
    });
}

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    } catch {
        return iso;
    }
}
</script>
