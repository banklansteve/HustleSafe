<template>
    <AppShell>
        <Head title="Open dispute · HustleSafe" />

        <div class="mx-auto max-w-3xl space-y-6">
            <BackChevronLink :href="route('quests.show', quest.route_key)" aria-label="Back to quest" />

            <header class="space-y-2">
                <h1 class="font-display text-2xl font-black tracking-tight text-slate-900">
                    Structured dispute · {{ quest.title }}
                </h1>
                <p class="text-sm font-semibold text-slate-600">
                    Minimum value ₦{{ (policy.minimum_disputed_amount_minor / 100).toLocaleString() }} · Self-resolution response
                    {{ policy.self_resolution_hours }}h · Formal window {{ policy.formal_ruling_hours }}h after escalation.
                    <a :href="workflow_doc_url" class="ml-1 font-black text-primary-800 underline underline-offset-2" target="_blank" rel="noopener noreferrer">Read workflow</a>
                </p>
            </header>

            <form class="space-y-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6" @submit.prevent="submit">
                <div>
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">Reason</label>
                    <select
                        v-model="form.reason"
                        required
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option v-for="r in reason_options" :key="r.value" :value="r.value">
                            {{ r.label }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.reason" />
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">Opening narrative (min 40 chars)</label>
                    <textarea
                        v-model="form.opening_summary"
                        required
                        rows="6"
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        placeholder="Timeline, what was agreed, what happened, what you need next."
                    />
                    <InputError class="mt-1" :message="form.errors.opening_summary" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wide text-slate-500">Evidence links (optional)</label>
                        <textarea
                            v-model="evidenceText"
                            rows="3"
                            class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="One URL per line"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wide text-slate-500">Requested platform outcome</label>
                        <select
                            v-model="form.structured_intake.requested_outcome"
                            class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        >
                            <option value="release_payment">Release payment</option>
                            <option value="rework">Rework / revision</option>
                            <option value="partial_refund">Partial refund</option>
                            <option value="full_refund">Full refund</option>
                            <option value="other">Other (explain in narrative)</option>
                        </select>
                    </div>
                </div>

                <div v-if="form.reason === 'silence_comms'">
                    <label class="block text-xs font-black uppercase tracking-wide text-slate-500">Days without meaningful reply</label>
                    <input
                        v-model.number="form.structured_intake.silence_days_observed"
                        type="number"
                        min="0"
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                    <InputError class="mt-1" :message="form.errors['structured_intake.silence_days_observed']" />
                </div>

                <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                    <input v-model="form.confirm_philosophy" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                    <span>I understand decisions lean on dated evidence, timers can escalate the file, and both sides see the same thread.</span>
                </label>
                <InputError class="mt-1" :message="form.errors.confirm_philosophy" />

                <div class="flex flex-wrap justify-end gap-2">
                    <Link
                        :href="route('quests.show', quest.route_key)"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-full bg-primary-700 px-5 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800 disabled:opacity-50"
                        :disabled="form.processing || !form.confirm_philosophy"
                    >
                        <ReLoader4Line v-if="form.processing" class="mr-2 h-4 w-4 shrink-0 animate-spin" aria-hidden="true" />
                        Open dispute file
                    </button>
                </div>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import InputError from '@/Components/InputError.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { ref, watch } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    offer_id: { type: Number, required: true },
    party: { type: String, required: true },
    reason_options: { type: Array, default: () => [] },
    philosophy: { type: Object, default: () => ({}) },
    policy: { type: Object, required: true },
    store_url: { type: String, required: true },
    workflow_doc_url: { type: String, default: '/docs/dispute-workflow.md' },
});

const evidenceText = ref('');

const form = useForm({
    reason: props.reason_options[0]?.value ?? '',
    opening_summary: '',
    structured_intake: {
        evidence_links: [],
        requested_outcome: 'rework',
        silence_days_observed: 0,
    },
    confirm_philosophy: false,
});

watch(
    () => props.reason_options,
    (opts) => {
        if (opts.length && !opts.some((o) => o.value === form.reason)) {
            form.reason = opts[0].value;
        }
    },
    { immediate: true },
);

function submit() {
    const links = evidenceText.value
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean);
    form
        .transform((data) => ({
            ...data,
            structured_intake: {
                ...data.structured_intake,
                evidence_links: links,
            },
        }))
        .post(props.store_url, { preserveScroll: true });
}
</script>
