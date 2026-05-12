<template>
    <AppShell>
        <Head title="Verifications" />

        <div class="mx-auto max-w-2xl">
            <h1 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl">
                Trust &amp; verification
            </h1>
            <p class="mt-3 text-base font-semibold leading-relaxed text-slate-600">
                Submit ID, address proof, or qualification documents. Approved checks lift your trust score on HustleSafe.
            </p>

            <form class="mt-8 space-y-5 rounded-[1.5rem] border border-slate-100 bg-white p-6 shadow-sm sm:p-8" @submit.prevent="submit">
                <div>
                    <InputLabel for="category" value="What are you submitting?" />
                    <select
                        id="category"
                        v-model="form.category"
                        class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="identity">
                            Government-issued ID (NIN slip, passport, driver&apos;s licence)
                        </option>
                        <option value="address">
                            Proof of address (utility bill, bank statement — last 3 months)
                        </option>
                        <option value="qualification">
                            Certificate / professional qualification
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.category" />
                </div>

                <div v-if="form.category === 'qualification'">
                    <InputLabel for="freelancer_credential_id" value="Credential ID (from your profile)" />
                    <TextInput
                        id="freelancer_credential_id"
                        v-model="form.freelancer_credential_id"
                        type="text"
                        class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        placeholder="Numeric ID from your dashboard credentials list"
                    />
                    <InputError class="mt-2" :message="form.errors.freelancer_credential_id" />
                </div>

                <div>
                    <InputLabel for="doc_note" value="Document URLs or paths (optional)" />
                    <textarea
                        id="doc_note"
                        v-model="docNote"
                        rows="3"
                        class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        placeholder="Paste secure storage links or admin reference — upload UI comes next."
                    />
                </div>

                <PrimaryButton
                    class="w-full justify-center rounded-2xl py-4 text-base font-bold normal-case"
                    :class="{ 'opacity-60': form.processing }"
                    :disabled="form.processing"
                >
                    Submit for review
                </PrimaryButton>
            </form>

            <div class="mt-10">
                <h2 class="font-display text-lg font-bold text-slate-900">
                    Your submissions
                </h2>
                <ul class="mt-4 space-y-3">
                    <li
                        v-for="item in items"
                        :key="item.id"
                        class="rounded-2xl border border-slate-100 bg-white px-4 py-4 shadow-sm"
                    >
                        <p class="text-sm font-bold uppercase tracking-wide text-primary-700">
                            {{ item.category }}
                        </p>
                        <p class="mt-1 text-base font-bold text-slate-900">
                            {{ item.status.replaceAll('_', ' ') }}
                        </p>
                        <p v-if="item.credential_title" class="mt-1 text-sm font-semibold text-slate-600">
                            {{ item.credential_title }}
                        </p>
                        <p class="mt-2 text-sm font-medium text-slate-500">
                            {{ formatWhen(item.submitted_at) }}
                        </p>
                    </li>
                    <li v-if="items.length === 0" class="text-base font-semibold text-slate-600">
                        No submissions yet.
                    </li>
                </ul>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const docNote = ref('');

const form = useForm({
    category: 'identity',
    freelancer_credential_id: '',
    document_paths: [],
    metadata: {},
});

watch(docNote, (v) => {
    const lines = v.split('\n').map((s) => s.trim()).filter(Boolean);
    form.document_paths = lines;
});

function submit() {
    form.transform((data) => ({
        ...data,
        freelancer_credential_id: data.category === 'qualification' && data.freelancer_credential_id !== ''
            ? Number(data.freelancer_credential_id)
            : null,
    })).post(route('verifications.store'));
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', { timeZone: 'Africa/Lagos' });
    } catch {
        return iso;
    }
}
</script>
