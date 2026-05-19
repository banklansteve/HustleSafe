<template>
    <AppShell>
        <Head title="Verifications" />

        <div class="mx-auto max-w-2xl">
            <h1 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl">
                Trust &amp; verification
            </h1>
            <p class="mt-3 text-base font-semibold leading-relaxed text-slate-600">
                Upload scans or photos of your documents. Each file needs a short label (e.g. “NIN slip front”). For high-budget
                quests, complete the selfie + ID check once your government ID is approved — one clear photo with your face and ID
                visible beats a long video for reviewers and loads faster for you.
            </p>

            <form
                id="verification-submit"
                class="mt-8 space-y-5 rounded-[1.5rem] border border-slate-100 bg-white p-6 shadow-sm sm:p-8"
                @submit.prevent="submit"
            >
                <div>
                    <InputLabel for="category" value="What are you submitting?" />
                    <UiSelect
                        id="category"
                        v-model="form.category"
                        class="mt-2"
                        :options="verificationCategoryOptions"
                        placeholder="Choose type"
                        :invalid="!!form.errors.category"
                    />
                    <InputError class="mt-2" :message="form.errors.category" />
                </div>

                <template v-if="form.category === 'live_presence'">
                    <div class="rounded-2xl border border-primary-100 bg-primary-50/60 p-4 text-sm font-semibold leading-relaxed text-primary-950 ring-1 ring-primary-100">
                        <p class="font-black uppercase tracking-wide text-primary-900">
                            Selfie + ID (recommended)
                        </p>
                        <p class="mt-2">
                            Use good lighting. Your face and the ID photo page must be readable in one image (JPEG/PNG/WebP, max
                            15&nbsp;MB). This unlocks proposals on high-budget quests after your document ID is approved.
                        </p>
                    </div>
                    <div>
                        <InputLabel for="live_photo" value="Upload photo" />
                        <input
                            id="live_photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-2 block w-full text-sm font-semibold text-slate-800 file:mr-4 file:rounded-xl file:border-0 file:bg-primary-600 file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-white"
                            @change="onLivePhoto"
                        />
                        <InputError class="mt-2" :message="form.errors.live_photo" />
                    </div>
                </template>

                <template v-else>
                    <div v-if="['identity', 'identity_address'].includes(form.category)" class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="id_type" value="ID type" />
                            <UiSelect
                                id="id_type"
                                v-model="form.id_type"
                                class="mt-2"
                                :options="form.category === 'identity_address' ? photoIdTypeOptions : idTypeOptions"
                                placeholder="Select ID type"
                                :invalid="!!form.errors.id_type"
                            />
                            <InputError class="mt-2" :message="form.errors.id_type" />
                        </div>
                        <div>
                            <InputLabel for="identifier_number" value="NIN / passport / licence number" />
                            <TextInput
                                id="identifier_number"
                                v-model="form.identifier_number"
                                type="text"
                                class="mt-2"
                                placeholder="As shown on the document"
                                autocomplete="off"
                            />
                            <InputError class="mt-2" :message="form.errors.identifier_number" />
                        </div>
                    </div>

                    <div v-if="['nin', 'bvn', 'tin'].includes(form.category)">
                        <InputLabel for="identifier_number" :value="form.category.toUpperCase() + ' number'" />
                        <TextInput
                            id="identifier_number"
                            v-model="form.identifier_number"
                            type="text"
                            class="mt-2"
                            placeholder="Enter the number exactly as issued"
                            autocomplete="off"
                        />
                        <InputError class="mt-2" :message="form.errors.identifier_number" />
                    </div>

                    <div v-if="form.category === 'identity_address'">
                        <InputLabel for="address_document_type" value="Proof of address document type" />
                        <UiSelect
                            id="address_document_type"
                            v-model="form.address_document_type"
                            class="mt-2"
                            :options="addressDocumentOptions"
                            placeholder="Select document type"
                            :invalid="!!form.errors.address_document_type"
                        />
                        <InputError class="mt-2" :message="form.errors.address_document_type" />
                    </div>

                    <div v-if="form.category === 'cac'" class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="cac_number" value="RC number" />
                            <TextInput id="cac_number" v-model="form.cac_number" type="text" class="mt-2" placeholder="e.g. RC1234567" />
                            <InputError class="mt-2" :message="form.errors.cac_number" />
                        </div>
                        <div>
                            <InputLabel for="registered_business_name" value="Registered business name" />
                            <TextInput id="registered_business_name" v-model="form.registered_business_name" type="text" class="mt-2" placeholder="As shown on CAC certificate" />
                            <InputError class="mt-2" :message="form.errors.registered_business_name" />
                        </div>
                    </div>

                    <div v-if="['qualification', 'professional_certificate'].includes(form.category)">
                        <InputLabel for="freelancer_credential_id" value="Credential ID (from your profile)" />
                        <TextInput
                            id="freelancer_credential_id"
                            v-model="form.freelancer_credential_id"
                            type="text"
                            class="mt-2"
                            placeholder="Numeric ID from your dashboard credentials list"
                        />
                        <InputError class="mt-2" :message="form.errors.freelancer_credential_id" />
                    </div>

                    <div v-if="!['nin', 'bvn', 'tin'].includes(form.category)" class="space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <InputLabel value="Documents (name + file for each)" />
                            <button
                                type="button"
                                class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 shadow-sm hover:border-primary-200"
                                @click="addDocRow"
                            >
                                + Add another
                            </button>
                        </div>
                        <p class="text-xs font-semibold text-slate-500">
                            Example labels: “NIN slip front”, “Passport data page”, “Utility bill March 2026”.
                        </p>
                        <div
                            v-for="(row, idx) in docRows"
                            :key="idx"
                            class="flex flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50/80 p-3 ring-1 ring-slate-100 sm:flex-row sm:items-end"
                        >
                            <div class="min-w-0 flex-1">
                                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Document name</label>
                                <TextInput v-model="row.label" type="text" class="mt-1" placeholder="Label for this file" />
                            </div>
                            <div class="w-full sm:w-auto sm:min-w-[12rem]">
                                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">File</label>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    class="mt-1 block w-full text-xs font-semibold text-slate-800 file:mr-2 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                                    @change="(e) => onDocFile(idx, e)"
                                />
                            </div>
                            <button
                                v-if="docRows.length > 1"
                                type="button"
                                class="rounded-lg px-2 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50"
                                @click="removeDocRow(idx)"
                            >
                                Remove
                            </button>
                        </div>
                        <InputError :message="form.errors.document_files" />
                        <InputError :message="form.errors.document_labels" />
                    </div>
                </template>

                <PrimaryButton
                    class="w-full justify-center rounded-2xl py-4 text-base font-bold normal-case"
                    :class="{ 'opacity-60': form.processing }"
                    :disabled="form.processing"
                >
                    <ReLoader4Line v-if="form.processing" class="mr-2 inline h-5 w-5 animate-spin" aria-hidden="true" />
                    Submit for review
                </PrimaryButton>
            </form>

            <div class="mt-10">
                <h2 class="font-display text-lg font-bold text-slate-900">
                    Your submissions
                </h2>
                <ListSearchSortBar
                    v-if="items.length"
                    v-model:search="search"
                    v-model:sort="sortKey"
                    class="mt-4"
                    placeholder="Search category, status, notes…"
                    :sort-options="sortOptions"
                />
                <ul class="mt-4 space-y-3">
                    <li
                        v-for="item in displayItems"
                        :key="item.id"
                        class="rounded-2xl border border-slate-100 bg-white px-4 py-4 shadow-sm"
                    >
                        <p class="text-sm font-bold uppercase tracking-wide text-primary-700">
                            {{ item.category_label || item.category }}
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
                    <li v-else-if="items.length && !displayItems.length" class="text-sm font-semibold text-slate-600">
                        No submissions match your search.
                    </li>
                </ul>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import ListSearchSortBar from '@/Components/Ui/ListSearchSortBar.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const verificationCategoryOptions = [
    { value: 'nin', label: 'NIN Verification' },
    { value: 'bvn', label: 'BVN Verification' },
    { value: 'identity_address', label: 'Identity & address verification' },
    {
        value: 'identity',
        label: "Government-issued ID (NIN slip, passport, driver's licence)",
    },
    {
        value: 'address',
        label: 'Proof of address (utility bill, bank statement — last 3 months)',
    },
    {
        value: 'cac',
        label: 'CAC verification (RC number + certificate)',
    },
    {
        value: 'tin',
        label: 'TIN verification',
    },
    {
        value: 'professional_certificate',
        label: 'Professional certificate / membership card',
    },
    {
        value: 'portfolio_review',
        label: 'Portfolio review verification',
    },
    {
        value: 'qualification',
        label: 'Legacy certificate / professional qualification',
    },
    {
        value: 'live_presence',
        label: 'Selfie + ID (high-value quest unlock)',
    },
];

const idTypeOptions = [
    { value: 'nin', label: 'NIN' },
    { value: 'passport', label: 'Passport' },
    { value: 'drivers_licence', label: "Driver's licence" },
];
const photoIdTypeOptions = [
    { value: 'passport', label: 'National Passport' },
    { value: 'drivers_licence', label: "Driver's licence" },
    { value: 'voters_card', label: "Voter's Card" },
];
const addressDocumentOptions = [
    { value: 'utility_bill', label: 'Utility bill' },
    { value: 'tenancy_agreement', label: 'Tenancy agreement' },
    { value: 'bank_statement', label: 'Bank statement' },
];

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const search = ref('');
const sortKey = ref('submitted_desc');

const sortOptions = [
    { value: 'submitted_desc', label: 'Newest first' },
    { value: 'submitted_asc', label: 'Oldest first' },
    { value: 'category_asc', label: 'Category A–Z' },
    { value: 'status_asc', label: 'Status A–Z' },
];

const displayItems = computed(() => {
    const q = search.value.trim().toLowerCase();
    let rows = [...props.items];
    if (q) {
        rows = rows.filter((row) => {
            const blob = [row.category, row.category_label, row.status, row.credential_title].filter(Boolean).join(' ').toLowerCase();

            return blob.includes(q);
        });
    }
    const sk = sortKey.value;
    rows.sort((a, b) => {
        if (sk === 'category_asc') {
            return String(a.category_label || a.category || '').localeCompare(String(b.category_label || b.category || ''));
        }
        if (sk === 'status_asc') {
            return String(a.status || '').localeCompare(String(b.status || ''));
        }
        if (sk === 'submitted_asc') {
            return ts(a.submitted_at) - ts(b.submitted_at);
        }

        return ts(b.submitted_at) - ts(a.submitted_at);
    });

    return rows;
});

function ts(iso) {
    if (!iso) {
        return 0;
    }
    const n = Date.parse(iso);

    return Number.isFinite(n) ? n : 0;
}

const docRows = ref([{ label: '', file: null }]);
const livePhoto = ref(null);

const form = useForm({
    category: 'identity',
    id_type: 'nin',
    address_document_type: 'utility_bill',
    identifier_number: '',
    cac_number: '',
    registered_business_name: '',
    freelancer_credential_id: '',
    document_labels: [],
    document_files: [],
    live_photo: null,
});

watch(
    () => form.category,
    (cat) => {
        docRows.value = [{ label: '', file: null }];
        livePhoto.value = null;
        form.clearErrors();
        if (!['identity', 'identity_address'].includes(cat)) {
            form.id_type = 'nin';
        }
        if (!['nin', 'bvn', 'tin', 'identity'].includes(cat)) {
            form.identifier_number = '';
        }
        if (!['qualification', 'professional_certificate'].includes(cat)) {
            form.freelancer_credential_id = '';
        }
        if (cat !== 'cac') {
            form.cac_number = '';
            form.registered_business_name = '';
        }
    },
);

function addDocRow() {
    docRows.value.push({ label: '', file: null });
}

function removeDocRow(i) {
    if (docRows.value.length <= 1) {
        return;
    }
    docRows.value.splice(i, 1);
}

function onDocFile(idx, e) {
    const f = e.target?.files?.[0];
    docRows.value[idx].file = f || null;
}

function onLivePhoto(e) {
    const f = e.target?.files?.[0];
    livePhoto.value = f || null;
}

function submit() {
    const visitOpts = { forceFormData: true, preserveScroll: true, timeout: 180000 };

    if (form.category === 'live_presence') {
        if (!livePhoto.value) {
            form.setError('live_photo', 'Choose a clear photo before submitting.');
            return;
        }
        form
            .transform(() => ({
                category: 'live_presence',
                live_photo: livePhoto.value,
            }))
            .post(route('verifications.store'), visitOpts);

        return;
    }

    if (['nin', 'bvn', 'tin'].includes(form.category)) {
        form
            .transform(() => ({
                category: form.category,
                identifier_number: form.identifier_number,
            }))
            .post(route('verifications.store'), visitOpts);

        return;
    }

    const pairs = docRows.value.filter((r) => r.file && r.label.trim());
    if (!pairs.length) {
        form.setError('document_files', 'Add at least one document with a label and a file.');
        return;
    }
    form.document_labels = pairs.map((r) => r.label.trim());
    form.document_files = pairs.map((r) => r.file);
    if (['qualification', 'professional_certificate'].includes(form.category) && form.freelancer_credential_id !== '') {
        form.freelancer_credential_id = Number(form.freelancer_credential_id);
    }
    form.post(route('verifications.store'), visitOpts);
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
