<template>
    <AdminShell title="Reconciliation exceptions" subtitle="Investigate and resolve gateway, ledger, and escrow position mismatches.">
        <div class="space-y-5">
            <FinancialAuditNav active="exceptions" />

            <div class="space-y-4">
                <article
                    v-for="item in listing.data"
                    :key="item.id"
                    class="rounded-3xl border p-5 shadow-sm"
                    :class="[shell.card, item.status === 'open' ? 'border-amber-300' : '', item.escalated_at && item.status !== 'resolved' ? 'ring-2 ring-rose-300' : '']"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wide text-primary-600">{{ item.type_label }}</p>
                            <h2 class="mt-1 font-display text-lg font-black">{{ item.title }}</h2>
                            <p class="mt-2 text-sm font-medium text-slate-600">{{ item.description }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-black capitalize" :class="statusClass(item.status)">{{ item.status.replace(/_/g, ' ') }}</span>
                    </div>

                    <dl class="mt-4 grid gap-2 text-xs font-semibold sm:grid-cols-3">
                        <div><dt class="text-slate-400">Detected</dt><dd>{{ dateLabel(item.first_detected_at) }}</dd></div>
                        <div><dt class="text-slate-400">Paystack ref</dt><dd>{{ item.paystack_reference || '—' }}</dd></div>
                        <div><dt class="text-slate-400">Variance</dt><dd>{{ item.variance_display || '—' }}</dd></div>
                        <div><dt class="text-slate-400">Assigned to</dt><dd>{{ item.assigned_to || 'Unassigned' }}</dd></div>
                    </dl>

                    <p v-if="item.investigation_notes" class="mt-3 whitespace-pre-wrap rounded-2xl bg-slate-50 p-3 text-xs font-medium text-slate-700 dark:bg-white/5">{{ item.investigation_notes }}</p>

                    <div v-if="item.status !== 'resolved'" class="mt-4 grid gap-3 border-t pt-4 lg:grid-cols-3">
                        <form class="space-y-2" @submit.prevent="assign(item.id)">
                            <select v-model="assignForms[item.id]" class="w-full rounded-xl border px-3 py-2 text-xs font-bold" :class="shell.input">
                                <option value="">Assign to…</option>
                                <option v-for="admin in super_admins" :key="admin.id" :value="admin.id">{{ admin.name }}</option>
                            </select>
                            <button type="submit" class="w-full rounded-xl bg-slate-800 px-3 py-2 text-xs font-black uppercase text-white">Assign</button>
                        </form>
                        <form class="space-y-2 lg:col-span-1" @submit.prevent="addNote(item.id)">
                            <textarea v-model="noteForms[item.id]" rows="2" placeholder="Investigation note" class="w-full rounded-xl border px-3 py-2 text-xs font-semibold" :class="shell.input" />
                            <button type="submit" class="w-full rounded-xl border px-3 py-2 text-xs font-black uppercase" :class="shell.btnGhost">Add note</button>
                        </form>
                        <form class="space-y-2 lg:col-span-1" @submit.prevent="resolve(item.id)">
                            <textarea v-model="resolveForms[item.id]" rows="2" placeholder="Resolution description (required)" class="w-full rounded-xl border px-3 py-2 text-xs font-semibold" :class="shell.input" required />
                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black uppercase text-white">Mark resolved</button>
                        </form>
                    </div>
                </article>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import FinancialAuditNav from '@/Components/Admin/FinancialAuditNav.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineProps({
    listing: { type: Object, required: true },
    super_admins: { type: Array, default: () => [] },
});

const shell = useInjectedAdminTheme();
const assignForms = reactive({});
const noteForms = reactive({});
const resolveForms = reactive({});

function statusClass(status) {
    if (status === 'resolved') return 'bg-emerald-100 text-emerald-800';
    if (status === 'under_investigation') return 'bg-sky-100 text-sky-800';
    return 'bg-amber-100 text-amber-900';
}

function assign(id) {
    if (!assignForms[id]) return;
    router.post(route('admin.financial-audit.exceptions.assign', id), { assigned_to_user_id: assignForms[id] }, { preserveScroll: true });
}

function addNote(id) {
    if (!noteForms[id]?.trim()) return;
    router.post(route('admin.financial-audit.exceptions.notes', id), { notes: noteForms[id] }, {
        preserveScroll: true,
        onSuccess: () => { noteForms[id] = ''; },
    });
}

function resolve(id) {
    if (!resolveForms[id]?.trim()) return;
    router.post(route('admin.financial-audit.exceptions.resolve', id), { resolution_description: resolveForms[id] }, { preserveScroll: true });
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
</script>
