<template>
    <AdminShell
        title="Staff activity trail"
        subtitle="Every staff and super-admin action on user accounts — default view is today, with custom date range, staff filter, and sorting."
    >
        <div class="staff-digest-page">
            <section class="digest-hero">
                <div>
                    <p class="eyebrow">Super admin oversight</p>
                    <h2 class="mt-2 font-display text-2xl font-black tracking-tight text-slate-950 md:text-4xl">
                        Staff activity trail
                    </h2>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 md:text-base">
                        Verifications, support tickets, live-support sessions, conversation monitoring warnings, user sanctions,
                        escalations, and every other staff action recorded in the audit log.
                    </p>
                    <p v-if="range_label" class="mt-3 text-xs font-black uppercase tracking-wide text-slate-500">
                        Showing {{ range_label }}
                    </p>
                </div>

                <form class="digest-filter-card" @submit.prevent="applyFilters">
                    <label>
                        <span>Period</span>
                        <select v-model="form.range">
                            <option value="day">Today / single day</option>
                            <option value="custom">Custom date range</option>
                        </select>
                    </label>
                    <label v-if="form.range !== 'custom'">
                        <span>Date</span>
                        <AdminDateInput v-model="form.date" />
                    </label>
                    <template v-else>
                        <label>
                            <span>From</span>
                            <AdminDateInput v-model="form.date_from" />
                        </label>
                        <label>
                            <span>To</span>
                            <AdminDateInput v-model="form.date_to" />
                        </label>
                    </template>
                    <label>
                        <span>Staff member</span>
                        <select v-model="form.admin_id">
                            <option value="">All staff</option>
                            <option v-for="admin in admins" :key="admin.id" :value="admin.id">
                                {{ admin.name }} · {{ admin.email }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span>Sort</span>
                        <select v-model="form.sort">
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                        </select>
                    </label>
                    <button type="submit">Apply filters</button>
                </form>
            </section>

            <section class="grid gap-3 sm:grid-cols-2">
                <article v-for="card in summary" :key="card.key" class="metric-card" :class="`metric-${card.tone}`">
                    <p>{{ card.label }}</p>
                    <strong>{{ card.value }}</strong>
                </article>
            </section>

            <section class="digest-panel">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="eyebrow">Full audit trail</p>
                        <h3 class="section-title">Staff actions ({{ timeline.length }})</h3>
                    </div>
                    <Link :href="route('admin.activity.index')" class="raw-log-link">Open raw audit log</Link>
                </div>

                <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">When</th>
                                <th class="px-4 py-3">Staff member</th>
                                <th class="px-4 py-3">Action</th>
                                <th class="px-4 py-3">Subject</th>
                                <th class="px-4 py-3">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="item in timeline" :key="item.id" class="align-top hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-bold text-slate-700">{{ item.at_label }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-bold text-slate-950">{{ item.actor }}</p>
                                    <p class="text-xs text-slate-500">{{ item.actor_email }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="digest-pill" :class="toneClass(item.tone)">{{ item.title }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs font-bold text-slate-600">{{ item.subject || 'Platform' }}</td>
                                <td class="px-4 py-3 text-xs leading-5 text-slate-600">{{ item.summary }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!timeline.length" class="empty-state rounded-none border-0">
                        No staff actions were recorded for this filter.
                    </p>
                </div>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    filters: { type: Object, required: true },
    range_label: { type: String, default: '' },
    admins: { type: Array, default: () => [] },
    summary: { type: Array, default: () => [] },
    timeline: { type: Array, default: () => [] },
});

const form = reactive({
    date: props.filters.date,
    date_from: props.filters.date_from || props.filters.date,
    date_to: props.filters.date_to || props.filters.date,
    range: props.filters.range || 'day',
    admin_id: props.filters.admin_id || '',
    sort: props.filters.sort || 'newest',
});

function applyFilters() {
    const payload = {
        range: form.range,
        admin_id: form.admin_id || undefined,
        sort: form.sort,
    };

    if (form.range === 'custom') {
        payload.date_from = form.date_from;
        payload.date_to = form.date_to;
    } else {
        payload.date = form.date;
    }

    router.get(route('admin.activity.digest'), payload, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function toneClass(tone) {
    return {
        resolved: 'tone-resolved',
        pending: 'tone-pending',
        overdue: 'tone-overdue',
    }[tone] || 'tone-neutral';
}
</script>

<style scoped>
.staff-digest-page {
    @apply space-y-6 bg-slate-50 text-slate-950;
}

.digest-hero,
.digest-panel,
.metric-card {
    @apply rounded-[2rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/70;
}

.digest-hero {
    @apply grid gap-5 p-5 lg:grid-cols-[1fr_22rem] lg:items-start;
}

.digest-filter-card {
    @apply grid gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4;
}

.digest-filter-card label {
    @apply grid gap-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500;
}

.digest-filter-card input,
.digest-filter-card select,
.digest-filter-card button:not([type='submit']) {
    @apply min-h-11 rounded-2xl border border-slate-300 bg-white px-3 text-sm font-bold normal-case tracking-normal text-slate-950 outline-none ring-2 ring-transparent transition focus:border-blue-500 focus:ring-blue-100;
}

.digest-filter-card button,
.raw-log-link {
    @apply inline-flex min-h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 text-xs font-black uppercase tracking-wide text-white transition hover:bg-blue-800;
}

.eyebrow {
    @apply text-[10px] font-black uppercase tracking-[0.22em] text-blue-700;
}

.section-title {
    @apply font-display text-lg font-black tracking-tight text-slate-950;
}

.metric-card {
    @apply p-4;
}

.metric-card p {
    @apply text-[10px] font-black uppercase tracking-[0.2em] text-slate-500;
}

.metric-card strong {
    @apply mt-3 block font-display text-4xl font-black text-slate-950;
}

.metric-blue {
    @apply border-blue-200 bg-blue-50;
}

.metric-indigo {
    @apply border-indigo-200 bg-indigo-50;
}

.digest-panel {
    @apply p-5;
}

.digest-pill {
    @apply inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide;
}

.tone-resolved {
    @apply border-emerald-500 bg-emerald-100 text-emerald-900;
}

.tone-pending {
    @apply border-amber-500 bg-amber-100 text-amber-900;
}

.tone-overdue {
    @apply border-rose-500 bg-rose-100 text-rose-900;
}

.tone-neutral {
    @apply border-slate-400 bg-slate-100 text-slate-800;
}

.empty-state {
    @apply rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-bold text-slate-500;
}
</style>
