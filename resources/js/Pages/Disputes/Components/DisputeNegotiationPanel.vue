<template>
    <section class="rounded-2xl border border-primary-100 bg-primary-50/40 p-5 ring-1 ring-primary-100">
        <h2 class="font-display text-sm font-black uppercase tracking-wide text-primary-900">
            {{ negotiation.headline || 'Dispute resolution' }}
        </h2>
        <p v-if="negotiation.phase_label" class="mt-1 text-xs font-bold uppercase tracking-wide text-primary-800">
            {{ negotiation.phase_label }}
        </p>
        <p v-if="negotiation.response_required_by" class="mt-2 text-xs font-bold text-amber-900">
            Respond by {{ formatWhen(negotiation.response_required_by) }}
            <span v-if="negotiation.awaiting_viewer" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] uppercase">Your turn</span>
        </p>

        <p class="mt-3 text-sm font-medium leading-normal text-slate-700">
            Each party gets up to {{ negotiation.max_attempts_per_party }} proposals. If you still disagree, a staff mediator reviews everything and decides.
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
            <span class="rounded-full bg-white px-2 py-1 text-slate-700">Your attempts: {{ negotiation.viewer_attempts_used }}/{{ negotiation.max_attempts_per_party }}</span>
            <span class="rounded-full bg-white px-2 py-1 text-slate-700">Client: {{ negotiation.client_attempts }}/{{ negotiation.max_attempts_per_party }}</span>
            <span class="rounded-full bg-white px-2 py-1 text-slate-700">Freelancer: {{ negotiation.freelancer_attempts }}/{{ negotiation.max_attempts_per_party }}</span>
        </div>

        <div v-if="negotiation.active_offer" class="mt-4 rounded-xl border border-emerald-200 bg-white/80 p-4">
            <p class="text-xs font-black uppercase text-emerald-900">Active proposal</p>
            <p class="mt-1 text-sm font-bold text-slate-900">{{ negotiation.active_offer.summary }}</p>
            <p class="mt-1 text-xs text-slate-600">From {{ negotiation.active_offer.offered_by }} · Attempt {{ negotiation.active_offer.attempt_number }}
                <span v-if="negotiation.active_offer.is_final_offer" class="font-bold text-amber-800"> · Final offer</span>
            </p>
            <p v-if="negotiation.active_offer.terms?.note" class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ negotiation.active_offer.terms.note }}</p>

            <div v-if="negotiation.can_accept || negotiation.can_counter || negotiation.can_reject_final" class="mt-4 flex flex-wrap gap-2">
                <button
                    v-if="negotiation.can_accept"
                    type="button"
                    class="rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase text-white disabled:opacity-50"
                    :disabled="busy"
                    @click="acceptOffer"
                >
                    Accept
                </button>
                <button
                    v-if="negotiation.can_reject_final"
                    type="button"
                    class="rounded-full border border-rose-300 bg-white px-4 py-2 text-xs font-black uppercase text-rose-800 disabled:opacity-50"
                    :disabled="busy"
                    @click="rejectOffer"
                >
                    Reject — go to mediation
                </button>
            </div>
            <p v-if="negotiation.active_offer.is_final_offer && negotiation.can_reject_final" class="mt-2 text-xs font-semibold text-amber-900">
                If you reject, a staff mediator will review all proposals. You may not get a better outcome.
            </p>
        </div>

        <form v-if="negotiation.can_propose || negotiation.can_counter" class="mt-5 space-y-4 rounded-xl border border-white/80 bg-white/70 p-4" @submit.prevent="submitProposal">
            <p class="text-sm font-bold text-slate-900">
                {{ negotiation.active_offer ? 'Counter-propose' : 'Open with your proposal' }}
                <span v-if="negotiation.viewer_attempts_remaining === 1" class="text-amber-800">(your final attempt)</span>
            </p>

            <div class="space-y-2">
                <label
                    v-for="opt in togetherOptions"
                    :key="opt.value"
                    class="flex cursor-pointer gap-3 rounded-xl border px-3 py-3"
                    :class="form.option === opt.value ? 'border-primary-300 bg-primary-50/60' : 'border-slate-100 bg-white'"
                >
                    <input v-model="form.option" type="radio" class="mt-1" :value="opt.value" @change="onOptionChange(opt)" />
                    <span>
                        <span class="block text-sm font-bold">{{ opt.label }}</span>
                        <span class="mt-0.5 block text-xs text-slate-600">{{ opt.hint }}</span>
                    </span>
                </label>
            </div>

            <div v-if="selectedOption?.requires_client_share">
                <label class="text-xs font-bold">Client keeps (%)</label>
                <input v-model.number="form.client_share_percent" type="number" min="0" max="100" class="mt-1 w-32 rounded-xl border-slate-200 text-base font-bold" />
            </div>
            <div v-if="selectedOption?.requires_days">
                <label class="text-xs font-bold">Extra days</label>
                <input v-model.number="form.extend_days" type="number" min="1" max="90" class="mt-1 w-32 rounded-xl border-slate-200 text-base font-bold" />
            </div>
            <div v-if="selectedOption?.optional_revision_days">
                <label class="text-xs font-bold">Days to complete fix (optional)</label>
                <input v-model.number="form.revision_days" type="number" min="1" max="90" class="mt-1 w-32 rounded-xl border-slate-200 text-base font-bold" />
            </div>
            <div v-if="selectedOption?.requires_target_date">
                <label class="text-xs font-bold">New completion date</label>
                <input v-model="form.target_completion_date" type="date" class="mt-1 w-full max-w-xs rounded-xl border-slate-200 text-base font-bold" />
            </div>
            <div v-if="form.option">
                <label class="text-xs font-bold">{{ form.option === 'other' ? 'Describe the agreement' : 'Explain in plain language' }}</label>
                <textarea v-model="form.terms_note" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-base" />
            </div>

            <button type="submit" class="rounded-full bg-primary-700 px-4 py-2.5 text-xs font-black uppercase text-white disabled:opacity-50" :disabled="form.processing || !form.option">
                {{ negotiation.active_offer ? 'Send counter-proposal' : 'Submit proposal' }}
            </button>
        </form>

        <div v-if="negotiation.phase === 'awaiting_mutual_approval'" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/80 p-4">
            <p class="text-sm font-bold text-emerald-950">You agreed on a resolution</p>
            <p class="mt-1 text-xs leading-normal text-emerald-900">
                Customer Support is reviewing your agreement before funds move. You may appeal within {{ mutualAppealDays }} days after approval if you believe it was incorrect.
            </p>
        </div>

        <div v-if="negotiation.phase === 'awaiting_enforcement'" class="mt-4 rounded-xl border border-amber-200 bg-amber-50/80 p-4">
            <p class="text-sm font-bold text-amber-950">A decision was issued</p>
            <p class="mt-1 text-xs leading-normal text-amber-900">
                Review the outcome below. If you disagree, file an appeal before {{ formatWhen(negotiation.rejection_window_ends_at) }} — after that window, the decision is enforced permanently.
            </p>
        </div>

        <div v-if="negotiation.phase === 'mediation' && !negotiation.viewer_binding_acknowledged" class="mt-4 rounded-xl border border-amber-200 bg-amber-50/80 p-4">
            <p class="text-sm font-bold text-amber-950">Before mediation concludes</p>
            <p class="mt-1 text-xs leading-normal text-amber-900">
                You must confirm that the final platform decision will be binding. By posting a quest or sending a proposal on HustleSafe you already agreed to our Dispute Policy — external legal mediation is not available for cases resolved here.
            </p>
            <button type="button" class="mt-3 rounded-full bg-amber-800 px-4 py-2 text-xs font-black uppercase text-white" :disabled="busy" @click="acknowledgeBinding">
                I understand — mediation outcomes are binding
            </button>
        </div>

        <ul v-if="negotiation.history?.length" class="mt-5 space-y-2">
            <li v-for="item in negotiation.history" :key="item.id" class="rounded-lg border border-slate-100 bg-white/60 px-3 py-2 text-xs text-slate-700">
                <span class="font-bold">{{ item.summary }}</span> · {{ item.offered_by }} · {{ item.status }}
            </li>
        </ul>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    negotiation: { type: Object, required: true },
    resolutionOptions: { type: Array, default: () => [] },
    urls: { type: Object, required: true },
});

const busy = ref(false);

const mutualAppealDays = 4;

const togetherOptions = computed(() => props.resolutionOptions.filter((o) => o.path === 'together'));

const form = useForm({
    option: '',
    client_share_percent: 50,
    extend_days: 7,
    revision_days: 7,
    target_completion_date: '',
    terms_note: '',
});

const selectedOption = computed(() => togetherOptions.value.find((o) => o.value === form.option) ?? null);

function onOptionChange(opt) {
    if (opt.default_client_share_percent != null) {
        form.client_share_percent = opt.default_client_share_percent;
    }
}

function submitProposal() {
    form.post(props.urls.negotiation_propose, { preserveScroll: true });
}

function acceptOffer() {
    busy.value = true;
    router.post(props.negotiation.active_offer.accept_url, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function rejectOffer() {
    busy.value = true;
    router.post(props.negotiation.active_offer.reject_url, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function acknowledgeBinding() {
    busy.value = true;
    router.post(props.urls.negotiation_acknowledge_binding, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}
</script>
