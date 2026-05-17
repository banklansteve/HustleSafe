<template>
    <AdminShell
        title="Record management"
        subtitle="Pick a resource from the sidebar Data registry. Search, create, edit, or delete with a mandatory audit reason."
    >
        <div class="space-y-2">
            <AdminPanel :title="definition.label" :description="definition.description">
                <template #actions>
                    <button
                        v-if="definition.creatable"
                        type="button"
                        class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide"
                        :class="shell.btnPrimary"
                        @click="openCreate"
                    >
                        New record
                    </button>
                </template>

                <p class="text-sm font-semibold" :class="shell.cardMuted">
                    Use the table search below to filter instantly in your browser without a page reload.
                </p>
            </AdminPanel>

            <AdminDataTable
                :rows="tableRows"
                :columns="columns"
                search-placeholder="Search this list instantly…"
            >
                <template #actions="{ row }">
                    <Link
                        :href="route('admin.management.show', { resource: resource_key, record: row.id })"
                        class="mr-2 text-xs font-bold text-teal-600 underline dark:text-teal-300"
                    >
                        View
                    </Link>
                    <Link
                        v-if="resource_key === 'conversation_threads'"
                        :href="route('admin.management.conversation_threads.show', row.id)"
                        class="mr-2 text-xs font-bold text-teal-600 underline dark:text-teal-300"
                    >
                        Open thread
                    </Link>
                    <template v-if="resource_key === 'users'">
                        <Link
                            :href="route('admin.management.users.activity', row.id)"
                            class="mr-2 text-xs font-bold text-teal-600 underline dark:text-teal-300"
                        >
                            Activity
                        </Link>
                        <button
                            type="button"
                            class="mr-2 text-xs font-bold underline"
                            :class="isUserSuspended(row) ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300'"
                            @click="openSuspend(row)"
                        >
                            {{ isUserSuspended(row) ? 'Reinstate' : 'Suspend' }}
                        </button>
                    </template>
                    <button
                        v-if="definition.editable"
                        type="button"
                        class="mr-2 text-xs font-bold text-teal-600 underline dark:text-teal-300"
                        @click="openEdit(row)"
                    >
                        Edit
                    </button>
                    <button
                        v-if="definition.deletable"
                        type="button"
                        class="text-xs font-bold text-rose-600 underline dark:text-rose-300"
                        @click="openDelete(row)"
                    >
                        Delete
                    </button>
                </template>
            </AdminDataTable>

            <nav v-if="records.links?.length > 3" class="flex flex-wrap justify-center gap-2">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="link in records.links"
                    :key="String(link.label) + (link.url || 'x')"
                    :href="link.url || undefined"
                    prefetch="false"
                    class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold"
                    :class="[link.active ? shell.btnPrimary : shell.btnGhost, !link.url ? 'pointer-events-none opacity-40' : '']"
                    preserve-state
                >
                    <span v-html="link.label" />
                </component>
            </nav>
        </div>

        <AdminSlideOver :open="createOpen" :title="`New ${definition.label}`" eyebrow="Create" @close="createOpen = false">
            <form class="max-h-[min(70vh,32rem)] space-y-3 overflow-y-auto pr-1" @submit.prevent="submitCreate">
                <AdminManagementField
                    v-for="field in definition.create_fields"
                    :key="field"
                    v-model="createForm[field]"
                    :field="field"
                    :schema="definition.fields[field]"
                />
                <div
                    v-if="isPrivilegedUserCreate"
                    class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-100"
                >
                    <p class="font-black">Admin account security check</p>
                    <p class="mt-1 text-xs font-semibold">
                        Creating admin or super admin access is sensitive. Confirm your password and acknowledge that this action will be audited and reported to your email.
                    </p>
                    <label class="mt-3 block">
                        <span class="text-[10px] font-black uppercase tracking-wider">Your current password</span>
                        <input
                            v-model="createForm.current_password"
                            type="password"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
                            :class="shell.input"
                            autocomplete="current-password"
                            :required="isPrivilegedUserCreate"
                        />
                    </label>
                    <label class="mt-3 flex items-start gap-2 text-xs font-bold">
                        <input
                            v-model="createForm.admin_creation_confirmation"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-amber-300 text-primary-600"
                            :required="isPrivilegedUserCreate"
                        />
                        <span>I confirm this admin account is authorised, necessary, and should trigger audit notifications.</span>
                    </label>
                </div>
                <label class="block">
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-600">Audit reason (required)</span>
                    <textarea
                        v-model="createForm.audit_reason"
                        rows="3"
                        required
                        minlength="8"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
                        :class="shell.input"
                        placeholder="Why is this record being created?"
                    />
                </label>
                <button type="submit" class="w-full rounded-xl px-4 py-2 text-sm font-black uppercase" :class="shell.btnPrimary">
                    Create record
                </button>
            </form>
        </AdminSlideOver>

        <AdminSlideOver :open="editOpen" title="Edit record" eyebrow="Management" @close="editOpen = false">
            <form v-if="editing" class="max-h-[min(70vh,32rem)] space-y-3 overflow-y-auto pr-1" @submit.prevent="submitEdit">
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
                        placeholder="Why is this change being made?"
                    />
                </label>
                <button type="submit" class="w-full rounded-xl px-4 py-2 text-sm font-black uppercase" :class="shell.btnPrimary">
                    Save changes
                </button>
            </form>
        </AdminSlideOver>

        <AdminSlideOver :open="deleteOpen" title="Delete record" eyebrow="Destructive" @close="deleteOpen = false">
            <form v-if="deleting" class="space-y-3" @submit.prevent="submitDelete">
                <p class="text-sm font-semibold" :class="shell.cardMuted">Record #{{ deleting.id }} will be permanently removed.</p>
                <label class="block">
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-600">Audit reason (required)</span>
                    <textarea
                        v-model="deleteForm.audit_reason"
                        rows="3"
                        required
                        minlength="8"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
                        :class="shell.input"
                    />
                </label>
                <button type="submit" class="w-full rounded-xl bg-rose-600 px-4 py-2 text-sm font-black uppercase text-white" :disabled="deleteForm.processing">
                    Confirm delete
                </button>
            </form>
        </AdminSlideOver>

        <AdminSlideOver :open="suspendOpen" :title="suspendTarget?.is_suspended ? 'Reinstate user' : 'Suspend user'" eyebrow="Moderation" @close="suspendOpen = false">
            <form v-if="suspendTarget" class="space-y-3" @submit.prevent="submitSuspend">
                <p class="text-sm font-semibold" :class="shell.cardMuted">
                    {{ suspendTarget.name }} · {{ suspendTarget.email }}
                </p>
                <label class="block">
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-600">Audit reason (required)</span>
                    <textarea
                        v-model="suspendForm.audit_reason"
                        rows="3"
                        required
                        minlength="8"
                        class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
                        :class="shell.input"
                    />
                </label>
                <button type="submit" class="w-full rounded-xl px-4 py-2 text-sm font-black uppercase text-white" :class="suspendTarget.is_suspended ? 'bg-emerald-600' : 'bg-amber-600'">
                    {{ suspendTarget.is_suspended ? 'Reinstate account' : 'Suspend account' }}
                </button>
            </form>
        </AdminSlideOver>
    </AdminShell>
</template>

<script setup>
import AdminDataTable from '@/Components/Admin/AdminDataTable.vue';
import AdminManagementField from '@/Components/Admin/AdminManagementField.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { adminActionColumn, buildAdminColumns } from '@/composables/useAdminTableColumns';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, toRaw } from 'vue';

const props = defineProps({
    resource_key: { type: String, required: true },
    resource_groups: { type: Array, required: true },
    definition: { type: Object, required: true },
    records: { type: Object, required: true },
    filters: { type: Object, default: () => ({ q: '', per_page: 20 }) },
});

const { shell } = useInjectedAdminTheme();

const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const suspendOpen = ref(false);
const editing = ref(null);
const deleting = ref(null);
const suspendTarget = ref(null);

const editFields = reactive({});
const editReason = ref('');
const createForm = reactive({ audit_reason: '' });
const deleteForm = useForm({ audit_reason: '' });
const suspendForm = useForm({ suspend: true, audit_reason: '' });

const tableRows = computed(() =>
    (props.records.data || []).map((row) => {
        const flat = { ...row };
        Object.keys(row).forEach((key) => {
            if (key.startsWith('_rel_') && row[key]) {
                const rel = row[key];
                if (rel.email) {
                    flat[key] = rel.email;
                } else if (rel.title) {
                    flat[key] = rel.title;
                } else if (rel.name) {
                    flat[key] = rel.name;
                }
            }
            if (key === 'is_suspended') {
                flat[key] = row[key] ? 'Suspended' : 'Active';
            }
        });

        return flat;
    }),
);

const columns = computed(() => [
    ...buildAdminColumns(
        props.definition.list_columns.map((col) => ({
            accessorKey: col,
            header: col.replace(/_/g, ' '),
        })),
    ),
    adminActionColumn(),
]);

const isPrivilegedUserCreate = computed(() => {
    if (props.resource_key !== 'users') {
        return false;
    }

    const roleField = props.definition.fields?.role_id;
    const selectedRole = (roleField?.options || []).find((option) => String(option.value) === String(createForm.role_id));
    const label = String(selectedRole?.label || '').toLowerCase();

    return createForm.account_type === 'admin' || label.includes('administrator') || label.includes('super');
});

function isUserSuspended(row) {
    const raw = (props.records.data || []).find((r) => r.id === row.id);

    return Boolean(raw?.is_suspended);
}

function managementParams(extra = {}) {
    return {
        resource: props.resource_key,
        per_page: props.filters.per_page,
        ...extra,
    };
}

function openCreate() {
    props.definition.create_fields.forEach((field) => {
        const type = props.definition.fields[field]?.type;
        createForm[field] = type === 'boolean' ? false : '';
    });
    createForm.audit_reason = '';
    createForm.current_password = '';
    createForm.admin_creation_confirmation = false;
    createOpen.value = true;
}

function recordById(id) {
    return (props.records.data || []).find((r) => r.id === id);
}

function openEdit(row) {
    const raw = recordById(row.id) ?? row;
    editing.value = raw;
    editReason.value = '';
    props.definition.edit_fields.forEach((field) => {
        const type = props.definition.fields[field]?.type;
        let value = raw._edit?.[field] ?? raw[field];
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

function openDelete(row) {
    deleting.value = row;
    deleteForm.reset();
    deleteOpen.value = true;
}

function openSuspend(row) {
    const raw = recordById(row.id) ?? row;
    suspendTarget.value = raw;
    suspendForm.reset();
    suspendForm.suspend = !raw.is_suspended;
    suspendOpen.value = true;
}

function submitCreate() {
    const payload = { audit_reason: createForm.audit_reason };
    props.definition.create_fields.forEach((field) => {
        payload[field] = createForm[field];
    });
    if (props.resource_key === 'users') {
        payload.current_password = createForm.current_password;
        payload.admin_creation_confirmation = createForm.admin_creation_confirmation;
    }

    router.post(route('admin.management.store', { resource: props.resource_key }), payload, {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
        },
    });
}

function submitEdit() {
    router.patch(
        route('admin.management.update', { resource: props.resource_key, record: editing.value.id }),
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

function submitDelete() {
    deleteForm.delete(route('admin.management.destroy', { resource: props.resource_key, record: deleting.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
        },
    });
}

function submitSuspend() {
    suspendForm.post(route('admin.management.users.suspend', suspendTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            suspendOpen.value = false;
        },
    });
}
</script>
