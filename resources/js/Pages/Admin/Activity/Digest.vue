<template>
    <AdminShell
        title="Staff Activity Digest"
        subtitle="Daily top-level oversight of staff/admin footprints, resolutions, pending work, overdue items, messages, and operational changes."
    >
        <div class="staff-digest-page">
            <section class="digest-hero">
                <div>
                    <p class="eyebrow">Super admin oversight</p>
                    <h2 class="mt-2 font-display text-2xl font-black tracking-tight text-slate-950 md:text-4xl">
                        Daily admin command digest
                    </h2>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 md:text-base">
                        A clean executive view of what staff touched today: resolved cases, pending queues,
                        overdue obligations, dispute and verification outcomes, messages, notes, and every recorded footprint.
                    </p>
                </div>

                <form class="digest-filter-card" @submit.prevent="applyFilters">
                    <label>
                        <span>Date</span>
                        <input v-model="form.date" type="date" />
                    </label>
                    <label>
                        <span>Staff/admin</span>
                        <select v-model="form.admin_id">
                            <option value="">All admins</option>
                            <option v-for="admin in admins" :key="admin.id" :value="admin.id">
                                {{ admin.name }} · {{ admin.email }}
                            </option>
                        </select>
                    </label>
                    <button type="submit">Refresh digest</button>
                </form>
            </section>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in summary" :key="card.key" class="metric-card" :class="`metric-${card.tone}`">
                    <p>{{ card.label }}</p>
                    <strong>{{ card.value }}</strong>
                    <span>{{ insightFor(card.key) }}</span>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
                <div class="digest-panel">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="eyebrow">Staff performance</p>
                            <h3 class="section-title">Who did what today</h3>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">{{ staff.length }} staff accounts</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        <article v-for="member in staff" :key="member.id" class="staff-row">
                            <div class="flex min-w-0 items-center gap-3">
                                <img v-if="member.avatar_url" :src="member.avatar_url" :alt="member.name" class="h-11 w-11 rounded-2xl object-cover ring-2 ring-blue-100" />
                                <span v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-700 to-violet-700 text-sm font-black text-white">
                                    {{ initials(member.name) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-slate-950">{{ member.name }}</p>
                                    <p class="truncate text-xs font-semibold text-slate-500">{{ member.email }}</p>
                                </div>
                            </div>
                            <div class="staff-metrics">
                                <span><b>{{ member.activity_count }}</b> actions</span>
                                <span><b>{{ member.resolved_count }}</b> resolved</span>
                                <span><b>{{ member.message_count }}</b> messages</span>
                                <span><b>{{ member.update_count }}</b> updates</span>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="digest-panel">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="eyebrow">Needs attention</p>
                            <h3 class="section-title">Due and overdue work</h3>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-3">
                        <DigestItem
                            v-for="item in attention.tasks_due_today"
                            :key="item.id"
                            :item="item"
                        />
                        <DigestItem
                            v-for="item in attention.disputes_needing_ruling"
                            :key="item.id"
                            :item="item"
                        />
                        <p v-if="!attention.tasks_due_today.length && !attention.disputes_needing_ruling.length" class="empty-state">
                            Nothing urgent is due or overdue for this filter.
                        </p>
                    </div>
                </div>
            </section>

            <section class="digest-grid">
                <DigestPanel
                    v-for="panel in categoryPanels"
                    :key="panel.key"
                    :title="panel.label"
                    :items="panel.items"
                />
            </section>

            <section class="digest-panel">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="eyebrow">Every footprint</p>
                        <h3 class="section-title">Chronological staff/admin trail</h3>
                    </div>
                    <Link :href="route('admin.activity.index')" class="raw-log-link">Open raw audit log</Link>
                </div>

                <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Time</th>
                                <th class="px-4 py-3">Actor</th>
                                <th class="px-4 py-3">Footprint</th>
                                <th class="px-4 py-3">Subject</th>
                                <th class="px-4 py-3">Meta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="item in timeline" :key="item.id" class="align-top hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-bold text-slate-500">{{ timeOnly(item.at) }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-bold text-slate-950">{{ item.actor }}</p>
                                    <p class="text-xs text-slate-500">{{ item.actor_email || 'System footprint' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="digest-pill" :class="toneClass(item.tone)">{{ item.title }}</span>
                                    <p class="mt-2 max-w-xl text-xs leading-5 text-slate-600">{{ item.summary }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs font-bold text-slate-600">{{ item.subject || 'Platform' }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ item.meta || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!timeline.length" class="empty-state rounded-none border-0">
                        No staff/admin footprints were recorded for this filter.
                    </p>
                </div>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, defineComponent, h, reactive } from 'vue';

const props = defineProps({
    filters: { type: Object, required: true },
    admins: { type: Array, default: () => [] },
    summary: { type: Array, default: () => [] },
    categories: { type: Object, default: () => ({}) },
    staff: { type: Array, default: () => [] },
    timeline: { type: Array, default: () => [] },
    attention: { type: Object, default: () => ({ tasks_due_today: [], disputes_needing_ruling: [] }) },
});

const form = reactive({
    date: props.filters.date,
    admin_id: props.filters.admin_id || '',
});

const categoryPanels = computed(() => Object.entries(props.categories).map(([key, value]) => ({
    key,
    ...value,
})));

function applyFilters() {
    router.get(route('admin.activity.digest'), {
        date: form.date,
        admin_id: form.admin_id || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function insightFor(key) {
    return {
        daily_activity: 'Audit + live events',
        resolved: 'Closed or completed outcomes',
        pending: 'Open handoffs and queued work',
        overdue: 'Past due tasks or dispute SLAs',
        disputes: 'Dispute outcomes logged',
        verifications: 'KYC/trust reviews completed',
        messages: 'Comms, notices, broadcasts',
        updates: 'Changes, flags, notes, edits',
    }[key] || 'Daily signal';
}

function initials(name) {
    return String(name || '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function timeOnly(value) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-NG', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Africa/Lagos',
    }).format(new Date(value));
}

function toneClass(tone) {
    return {
        resolved: 'tone-resolved',
        green: 'tone-resolved',
        pending: 'tone-pending',
        amber: 'tone-pending',
        overdue: 'tone-overdue',
        red: 'tone-overdue',
        critical: 'tone-overdue',
        purple: 'tone-purple',
        indigo: 'tone-indigo',
        cyan: 'tone-cyan',
        blue: 'tone-blue',
    }[tone] || 'tone-neutral';
}

const DigestItem = defineComponent({
    props: { item: { type: Object, required: true } },
    setup(props) {
        return () => h('article', { class: 'digest-item' }, [
            h('div', { class: 'flex items-start justify-between gap-3' }, [
                h('div', { class: 'min-w-0' }, [
                    h('span', { class: `digest-pill ${toneClass(props.item.tone)}` }, props.item.type || 'activity'),
                    h('h4', { class: 'mt-2 line-clamp-2 font-bold text-slate-950' }, props.item.title),
                    h('p', { class: 'mt-1 line-clamp-3 text-sm leading-6 text-slate-600' }, props.item.summary || 'No summary supplied.'),
                ]),
                h('time', { class: 'shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-600' }, timeOnly(props.item.at)),
            ]),
            h('div', { class: 'mt-3 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-500' }, [
                h('span', props.item.actor || 'System'),
                props.item.meta ? h('span', `· ${props.item.meta}`) : null,
            ]),
        ]);
    },
});

const DigestPanel = defineComponent({
    props: {
        title: { type: String, required: true },
        items: { type: Array, default: () => [] },
    },
    setup(props) {
        return () => h('section', { class: 'digest-panel min-h-[22rem]' }, [
            h('div', { class: 'flex items-center justify-between gap-3' }, [
                h('h3', { class: 'section-title' }, props.title),
                h('span', { class: 'rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700' }, props.items.length),
            ]),
            h('div', { class: 'mt-5 space-y-3' }, props.items.length
                ? props.items.map((item) => h(DigestItem, { key: item.id, item }))
                : [h('p', { class: 'empty-state' }, 'No records for this category in the selected window.')]),
        ]);
    },
});
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
    @apply grid gap-5 p-5 lg:grid-cols-[1fr_22rem] lg:items-center;
}

.digest-filter-card {
    @apply grid gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4;
}

.digest-filter-card label {
    @apply grid gap-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500;
}

.digest-filter-card input,
.digest-filter-card select {
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

.metric-card span {
    @apply mt-2 block text-xs font-bold text-slate-500;
}

.metric-blue {
    @apply border-blue-200 bg-blue-50;
}

.metric-green {
    @apply border-emerald-200 bg-emerald-50;
}

.metric-amber {
    @apply border-amber-200 bg-amber-50;
}

.metric-red {
    @apply border-rose-200 bg-rose-50;
}

.metric-purple {
    @apply border-purple-200 bg-purple-50;
}

.metric-indigo {
    @apply border-indigo-200 bg-indigo-50;
}

.metric-cyan {
    @apply border-cyan-200 bg-cyan-50;
}

.metric-slate {
    @apply border-slate-200 bg-slate-100;
}

.digest-grid {
    @apply grid gap-5 xl:grid-cols-2;
}

.digest-panel {
    @apply p-5;
}

.staff-row,
.digest-item {
    @apply rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-100;
}

.staff-row {
    @apply flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between;
}

.staff-metrics {
    @apply grid grid-cols-2 gap-2 text-xs font-bold text-slate-600 sm:flex sm:flex-wrap sm:justify-end;
}

.staff-metrics span {
    @apply rounded-2xl bg-slate-50 px-3 py-2;
}

.staff-metrics b {
    @apply text-slate-950;
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

.tone-purple {
    @apply border-purple-500 bg-purple-100 text-purple-900;
}

.tone-indigo {
    @apply border-indigo-500 bg-indigo-100 text-indigo-900;
}

.tone-cyan {
    @apply border-cyan-500 bg-cyan-100 text-cyan-900;
}

.tone-blue {
    @apply border-blue-500 bg-blue-100 text-blue-900;
}

.tone-neutral {
    @apply border-slate-400 bg-slate-100 text-slate-800;
}

.empty-state {
    @apply rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-bold text-slate-500;
}
</style>
