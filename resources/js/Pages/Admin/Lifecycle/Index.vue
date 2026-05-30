<template>
    <AdminShell title="User lifecycle analytics" subtitle="Cohort timing, onboarding drop-offs, and retention — where the platform loses users before churn compounds.">
        <div class="grid gap-4 lg:grid-cols-2">
            <AdminPanel eyebrow="Clients" title="Time to first Quest">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase text-slate-400">Median</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ fmtDays(analytics.client_first_quest.median_days) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase text-slate-400">P75</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ fmtDays(analytics.client_first_quest.p75_days) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase text-slate-400">Publish rate</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ analytics.client_first_quest.conversion_rate_pct }}%</p>
                    </div>
                </div>
                <ul class="mt-4 space-y-1 text-xs text-slate-600">
                    <li v-for="c in analytics.client_first_quest.cohorts" :key="c.month" class="flex justify-between gap-2">
                        <span>{{ c.month }}</span>
                        <span class="font-bold">{{ c.sample ? `${c.median_days ?? '—'}d (${c.sample})` : '—' }}</span>
                    </li>
                </ul>
            </AdminPanel>

            <AdminPanel eyebrow="Freelancers" title="Time to first contract">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase text-slate-400">Median</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ fmtDays(analytics.freelancer_first_contract.median_days) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase text-slate-400">P75</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ fmtDays(analytics.freelancer_first_contract.p75_days) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase text-slate-400">Win rate</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ analytics.freelancer_first_contract.conversion_rate_pct }}%</p>
                    </div>
                </div>
                <ul class="mt-4 space-y-1 text-xs text-slate-600">
                    <li v-for="c in analytics.freelancer_first_contract.cohorts" :key="c.month" class="flex justify-between gap-2">
                        <span>{{ c.month }}</span>
                        <span class="font-bold">{{ c.sample ? `${c.median_days ?? '—'}d (${c.sample})` : '—' }}</span>
                    </li>
                </ul>
            </AdminPanel>
        </div>

        <AdminPanel eyebrow="90-day window" title="Onboarding funnel drop-offs" class="mt-4">
            <div class="space-y-2">
                <div v-for="step in analytics.onboarding_funnel" :key="step.step" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div>
                        <p class="text-sm font-black text-slate-900">{{ step.step }}</p>
                        <p v-if="step.drop_pct !== null" class="text-xs font-semibold text-rose-700">{{ step.drop_pct }}% drop from previous step</p>
                    </div>
                    <p class="text-xl font-black tabular-nums text-slate-900">{{ step.count }}</p>
                </div>
            </div>
        </AdminPanel>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <AdminPanel eyebrow="Retention" title="Clients — active at day N">
                <ul class="space-y-2">
                    <li v-for="r in analytics.retention.clients" :key="r.days" class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                        <span class="font-semibold text-slate-700">{{ r.days }} days</span>
                        <span class="font-black text-slate-900">{{ r.rate_pct }}% <span class="text-xs font-semibold text-slate-500">(n={{ r.cohort_size }})</span></span>
                    </li>
                </ul>
            </AdminPanel>
            <AdminPanel eyebrow="Retention" title="Freelancers — active at day N">
                <ul class="space-y-2">
                    <li v-for="r in analytics.retention.freelancers" :key="r.days" class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                        <span class="font-semibold text-slate-700">{{ r.days }} days</span>
                        <span class="font-black text-slate-900">{{ r.rate_pct }}% <span class="text-xs font-semibold text-slate-500">(n={{ r.cohort_size }})</span></span>
                    </li>
                </ul>
            </AdminPanel>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminShell from '@/Layouts/AdminShell.vue';

defineProps({
    analytics: { type: Object, required: true },
});

function fmtDays(v) {
    return v == null ? '—' : `${v}d`;
}
</script>
