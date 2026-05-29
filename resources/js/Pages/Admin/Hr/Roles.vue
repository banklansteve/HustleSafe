<template>
    <AdminShell title="Role management" subtitle="Assign and monitor operational role groups for staff admins.">
        <div class="space-y-6">
            <section class="grid gap-4 md:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Role coverage dashboard</p>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-700">{{ roleCoverage.zero_coverage_groups?.length || 0 }} zero coverage</span>
                    </div>
                    <ul class="mt-3 space-y-1 text-sm font-semibold text-slate-700">
                        <li v-for="item in roleCoverage.items || []" :key="item.role_group">{{ item.label }}: {{ item.headcount }}</li>
                    </ul>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Benchmark breaches (2 consecutive weeks)</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ benchmarkBreaches.length }}</p>
                </article>
            </section>

            <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="assignRoleGroup">
                <h2 class="text-lg font-black text-slate-900">Assign role group</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <select v-model.number="roleForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                        <option :value="null">Select staff</option>
                        <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }} ({{ member.email }})</option>
                    </select>
                    <select v-model="roleForm.role_group" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                        <option value="">Role group</option>
                        <option v-for="group in roleGroups" :key="group.value" :value="group.value">{{ group.label }}</option>
                    </select>
                    <input v-model="roleForm.starts_on" type="date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                    <input v-model="roleForm.ends_on" type="date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                </div>
                <textarea v-model="roleForm.reason" rows="2" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Reason for this assignment..." />
                <button type="submit" class="mt-3 inline-flex items-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white disabled:opacity-70" :disabled="assigningRole">
                    <span v-if="assigningRole" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                    Save role assignment
                </button>
            </form>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Role assignments by admin</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Staff</th>
                                <th class="px-2 py-2">Role group</th>
                                <th class="px-2 py-2">From</th>
                                <th class="px-2 py-2">To</th>
                                <th class="px-2 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="assignment in activeAssignments" :key="assignment.id" class="border-b border-slate-100">
                                <td class="px-2 py-2 font-semibold text-slate-800">{{ assignment.staff?.name || 'Unknown' }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ roleGroupLabel(assignment.role_group) }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ assignment.starts_on }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ assignment.ends_on || 'Open' }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ assignment.status }}</td>
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
import { useAdminHrInertia } from '@/composables/useAdminHrInertia';
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

defineProps({
    staff: { type: Array, default: () => [] },
    activeAssignments: { type: Array, default: () => [] },
    roleCoverage: { type: Object, default: () => ({ items: [], zero_coverage_groups: [] }) },
    benchmarkBreaches: { type: Array, default: () => [] },
});

const roleGroups = [
    { value: 'group_a_chat_communications', label: 'Group A - Chat & Communications' },
    { value: 'group_b_moderation_operations', label: 'Group B - Moderation Operations' },
    { value: 'group_c_people_trust_management', label: 'Group C - People & Trust' },
    { value: 'group_d_financial_disputes_casework', label: 'Group D - Financial & Disputes' },
];
const roleForm = reactive({ staff_user_id: null, role_group: '', starts_on: '', ends_on: '', reason: '' });
const { inertiaOptions } = useAdminHrInertia();
const assigningRole = ref(false);

function assignRoleGroup() {
    assigningRole.value = true;
    router.post(route('admin.hr.role-group.assign'), roleForm, inertiaOptions(
        { success: 'Role group assigned.', error: 'Could not assign role group.' },
        {
            onFinish: () => {
                assigningRole.value = false;
            },
        },
    ));
}
function roleGroupLabel(value) {
    return roleGroups.find((group) => group.value === value)?.label || value;
}
</script>
