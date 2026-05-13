<template>
    <AppShell>
        <Head :title="pageTitle" />

        <div class="mx-auto w-full max-w-2xl space-y-8">
            <div>
                <Link
                    :href="route('account.credentials.index')"
                    class="text-xs font-bold uppercase tracking-wide text-primary-700 hover:text-primary-800"
                >
                    ← All credentials
                </Link>
                <p
                    class="mt-3 inline-flex rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-primary-900"
                >
                    {{ typeLabel }}
                </p>
                <h1 class="font-display mt-2 text-2xl font-black tracking-tight text-slate-900">
                    {{ mode === 'create' ? `Add ${typeLabel}` : `Edit ${typeLabel}` }}
                </h1>
                <p class="mt-2 text-sm font-medium text-slate-600">
                    {{ typeHelp }}
                </p>
            </div>

            <form class="space-y-5 rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8" @submit.prevent="submit">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Title</label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        :placeholder="titlePlaceholder"
                    />
                    <InputError class="mt-1" :message="form.errors.title" />
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Issuing authority / insurer</label>
                    <input
                        v-model="form.issuing_authority"
                        type="text"
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        :placeholder="authorityPlaceholder"
                    />
                    <InputError class="mt-1" :message="form.errors.issuing_authority" />
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Reference / policy number</label>
                    <input
                        v-model="form.reference_number"
                        type="text"
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                    <InputError class="mt-1" :message="form.errors.reference_number" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Issued on</label>
                        <input
                            v-model="form.issued_on"
                            type="date"
                            class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        />
                        <InputError class="mt-1" :message="form.errors.issued_on" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Expires on</label>
                        <input
                            v-model="form.expires_on"
                            type="date"
                            class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        />
                        <InputError class="mt-1" :message="form.errors.expires_on" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Coverage / details</label>
                    <textarea
                        v-model="form.coverage_summary"
                        rows="4"
                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        placeholder="Sum insured, territorial scope, membership grade, etc."
                    />
                    <InputError class="mt-1" :message="form.errors.coverage_summary" />
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Supporting document (PDF or image)</label>
                    <input
                        type="file"
                        accept=".pdf,image/*"
                        class="mt-1 block w-full text-sm font-medium text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white"
                        @input="form.document = $event.target.files[0]"
                    />
                    <p v-if="credential?.document_url" class="mt-2 text-xs font-semibold text-slate-600">
                        Current file:
                        <a :href="credential.document_url" class="text-primary-700 underline" target="_blank" rel="noopener noreferrer">Open</a>
                    </p>
                    <InputError class="mt-1" :message="form.errors.document" />
                </div>
                <div class="flex justify-end gap-3">
                    <Link
                        :href="route('account.credentials.index')"
                        class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-800 hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ mode === 'create' ? 'Save' : 'Update' }}
                    </button>
                </div>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    credential: { type: Object, default: null },
    credentialType: { type: String, required: true },
    typeLabel: { type: String, required: true },
});

const form = useForm({
    title: props.credential?.title ?? '',
    issuing_authority: props.credential?.issuing_authority ?? '',
    reference_number: props.credential?.reference_number ?? '',
    issued_on: props.credential?.issued_on ?? '',
    expires_on: props.credential?.expires_on ?? '',
    coverage_summary: props.credential?.coverage_summary ?? '',
    document: null,
});

const pageTitle = computed(() =>
    props.mode === 'create' ? `Add ${props.typeLabel} · HustleSafe` : `Edit ${props.typeLabel} · HustleSafe`,
);

const typeHelp = computed(() => {
    switch (props.credentialType) {
        case 'insurance':
            return 'Add each policy separately (you can save several). Use the schedule title and NAICOM-regulated insurer or broker details where applicable.';
        case 'professional_licence':
            return 'Council or regulator registrations (e.g. COREN, ARCON, MDCN). Add one licence per entry if you hold more than one.';
        case 'qualification':
            return 'Degrees, diplomas, and formal training. Add each qualification as its own entry.';
        case 'certification':
            return 'Vendor certs, cloud badges, and short courses. Add each certification separately.';
        default:
            return 'Use official names as they appear on your certificate or policy schedule.';
    }
});

const titlePlaceholder = computed(() => {
    switch (props.credentialType) {
        case 'insurance':
            return 'e.g. Professional indemnity · ₦10m limit';
        case 'professional_licence':
            return 'e.g. COREN — Registered Engineer';
        case 'qualification':
            return 'e.g. B.Eng Civil Engineering — UNILAG';
        case 'certification':
            return 'e.g. AWS Solutions Architect — Associate';
        default:
            return '';
    }
});

const authorityPlaceholder = computed(() => {
    switch (props.credentialType) {
        case 'insurance':
            return 'Insurer or broker (NAICOM-regulated where applicable)';
        case 'professional_licence':
            return 'Council or issuing body';
        default:
            return 'Institution or issuer';
    }
});

function submit() {
    if (props.mode === 'create') {
        form.post(route('account.credentials.store', { type: props.credentialType }), {
            forceFormData: true,
            preserveScroll: true,
        });

        return;
    }
    form.put(route('account.credentials.update', { freelancerCredential: props.credential.id }), {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>
