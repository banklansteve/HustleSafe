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

            <AdminRoleAssignmentForm :staff="staff" :active-assignments="activeAssignments" />

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
                                <td class="px-2 py-2 text-slate-700">{{ formatLeaveDate(assignment.starts_on) }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ assignment.ends_on ? formatLeaveDate(assignment.ends_on) : 'Open' }}</td>
                                <td class="px-2 py-2 capitalize text-slate-700">{{ assignment.status }}</td>
                            </tr>
                            <tr v-if="!activeAssignments.length">
                                <td class="px-2 py-6 text-sm font-semibold text-slate-500" colspan="5">No active role assignments yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminRoleAssignmentForm from '@/Components/Admin/AdminRoleAssignmentForm.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { formatLeaveDate } from '@/utils/formatHumanDateTime';

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

function roleGroupLabel(value) {
    return roleGroups.find((group) => group.value === value)?.label || value;
}
</script>
