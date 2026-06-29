<template>
    <section class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 ring-1 ring-amber-100">
        <h2 class="font-display text-sm font-black uppercase tracking-wide text-amber-950">
            Appeal this decision
        </h2>
        <p class="mt-2 text-sm font-medium leading-normal text-amber-900">
            You have one appeal. Explain why the decision is unfair and what you consider fair. The final outcome after review is binding — no further appeals.
        </p>
        <p v-if="appeal.rejection_window_ends_at" class="mt-2 text-xs font-bold text-amber-800">
            Respond by {{ formatWhen(appeal.rejection_window_ends_at) }}
        </p>
        <p v-else-if="appeal.appeal_window_ends_at" class="mt-2 text-xs font-bold text-amber-800">
            Appeal window ends {{ formatWhen(appeal.appeal_window_ends_at) }}
        </p>

        <form v-if="appeal.can_file" class="mt-4 space-y-4" @submit.prevent="submitAppeal">
            <div>
                <label class="text-xs font-bold text-slate-800">Why is this decision unfair?</label>
                <textarea v-model="form.unfair_reason" rows="5" class="mt-1 w-full rounded-xl border-slate-200 text-base" placeholder="Explain clearly — max 500 words." />
                <p v-if="form.errors.unfair_reason" class="mt-1 text-xs text-rose-700">{{ form.errors.unfair_reason }}</p>
            </div>

            <div class="space-y-2">
                <p class="text-xs font-bold text-slate-800">What resolution would you consider fair?</p>
                <label
                    v-for="opt in togetherOptions"
                    :key="opt.value"
                    class="flex cursor-pointer gap-3 rounded-xl border px-3 py-3"
                    :class="form.proposed_option === opt.value ? 'border-amber-300 bg-white' : 'border-slate-100 bg-white/80'"
                >
                    <input v-model="form.proposed_option" type="radio" class="mt-1" :value="opt.value" />
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

            <div class="rounded-xl border border-rose-200 bg-rose-50/80 px-3 py-3 text-xs font-semibold text-rose-900">
                By submitting, you confirm you understand the post-appeal decision is final and binding on HustleSafe. External legal mediation is not available for disputes resolved on this platform.
            </div>

            <button type="submit" class="rounded-full bg-amber-800 px-4 py-2.5 text-xs font-black uppercase text-white disabled:opacity-50" :disabled="form.processing">
                Submit appeal
            </button>
        </form>

        <div v-else-if="appeal.can_respond && appeal.open_appeal" class="mt-4 space-y-3 rounded-xl border border-white bg-white/80 p-4">
            <p class="text-sm font-bold text-slate-900">{{ appeal.open_appeal.filed_by }} filed an appeal</p>
            <p class="whitespace-pre-wrap text-sm text-slate-700">{{ appeal.open_appeal.unfair_reason }}</p>
            <form @submit.prevent="submitResponse">
                <label class="text-xs font-bold">Your response (optional)</label>
                <textarea v-model="responseForm.counter_response" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-base" />
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="submit" class="rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="responseBusy">Submit response</button>
                    <button type="button" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-black uppercase text-slate-700" :disabled="responseBusy" @click="skipResponse">Skip</button>
                </div>
            </form>
        </div>

        <div v-else-if="appeal.open_appeal" class="mt-4 rounded-xl border border-slate-100 bg-white/80 p-4 text-sm text-slate-700">
            <p class="font-bold">Appeal status: {{ appeal.open_appeal.status }}</p>
            <p class="mt-2 whitespace-pre-wrap">{{ appeal.open_appeal.unfair_reason }}</p>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    appeal: { type: Object, required: true },
    resolutionOptions: { type: Array, default: () => [] },
    urls: { type: Object, required: true },
});

const togetherOptions = computed(() => props.resolutionOptions.filter((o) => o.path === 'together'));
const selectedOption = computed(() => togetherOptions.value.find((o) => o.value === form.proposed_option) ?? null);

const form = useForm({
    unfair_reason: '',
    proposed_option: '',
    client_share_percent: 50,
    terms_note: '',
});

const responseForm = useForm({ counter_response: '' });
const responseBusy = ref(false);

function submitAppeal() {
    form.post(props.urls.appeal_store, { preserveScroll: true });
}

function submitResponse() {
    if (!props.appeal.open_appeal?.respond_url) return;
    responseBusy.value = true;
    router.post(props.appeal.open_appeal.respond_url, { counter_response: responseForm.counter_response }, {
        preserveScroll: true,
        onFinish: () => { responseBusy.value = false; },
    });
}

function skipResponse() {
    submitResponse();
}

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}
</script>
