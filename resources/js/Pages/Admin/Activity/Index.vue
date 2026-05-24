<template>
    <AdminShell
        title="Audit log"
        subtitle="Immutable-style trail of super-admin actions (role grants, CSV imports, support note batches)."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between" :class="shell.card">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <p class="text-sm font-semibold" :class="shell.cardMuted">Full export for compliance packs (latest first, chunked on the server).</p>
                <select
                    :value="filters.action"
                    class="rounded-xl border px-3 py-2 text-xs font-bold"
                    :class="shell.input"
                    @change="applyActionFilter"
                >
                    <option value="">All actions</option>
                    <option value="quest_completion">Quest completion & releases</option>
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="route('admin.activity.export')" class="inline-flex rounded-xl bg-teal-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 hover:bg-teal-400">
                    Export CSV
                </a>
                <span class="inline-flex items-center rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-slate-600 line-through opacity-60" title="Logs are append-only">
                    Import
                </span>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border" :class="shell.card">
            <table class="min-w-full text-left text-sm" :class="['divide-y', shell.tableDivide]">
                <thead class="text-[10px] font-black uppercase tracking-[0.18em]" :class="shell.tableHead">
                    <tr>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Actor</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Subject</th>
                    </tr>
                </thead>
                <tbody :class="['divide-y', shell.tableDivide]">
                    <tr v-for="log in logs.data" :key="log.id" class="transition hover:bg-slate-50 dark:hover:bg-white/5">
                        <td class="px-4 py-3 text-xs" :class="shell.cardMuted">{{ formatDate(log.created_at) }}</td>
                        <td class="px-4 py-3" :class="shell.tableRow">{{ log.actor?.email ?? (log.properties?.actor_label === 'system' ? 'System' : '—') }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-primary-700 dark:text-teal-200">{{ log.action }}</td>
                        <td class="px-4 py-3 text-xs" :class="shell.cardMuted">
                            <span v-if="log.subject_type">{{ shortType(log.subject_type) }}#{{ log.subject_id ?? '—' }}</span>
                            <span v-else>—</span>
                            <span v-if="log.properties?.quest_title" class="mt-1 block font-semibold text-slate-600 dark:text-slate-300">{{ log.properties.quest_title }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="logs.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in logs.links"
                :key="String(link.label) + (link.url || 'x')"
                :href="link.url || undefined"
                prefetch="false"
                class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                :class="[link.active ? 'bg-teal-500 text-slate-950' : 'border border-white/10 text-slate-200', !link.url ? 'pointer-events-none opacity-40' : '']"
                preserve-state
            >
                <span v-html="link.label" />
            </component>
        </nav>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { Link, router } from '@inertiajs/vue3';

const { shell } = useInjectedAdminTheme();

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, required: true },
});

function applyActionFilter(event) {
    router.get(
        route('admin.activity.index'),
        { action: event.target.value, per_page: props.filters.per_page },
        { preserveState: true, preserveScroll: true },
    );
}

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', { timeZone: 'Africa/Lagos' });
    } catch {
        return iso;
    }
}

function shortType(t) {
    if (!t || typeof t !== 'string') {
        return '';
    }
    const parts = t.split('\\');

    return parts[parts.length - 1] || t;
}
</script>
