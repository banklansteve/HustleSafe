<template>
    <OperationsShell title="Category health dashboard" subtitle="30-day operational signals per category — volume, fill rates, and dispute trends.">
        <p class="mb-4 text-sm font-semibold text-slate-600">Read-only analytics to spot supply issues, dispute spikes, or suspicious low-budget clusters.</p>
        <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Quests</th>
                        <th class="px-4 py-3">Proposals / quest</th>
                        <th class="px-4 py-3">Fill %</th>
                        <th class="px-4 py-3">Dispute %</th>
                        <th class="px-4 py-3">Avg value</th>
                        <th class="px-4 py-3">Flag</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in items" :key="row.id" class="border-b border-slate-50">
                        <td class="px-4 py-3 font-semibold text-slate-950">{{ row.name }}</td>
                        <td class="px-4 py-3">{{ row.quest_volume }}</td>
                        <td class="px-4 py-3">{{ row.proposal_rate }}</td>
                        <td class="px-4 py-3">{{ row.fill_rate }}%</td>
                        <td class="px-4 py-3">{{ row.dispute_rate }}%</td>
                        <td class="px-4 py-3">₦{{ (row.avg_contract_value_minor / 100).toLocaleString() }}</td>
                        <td class="px-4 py-3"><span v-if="row.health_flag" class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase text-amber-900">{{ row.health_flag.replace('_', ' ') }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </OperationsShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';

const items = ref([]);

onMounted(async () => {
    const { data } = await window.axios.get(route('operations.api.category-health.dashboard'));
    items.value = data.items ?? [];
});
</script>
