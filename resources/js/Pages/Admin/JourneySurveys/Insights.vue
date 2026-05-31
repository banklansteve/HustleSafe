<template>
    <AdminShell
        title="Survey insights"
        subtitle="Journey experience feedback — client completion, freelancer payout, and marketplace quality signals."
    >
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AdminKpiTile label="Submitted" :value="panel.summary.total_submitted" hint="All completed surveys" />
            <AdminKpiTile label="Client completed" :value="panel.summary.client_completed" />
            <AdminKpiTile label="Freelancer paid" :value="panel.summary.freelancer_awarded" />
            <AdminKpiTile label="Not selected" :value="panel.summary.freelancer_rejected" hint="Marketplace quality cohort" />
        </div>

        <AdminPanel eyebrow="Operational" title="Dual low-score quests">
            <p class="mb-4 text-xs font-semibold text-slate-600">
                Quests where both client proposal quality and freelancer payment experience scored poorly — worth investigating.
            </p>
            <div v-if="panel.dual_low_quests.length" class="space-y-2">
                <div
                    v-for="row in panel.dual_low_quests"
                    :key="row.quest_id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-rose-100 bg-rose-50/60 px-4 py-3"
                >
                    <span class="text-sm font-black text-slate-900">{{ row.quest_title }}</span>
                    <span class="text-xs font-bold text-rose-800">
                        Client {{ row.client_score }} · Freelancer {{ row.freelancer_score }}
                    </span>
                </div>
            </div>
            <p v-else class="text-sm font-semibold text-slate-500">No dual low-score quests flagged yet.</p>
        </AdminPanel>

        <div class="grid gap-6 xl:grid-cols-2">
            <AdminPanel eyebrow="Trend" title="Freelancer NPS (awarded cohort)">
                <div class="space-y-2">
                    <div
                        v-for="row in panel.nps_trend"
                        :key="row.month"
                        class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-2"
                    >
                        <span class="text-xs font-bold text-slate-600">{{ row.month }}</span>
                        <span class="text-sm font-black text-slate-900">
                            {{ row.nps !== null ? `${row.nps}%` : '—' }}
                            <span class="text-xs font-semibold text-slate-400">({{ row.responses }})</span>
                        </span>
                    </div>
                </div>
            </AdminPanel>

            <AdminPanel eyebrow="Marketplace" title="Not-selected freelancer signals">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase text-slate-500">Responses</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ panel.rejected_cohort_summary.responses }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase text-slate-500">Brief adequacy</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ panel.rejected_cohort_summary.avg_brief_adequacy ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase text-slate-500">Platform fairness</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ panel.rejected_cohort_summary.avg_platform_fairness ?? '—' }}</p>
                    </div>
                </div>
            </AdminPanel>
        </div>

        <AdminPanel eyebrow="Categories" title="Score breakdown by quest category">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-xs font-black uppercase text-slate-500">
                            <th class="py-2 pr-4">Category</th>
                            <th class="py-2 pr-4">Client</th>
                            <th class="py-2 pr-4">Paid FL</th>
                            <th class="py-2">Not selected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in panel.by_category" :key="row.category" class="border-b border-slate-50">
                            <td class="py-2 pr-4 font-semibold text-slate-800">{{ row.category }}</td>
                            <td class="py-2 pr-4">{{ row.client_avg ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ row.awarded_avg ?? '—' }}</td>
                            <td class="py-2">{{ row.rejected_avg ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </AdminPanel>

        <AdminPanel eyebrow="Free text" title="Recent open feedback">
            <div class="mb-4 flex flex-wrap gap-2">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search free-text responses…"
                    class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold"
                    @keyup.enter="runSearch"
                />
                <button
                    type="button"
                    class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white"
                    @click="runSearch"
                >
                    Search
                </button>
            </div>
            <div class="space-y-3">
                <article
                    v-for="item in freeTextItems"
                    :key="`${item.id}-${item.question_key}`"
                    class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                >
                    <p class="text-xs font-bold uppercase text-slate-500">{{ item.cohort }} · {{ item.quest_title }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-800">{{ item.text }}</p>
                </article>
            </div>
            <Link
                :href="route('admin.journey-surveys.index')"
                class="mt-6 inline-flex text-sm font-black text-primary-700 hover:text-primary-900"
            >
                View all survey responses by user →
            </Link>
        </AdminPanel>
    </AdminShell>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminKpiTile from '@/Components/Admin/AdminKpiTile.vue';

const props = defineProps({
    panel: { type: Object, required: true },
});

const search = ref('');
const freeTextItems = ref(props.panel.recent_free_text ?? []);

async function runSearch() {
    const params = new URLSearchParams();
    if (search.value) {
        params.set('q', search.value);
    }
    const res = await fetch(`${route('admin.journey-surveys.free-text')}?${params.toString()}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (res.ok) {
        const data = await res.json();
        freeTextItems.value = data.items ?? [];
    }
}
</script>
