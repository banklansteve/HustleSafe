<template>
    <AdminShell title="Quest boost report" subtitle="Promotional investment tracking — theoretical revenue if boosts were paid.">
        <div class="space-y-6">
            <div class="flex flex-wrap gap-2">
                <Link :href="route('admin.quest-boosts.index')" class="rounded-xl border px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost">← Boosts panel</Link>
                <a :href="exportUrl" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white dark:bg-white dark:text-slate-900">Export CSV</a>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase" :class="shell.label">Total boosts</p>
                    <p class="mt-2 text-2xl font-black">{{ summary.total_boosts }}</p>
                </div>
                <div class="rounded-2xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase" :class="shell.label">Theoretical investment</p>
                    <p class="mt-2 text-2xl font-black">{{ summary.total_investment_display }}</p>
                </div>
                <div v-for="(count, tier) in summary.by_tier" :key="tier" class="rounded-2xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase" :class="shell.label">{{ tier }}</p>
                    <p class="mt-2 text-2xl font-black">{{ count }}</p>
                </div>
            </section>

            <section class="rounded-3xl border overflow-hidden" :class="shell.card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b text-[10px] font-black uppercase" :class="shell.label">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Quest</th>
                                <th class="px-4 py-3">Client</th>
                                <th class="px-4 py-3">Tier</th>
                                <th class="px-4 py-3">Cost</th>
                                <th class="px-4 py-3">Admin</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in line_items" :key="row.reference" class="border-b">
                                <td class="px-4 py-3 font-mono text-xs">{{ row.reference }}</td>
                                <td class="px-4 py-3">{{ row.quest_title }}</td>
                                <td class="px-4 py-3">{{ row.client_name }}</td>
                                <td class="px-4 py-3">{{ row.tier }}</td>
                                <td class="px-4 py-3">{{ row.planned_cost_display }}</td>
                                <td class="px-4 py-3">{{ row.granting_admin }}</td>
                                <td class="px-4 py-3">{{ row.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { useAdminShell } from '@/Composables/useAdminShell';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    line_items: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { shell } = useAdminShell();

const exportUrl = computed(() => route('admin.quest-boosts.report.export', props.filters));
</script>
