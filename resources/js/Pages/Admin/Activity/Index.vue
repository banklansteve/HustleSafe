<template>
    <AdminShell
        title="Audit log"
        subtitle="Immutable-style trail of super-admin actions (role grants, CSV imports, support note batches)."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/40 p-4 ring-1 ring-white/5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-400">Full export for compliance packs (latest first, chunked on the server).</p>
            <div class="flex flex-wrap gap-2">
                <a :href="route('admin.activity.export')" class="inline-flex rounded-xl bg-teal-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 hover:bg-teal-400">
                    Export CSV
                </a>
                <span class="inline-flex items-center rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-slate-600 line-through opacity-60" title="Logs are append-only">
                    Import
                </span>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-white/10 bg-slate-900/40 ring-1 ring-white/5">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-slate-900/80 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Actor</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Subject</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="log in logs.data" :key="log.id" class="hover:bg-white/5">
                        <td class="px-4 py-3 text-xs text-slate-400">{{ formatDate(log.created_at) }}</td>
                        <td class="px-4 py-3 text-slate-200">{{ log.actor?.email ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-teal-200">{{ log.action }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            <span v-if="log.subject_type">{{ shortType(log.subject_type) }}#{{ log.subject_id ?? '—' }}</span>
                            <span v-else>—</span>
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
import { Link } from '@inertiajs/vue3';

defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, required: true },
});

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
