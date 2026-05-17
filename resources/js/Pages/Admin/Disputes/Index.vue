<template>
    <AdminShell
        title="Disputes (registry)"
        subtitle="Operational list for triage. Party workflows continue in the main Disputes centre."
    >
        <AdminPanel title="Filters & export">
            <template #actions>
                <AdminQuickActions
                    :export-actions="[{ label: 'Export CSV', href: exportUrl }]"
                    :button-actions="[
                        {
                            key: 'member-ui',
                            label: 'Member UI',
                            variant: 'ghost',
                            onClick: () => router.visit(route('disputes.index')),
                        },
                    ]"
                />
            </template>
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="apply">
                <label class="block">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">Status</span>
                    <select v-model="form.status" class="mt-1 rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input">
                        <option value="">Any</option>
                        <option v-for="o in status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </label>
                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-black uppercase" :class="shell.btnPrimary">Apply</button>
            </form>
        </AdminPanel>

        <AdminDataTable :rows="tableRows" :columns="columns" search-placeholder="Filter disputes on this page…">
            <template #actions="{ row }">
                <Link :href="route('disputes.show', row.uuid)" class="text-xs font-bold text-teal-600 underline dark:text-teal-300">
                    Open
                </Link>
            </template>
        </AdminDataTable>

        <nav v-if="disputes.links?.length > 3" class="flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in disputes.links"
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
    </AdminShell>
</template>

<script setup>
import AdminDataTable from '@/Components/Admin/AdminDataTable.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminQuickActions from '@/Components/Admin/AdminQuickActions.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { adminActionColumn, buildAdminColumns } from '@/composables/useAdminTableColumns';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    disputes: { type: Object, required: true },
    filters: { type: Object, required: true },
    status_options: { type: Array, default: () => [] },
});

const { shell } = useInjectedAdminTheme();

const form = reactive({ status: props.filters.status ?? '' });

watch(
    () => props.filters,
    (f) => {
        form.status = f.status ?? '';
    },
    { deep: true },
);

const exportUrl = computed(() => route('admin.disputes.export', { status: form.status || undefined }));

const tableRows = computed(() =>
    (props.disputes.data || []).map((d) => ({
        ...d,
        status_label: String(d.status ?? '').replace(/_/g, ' '),
        quest_title: d.quest?.title ?? '—',
        opened_by_email: d.opened_by?.email ?? d.openedBy?.email ?? '—',
        created_label: formatDate(d.created_at),
    })),
);

const columns = buildAdminColumns([
    { accessorKey: 'id', header: 'ID' },
    { accessorKey: 'quest_title', header: 'Quest' },
    { accessorKey: 'status_label', header: 'Status' },
    { accessorKey: 'opened_by_email', header: 'Opened by' },
    { accessorKey: 'created_label', header: 'Created' },
]).concat([adminActionColumn()]);

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

function apply() {
    router.get(route('admin.disputes.index'), { status: form.status || undefined }, { preserveState: true, replace: true });
}
</script>
