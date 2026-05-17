<template>
    <AdminShell :title="`${definition.label} detail`" :subtitle="definition.description">
        <AdminPanel eyebrow="Record" :title="recordTitle">
            <template #actions>
                <Link
                    :href="route('admin.management.index', { resource: resource_key })"
                    class="rounded-xl border px-4 py-2 text-xs font-black uppercase tracking-wide"
                    :class="shell.btnGhost"
                >
                    Back to list
                </Link>
                <button
                    v-if="definition.editable"
                    type="button"
                    class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide"
                    :class="shell.btnPrimary"
                    @click="openEdit"
                >
                    Edit details
                </button>
            </template>

            <dl class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-3">
                <div v-for="column in definition.detail_columns" :key="column" class="rounded-xl border p-3" :class="shell.card">
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">
                        {{ column.replace(/_/g, ' ') }}
                    </dt>
                    <dd class="mt-1 font-semibold" :class="shell.cardTitle">
                        <Link
                            v-if="record[column]?.href"
                            :href="record[column].href"
                            class="underline decoration-teal-400/50 underline-offset-2"
                            :class="shell.link"
                        >
                            {{ record[column].label }}
                        </Link>
                        <span v-else>{{ display(record[column]) }}</span>
                    </dd>
                </div>
            </dl>
        </AdminPanel>

        <AdminPanel v-if="files.length" eyebrow="Files" title="Related files">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <article v-for="file in files" :key="`${file.resource}-${file.id}`" class="overflow-hidden rounded-2xl border" :class="shell.card">
                    <button type="button" class="block aspect-video w-full bg-slate-900/10" @click="preview = file">
                        <img v-if="file.is_image" :src="file.url" :alt="file.name" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center px-4 text-center text-xs font-bold" :class="shell.cardMuted">
                            {{ file.mime_type || 'File' }}
                        </div>
                    </button>
                    <div class="space-y-2 p-3">
                        <p class="truncate text-sm font-bold" :class="shell.cardTitle">{{ file.name }}</p>
                        <div class="flex gap-2">
                            <a :href="file.url" target="_blank" rel="noreferrer" class="rounded-lg px-3 py-1.5 text-xs font-bold" :class="shell.btnGhost">
                                Open
                            </a>
                            <button type="button" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white" @click="removeFile(file)">
                                Remove
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </AdminPanel>

        <AdminPanel v-else eyebrow="Files" title="Related files">
            <p class="text-sm font-semibold" :class="shell.cardMuted">No files are attached to this record.</p>
        </AdminPanel>

        <Teleport to="body">
            <div
                v-if="preview"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/80 p-4"
                role="dialog"
                aria-modal="true"
                @click.self="preview = null"
            >
                <div class="max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b px-4 py-3" :class="shell.tableDivide">
                        <p class="truncate text-sm font-bold">{{ preview.name }}</p>
                        <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-bold" :class="shell.btnGhost" @click="preview = null">
                            Close
                        </button>
                    </div>
                    <img v-if="preview.is_image" :src="preview.url" :alt="preview.name" class="max-h-[78vh] w-full object-contain" />
                    <iframe v-else :src="preview.url" class="h-[78vh] w-full" />
                </div>
            </div>
        </Teleport>

        <AdminSlideOver :open="editOpen" title="Edit record" eyebrow="Management" @close="editOpen = false">
            <form class="max-h-[min(70vh,32rem)] space-y-3 overflow-y-auto pr-1" @submit.prevent="submitEdit">
                <AdminManagementField
                    v-for="field in definition.edit_fields"
                    :key="field"
                    v-model="editFields[field]"
                    :field="field"
                    :schema="definition.fields[field]"
                />
                <label class="block">
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-600">Audit reason (required)</span>
                    <textarea
                        v-model="editReason"
                        rows="3"
                        required
                        minlength="8"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
                        :class="shell.input"
                    />
                </label>
                <button type="submit" class="w-full rounded-xl px-4 py-2 text-sm font-black uppercase" :class="shell.btnPrimary">
                    Save changes
                </button>
            </form>
        </AdminSlideOver>
    </AdminShell>
</template>

<script setup>
import AdminManagementField from '@/Components/Admin/AdminManagementField.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, toRaw } from 'vue';

const props = defineProps({
    resource_key: { type: String, required: true },
    definition: { type: Object, required: true },
    record: { type: Object, required: true },
    files: { type: Array, default: () => [] },
});

const { shell } = useInjectedAdminTheme();
const preview = ref(null);
const editOpen = ref(false);
const editReason = ref('');
const editFields = reactive({});

const recordTitle = computed(() => props.record.title || props.record.name || props.record.email || `Record #${props.record.id}`);

function display(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    if (typeof value === 'object' && value.label) {
        return value.label;
    }

    return value;
}

function removeFile(file) {
    if (!file.resource) {
        return;
    }

    router.delete(route('admin.management.destroy', { resource: file.resource, record: file.id }), {
        preserveScroll: true,
    });
}

function openEdit() {
    editReason.value = '';
    (props.definition.edit_fields || []).forEach((field) => {
        const type = props.definition.fields[field]?.type;
        let value = props.record._edit?.[field] ?? props.record[field];
        if (type === 'boolean') {
            value = value === true || value === 1 || value === '1' || value === 'true';
        }
        if (type === 'key_value') {
            value = Array.isArray(value) ? value : [];
        }
        editFields[field] = value ?? (type === 'boolean' ? false : '');
    });
    editOpen.value = true;
}

function submitEdit() {
    router.patch(
        route('admin.management.update', { resource: props.resource_key, record: props.record.id }),
        {
            ...toRaw(editFields),
            audit_reason: editReason.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
            },
        },
    );
}
</script>
