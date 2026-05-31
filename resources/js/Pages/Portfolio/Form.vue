<template>
    <AppShell>
        <Head :title="mode === 'create' ? 'New portfolio' : 'Edit portfolio'" />

        <div class="mx-auto max-w-3xl">
            <Link
                :href="mode === 'create' ? route('portfolio.manage') : route('portfolio.show', portfolio.slug)"
                class="text-xs font-bold uppercase tracking-wide text-primary-700 hover:text-primary-800"
            >
                ← Back
            </Link>
            <h1 class="font-display mt-3 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                {{ mode === 'create' ? 'New portfolio piece' : 'Edit portfolio' }}
            </h1>
            <p class="mt-2 text-sm font-medium text-slate-600">
                Drag files into the drop zone, set categories, and publish when it feels ready.
            </p>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Title</label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="mt-1 w-full rounded-lg border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        required
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs font-bold text-rose-600">
                        {{ form.errors.title }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="6"
                        class="mt-1 w-full rounded-lg border-slate-200 text-sm font-medium leading-relaxed shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        required
                    />
                    <p v-if="form.errors.description" class="mt-1 text-xs font-bold text-rose-600">
                        {{ form.errors.description }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Category</label>
                        <UiSelect
                            v-model="form.category_id"
                            class="mt-1"
                            :options="categorySelectOptions"
                            placeholder="Choose…"
                            :invalid="!!form.errors.category_id"
                            @update:model-value="form.subcategory_id = null"
                        />
                        <p v-if="form.errors.category_id" class="mt-1 text-xs font-bold text-rose-600">
                            {{ form.errors.category_id }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Subcategory</label>
                        <UiSelect
                            v-model="form.subcategory_id"
                            class="mt-1"
                            :options="subcategorySelectOptions"
                            placeholder="Optional"
                            :invalid="!!form.errors.subcategory_id"
                        />
                        <p v-if="form.errors.subcategory_id" class="mt-1 text-xs font-bold text-rose-600">
                            {{ form.errors.subcategory_id }}
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Linked quest (optional)</label>
                    <UiSelect
                        v-model="form.quest_id"
                        class="mt-1"
                        :options="questLinkSelectOptions"
                        placeholder="No quest link"
                    />
                    <p class="mt-2 text-xs font-medium text-slate-500">
                        Pulls in the client review for this showcase when available.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Start date</label>
                        <PremiumDatePicker
                            v-model="form.started_at"
                            class="mt-1"
                            placeholder="dd/mm/yyyy"
                        />
                    </div>
                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Completed</label>
                        <PremiumDatePicker
                            v-model="form.completed_at"
                            class="mt-1"
                            placeholder="dd/mm/yyyy"
                            :min="form.started_at || ''"
                        />
                    </div>
                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Project cost (₦)</label>
                        <input
                            v-model="projectCostNaira"
                            type="number"
                            min="0"
                            step="1"
                            class="mt-1 w-full rounded-lg border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Optional"
                        />
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Visibility</label>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-100 has-[:checked]:border-primary-300 has-[:checked]:bg-primary-50">
                            <input v-model="form.status" type="radio" value="draft" class="text-primary-600 focus:ring-primary-500" />
                            Draft (only me)
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-100 has-[:checked]:border-primary-300 has-[:checked]:bg-primary-50">
                            <input v-model="form.status" type="radio" value="published" class="text-primary-600 focus:ring-primary-500" />
                            Published (gallery)
                        </label>
                    </div>
                </div>

                <div
                    class="rounded-xl border-2 border-dashed p-6 transition"
                    :class="dragOver ? 'border-primary-400 bg-primary-50/50' : 'border-slate-200 bg-white'"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDrop"
                >
                    <input ref="fileInput" type="file" class="hidden" multiple accept="image/*,video/*,.pdf" @change="onPick" />
                    <div class="text-center">
                        <p class="text-sm font-bold text-slate-800">
                            Drop images, short videos, or PDFs
                        </p>
                        <p class="mt-1 text-xs font-medium text-slate-500">
                            Up to 12 files · 10MB each
                        </p>
                        <button
                            type="button"
                            class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-800"
                            @click="fileInput?.click()"
                        >
                            Browse files
                        </button>
                    </div>
                </div>

                <div v-if="existingFiles.length" class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Current files
                    </p>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        <li
                            v-for="f in existingFiles"
                            :key="'ex-' + f.id"
                            class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/80 p-2"
                        >
                            <img v-if="f.is_image" :src="f.url" :alt="f.original_name" class="h-14 w-14 rounded-md object-cover" />
                            <div v-else class="flex h-14 w-14 items-center justify-center rounded-md bg-slate-200 text-[10px] font-bold text-slate-600">
                                file
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-bold text-slate-800">
                                    {{ f.original_name }}
                                </p>
                                <button
                                    type="button"
                                    class="mt-1 text-[11px] font-bold text-rose-600 hover:text-rose-700"
                                    @click="toggleRemoveExisting(f.id)"
                                >
                                    {{ removeIds.includes(f.id) ? 'Undo remove' : 'Remove' }}
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>

                <div v-if="pendingPreviews.length" class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        New uploads
                    </p>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        <li
                            v-for="(pv, idx) in pendingPreviews"
                            :key="pv.key"
                            class="flex items-center gap-3 rounded-lg border border-primary-100 bg-primary-50/40 p-2"
                        >
                            <img v-if="pv.isImage" :src="pv.url" alt="" class="h-14 w-14 rounded-md object-cover" />
                            <div v-else class="flex h-14 w-14 items-center justify-center rounded-md bg-white text-[10px] font-bold text-slate-600 ring-1 ring-slate-100">
                                new
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-bold text-slate-800">
                                    {{ pv.name }}
                                </p>
                                <button type="button" class="mt-1 text-[11px] font-bold text-rose-600" @click="removePending(idx)">
                                    Remove
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-6 py-3 text-sm font-bold text-white shadow-md transition hover:bg-primary-800 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <ReLoader4Line v-if="form.processing" class="h-5 w-5 animate-spin" />
                        {{ mode === 'create' ? 'Save portfolio' : 'Update portfolio' }}
                    </button>
                    <Link
                        :href="mode === 'create' ? route('portfolio.manage') : route('portfolio.show', portfolio.slug)"
                        class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import PremiumDatePicker from '@/Components/Ui/PremiumDatePicker.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    portfolio: {
        type: Object,
        default: null,
    },
    categoryTree: {
        type: Array,
        required: true,
    },
    completedQuests: {
        type: Array,
        required: true,
    },
});

const fileInput = ref(null);
const dragOver = ref(false);
const pendingFiles = ref([]);
const pendingPreviews = ref([]);
const removeIds = ref([]);

const projectCostNaira = ref(
    props.portfolio?.project_cost_minor ? String(Math.round(Number(props.portfolio.project_cost_minor) / 100)) : '',
);

const form = useForm({
    title: props.portfolio?.title ?? '',
    description: props.portfolio?.description ?? '',
    category_id: props.portfolio?.category_id ?? null,
    subcategory_id: props.portfolio?.subcategory_id ?? null,
    quest_id: props.portfolio?.quest_id ?? null,
    started_at: props.portfolio?.started_at ?? '',
    completed_at: props.portfolio?.completed_at ?? '',
    status: props.portfolio?.status ?? 'draft',
    project_cost_minor: props.portfolio?.project_cost_minor ?? null,
    files: [],
    remove_file_ids: [],
});

const existingFiles = computed(() => props.portfolio?.files ?? []);

const subcategories = computed(() => {
    const row = props.categoryTree.find((c) => c.id === form.category_id);

    return row?.children ?? [];
});

const categorySelectOptions = computed(() =>
    props.categoryTree.map((c) => ({
        value: c.id,
        label: c.name,
    })),
);

const subcategorySelectOptions = computed(() => [
    { value: null, label: 'Optional' },
    ...subcategories.value.map((ch) => ({
        value: ch.id,
        label: ch.name,
    })),
]);

const questLinkSelectOptions = computed(() => [
    { value: null, label: 'No quest link' },
    ...props.completedQuests.map((q) => ({
        value: q.id,
        label: q.title,
    })),
]);

watch(
    () => form.category_id,
    () => {
        const ids = new Set(subcategories.value.map((c) => c.id));
        if (form.subcategory_id != null && !ids.has(form.subcategory_id)) {
            form.subcategory_id = null;
        }
    },
);

function onPick(e) {
    addFiles(e.target.files);
    e.target.value = '';
}

function onDrop(e) {
    dragOver.value = false;
    addFiles(e.dataTransfer.files);
}

function addFiles(fileList) {
    if (!fileList?.length) {
        return;
    }
    const next = [...pendingFiles.value];
    for (const f of fileList) {
        if (next.length >= 12) {
            break;
        }
        next.push(f);
    }
    pendingFiles.value = next;
    rebuildPreviews();
}

function rebuildPreviews() {
    pendingPreviews.value.forEach((p) => URL.revokeObjectURL(p.url));
    pendingPreviews.value = pendingFiles.value.map((f, i) => ({
        key: `${i}-${f.name}-${f.size}`,
        name: f.name,
        url: URL.createObjectURL(f),
        isImage: f.type.startsWith('image/'),
    }));
}

function removePending(idx) {
    const copy = [...pendingFiles.value];
    copy.splice(idx, 1);
    pendingFiles.value = copy;
    rebuildPreviews();
}

function toggleRemoveExisting(id) {
    const set = new Set(removeIds.value);
    if (set.has(id)) {
        set.delete(id);
    } else {
        set.add(id);
    }
    removeIds.value = [...set];
}

onUnmounted(() => {
    pendingPreviews.value.forEach((p) => URL.revokeObjectURL(p.url));
});

function submit() {
    form.remove_file_ids = [...removeIds.value];
    form.files = [...pendingFiles.value];
    form.project_cost_minor =
        projectCostNaira.value === '' || projectCostNaira.value == null
            ? null
            : Math.round(Number(projectCostNaira.value) * 100);

    if (props.mode === 'create') {
        form.post(route('portfolio.store'), { forceFormData: true });
    } else {
        form.post(route('portfolio.update', props.portfolio.slug), {
            forceFormData: true,
            _method: 'put',
        });
    }
}
</script>
