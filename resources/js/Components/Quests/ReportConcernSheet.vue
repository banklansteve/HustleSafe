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
            <span class="rounded-full bg-rose-600/10 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-rose-900">
                Confidential
            </span>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
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
                <label class="text-[11px] font-black uppercase tracking-wide text-rose-900">Evidence link (optional)</label>
                <input
                    v-model="form.evidence_url"
                    type="url"
                    placeholder="https://…"
                    class="w-full rounded-xl border-rose-200 text-sm font-medium shadow-sm focus:border-rose-500 focus:ring-rose-500"
                />
                <InputError :message="form.errors.evidence_url" />
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
            <button
                type="submit"
                class="w-full rounded-full bg-rose-700 px-6 py-3 text-xs font-black uppercase tracking-wide text-white shadow-md hover:bg-rose-800 disabled:opacity-50 sm:w-auto"
                :disabled="form.processing || !form.confirm_accuracy"
            >
                Submit to trust & safety
            </button>
        </form>
    </div>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

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
});

const accuracyError = ref('');

const form = useForm({
    reason: 'misleading',
    severity: 'standard',
    evidence_url: '',
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
        evidence_url: d.evidence_url?.trim() || null,
        details: d.details?.trim() || null,
    })).post(props.actionUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('details', 'evidence_url');
            form.confirm_accuracy = false;
        },
    });
}
</script>
