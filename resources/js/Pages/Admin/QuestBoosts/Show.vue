<template>
    <AdminShell title="Boost detail" :subtitle="boost.reference">
        <div class="space-y-6">
            <Link :href="route('admin.quest-boosts.index')" class="text-xs font-black uppercase text-primary-600">← All boosts</Link>

            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-3xl border p-5 space-y-3" :class="shell.card">
                    <h2 class="text-sm font-black uppercase" :class="shell.title">Boost</h2>
                    <dl class="grid gap-2 text-sm">
                        <div><dt class="text-xs font-bold uppercase text-slate-500">Quest</dt><dd class="font-semibold">{{ boost.quest_title }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-slate-500">Client</dt><dd>{{ boost.client?.name }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-slate-500">Tier</dt><dd>{{ boost.tier_label }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-slate-500">Planned cost</dt><dd>{{ boost.planned_cost_display }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-slate-500">Status</dt><dd>{{ boost.status_label }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-slate-500">Grant reason</dt><dd class="whitespace-pre-wrap">{{ boost.grant_reason }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-slate-500">Granted by</dt><dd>{{ boost.granting_admin?.name }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-slate-500">Start</dt><dd>{{ formatWhen(boost.starts_at) }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-slate-500">End</dt><dd>{{ formatWhen(boost.ends_at) }}</dd></div>
                        <div v-if="boost.actual_ended_at"><dt class="text-xs font-bold uppercase text-slate-500">Actual end</dt><dd>{{ formatWhen(boost.actual_ended_at) }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-slate-500">Duration (hours)</dt><dd>{{ boost.actual_duration_hours }}</dd></div>
                    </dl>
                </div>

                <div v-if="boost.status === 'active'" class="rounded-3xl border p-5 space-y-4" :class="shell.card">
                    <h2 class="text-sm font-black uppercase" :class="shell.title">Actions</h2>
                    <form class="space-y-3" @submit.prevent="saveDates">
                        <p class="text-xs font-bold uppercase text-slate-500">Edit dates</p>
                        <input v-model="datesForm.starts_at" type="datetime-local" class="w-full rounded-xl border px-3 py-2 text-sm" :class="shell.input" />
                        <input v-model="datesForm.ends_at" type="datetime-local" class="w-full rounded-xl border px-3 py-2 text-sm" :class="shell.input" />
                        <input v-model="datesForm.reason" type="text" placeholder="Reason for edit (optional)" class="w-full rounded-xl border px-3 py-2 text-sm" :class="shell.input" />
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white dark:bg-white dark:text-slate-900" :disabled="datesForm.processing">Save dates</button>
                    </form>
                    <button type="button" class="w-full rounded-xl border border-amber-300 px-4 py-2 text-xs font-black uppercase text-amber-800" @click="endEarly">End boost early</button>
                    <button type="button" class="w-full rounded-xl border border-rose-300 px-4 py-2 text-xs font-black uppercase text-rose-800" @click="cancelBoost">Cancel boost</button>
                </div>
            </section>

            <section class="rounded-3xl border p-5" :class="shell.card">
                <h2 class="text-sm font-black uppercase" :class="shell.title">Audit trail</h2>
                <ol class="mt-4 space-y-4">
                    <li v-for="entry in audit_trail" :key="entry.id" class="rounded-2xl border p-4 text-sm" :class="shell.card">
                        <p class="font-black uppercase text-[10px] tracking-wide text-slate-500">{{ entry.action_type }}</p>
                        <p class="mt-1 font-semibold">{{ entry.actor?.name || 'System' }} · {{ formatWhen(entry.occurred_at) }}</p>
                        <p v-if="entry.reason" class="mt-2 text-slate-600">{{ entry.reason }}</p>
                    </li>
                </ol>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { useAdminShell } from '@/Composables/useAdminShell';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    boost: { type: Object, required: true },
    audit_trail: { type: Array, default: () => [] },
});

const { shell } = useAdminShell();

const datesForm = useForm({
    starts_at: toLocalInput(props.boost.starts_at),
    ends_at: toLocalInput(props.boost.ends_at),
    reason: '',
});

function toLocalInput(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
}

function saveDates() {
    datesForm.patch(route('admin.quest-boosts.dates.update', props.boost.id), { preserveScroll: true });
}

function endEarly() {
    const reason = window.prompt('Reason for ending this boost early (required):');
    if (!reason?.trim()) return;
    router.post(route('admin.quest-boosts.end-early', props.boost.id), { reason }, { preserveScroll: true });
}

function cancelBoost() {
    const reason = window.prompt('Reason for cancelling this boost (required):');
    if (!reason?.trim()) return;
    router.post(route('admin.quest-boosts.cancel', props.boost.id), { reason }, { preserveScroll: true });
}
</script>
