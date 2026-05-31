<template>
    <div class="rounded-2xl border border-rose-200/90 bg-gradient-to-br from-rose-50/90 via-white to-amber-50/40 p-5 ring-1 ring-rose-100 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-rose-900">
                    Trust & safety
                </h2>
                <p class="mt-1 max-w-prose text-xs font-semibold leading-relaxed text-rose-900/85">
                    {{ subtitle }}
                </p>
            </div>
            <button
                v-if="!formOpen"
                type="button"
                class="rounded-full bg-rose-700 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-white shadow-sm hover:bg-rose-800"
                @click="formOpen = true"
            >
                Report concern
            </button>
        </div>

        <form v-if="formOpen" class="mt-5 space-y-4" @submit.prevent="submit">
            <div
                v-if="contextSummary"
                class="rounded-xl border border-rose-100 bg-white/90 px-4 py-3 text-xs font-semibold leading-relaxed text-rose-950 ring-1 ring-rose-50"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-rose-800">Reporting</p>
                <p class="mt-1">{{ contextSummary }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-1">
                    <label class="text-[11px] font-black uppercase tracking-wide text-rose-900">Reason</label>
                    <UiSelect
                        v-model="form.reason"
                        class="w-full"
                        :options="reasonOptions"
                        placeholder="Reason"
                        :invalid="!!form.errors.reason"
                    />
                    <InputError :message="form.errors.reason" />
                </div>
                <div class="space-y-1">
                    <label class="text-[11px] font-black uppercase tracking-wide text-rose-900">Priority</label>
                    <UiSelect
                        v-model="form.severity"
                        class="w-full"
                        :options="severityOptions"
                        placeholder="Priority"
                        :invalid="!!form.errors.severity"
                    />
                    <InputError :message="form.errors.severity" />
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-[11px] font-black uppercase tracking-wide text-rose-900">What happened?</label>
                <textarea
                    v-model="form.details"
                    rows="4"
                    class="w-full rounded-xl border-rose-200 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"
                    placeholder="Timestamps, screenshots description, or anything that helps us triage quickly."
                />
                <InputError :message="form.errors.details" />
            </div>
            <label class="flex cursor-pointer items-start gap-3 text-xs font-semibold text-rose-950">
                <input v-model="form.confirm_accuracy" type="checkbox" class="mt-0.5 rounded border-rose-300 text-rose-600 focus:ring-rose-500" />
                <span>I confirm this report is submitted in good faith. False reports may affect my account.</span>
            </label>
            <p v-if="accuracyError" class="text-xs font-semibold text-rose-800">{{ accuracyError }}</p>
            <div class="flex flex-wrap gap-2">
                <button
                    type="submit"
                    class="rounded-full bg-rose-700 px-6 py-3 text-xs font-black uppercase tracking-wide text-white shadow-md hover:bg-rose-800 disabled:opacity-50"
                    :disabled="form.processing || !form.confirm_accuracy"
                >
                    Submit to trust & safety
                </button>
                <button
                    type="button"
                    class="rounded-full border border-rose-200 bg-white px-6 py-3 text-xs font-black uppercase tracking-wide text-rose-900 hover:bg-rose-50"
                    @click="formOpen = false"
                >
                    Cancel
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const reasonOptions = [
    { value: 'spam', label: 'Spam or noise' },
    { value: 'harassment', label: 'Harassment' },
    { value: 'fraud', label: 'Fraud / scam signals' },
    { value: 'off_platform_contact', label: 'Off-platform contact' },
    { value: 'misleading', label: 'Misleading quote or scope' },
    { value: 'copyright', label: 'Copyright / IP issue' },
    { value: 'duplicate_listing', label: 'Duplicate listing' },
    { value: 'unsafe_scope', label: 'Unsafe or illegal scope' },
    { value: 'payment_dispute', label: 'Payment / escrow concern' },
    { value: 'other', label: 'Other' },
];

const severityOptions = [
    { value: 'low', label: 'Low' },
    { value: 'standard', label: 'Standard' },
    { value: 'high', label: 'High' },
    { value: 'urgent', label: 'Urgent safety' },
];

const props = defineProps({
    actionUrl: { type: String, required: true },
    subtitle: { type: String, default: 'Fast triage — include concrete detail so our team can act quickly.' },
    context: { type: Object, default: null },
});

const formOpen = ref(false);
const accuracyError = ref('');

const contextSummary = computed(() => {
    const c = props.context;
    if (!c) {
        return '';
    }
    if (c.type === 'proposal') {
        const parts = [
            `Proposal #${c.proposal_id}`,
            c.quest_title ? `on quest “${c.quest_title}”` : null,
            c.freelancer_name ? `by ${c.freelancer_name}` : null,
        ].filter(Boolean);

        return parts.join(' · ');
    }
    if (c.type === 'quest') {
        return c.quest_title ? `Quest “${c.quest_title}”${c.reference_code ? ` (${c.reference_code})` : ''}` : 'This quest listing';
    }

    return c.label || '';
});

const form = useForm({
    reason: 'misleading',
    severity: 'standard',
    details: '',
    confirm_accuracy: false,
});

watch(
    () => form.confirm_accuracy,
    () => {
        accuracyError.value = '';
    },
);

function submit() {
    if (!form.confirm_accuracy) {
        accuracyError.value = 'Please confirm accuracy before submitting.';

        return;
    }
    form.transform((d) => ({
        reason: d.reason,
        severity: d.severity,
        details: d.details?.trim() || null,
    })).post(props.actionUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('details');
            form.confirm_accuracy = false;
            formOpen.value = false;
        },
    });
}
</script>
