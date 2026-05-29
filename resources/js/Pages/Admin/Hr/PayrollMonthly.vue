<template>
    <AdminShell title="Monthly payroll" subtitle="All admin salary payments by month.">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <input v-model.number="yearForm" type="number" min="2020" max="2100" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                        <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white" @click="applyYear">Load</button>
                    </div>
                    <a :href="route('admin.hr.payroll-monthly.export', { year: yearForm })" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">
                        Download CSV
                    </a>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Staff</th>
                                <th class="px-2 py-2">Period</th>
                                <th class="px-2 py-2">Gross</th>
                                <th class="px-2 py-2">Allowances</th>
                                <th class="px-2 py-2">Deductions</th>
                                <th class="px-2 py-2">Net</th>
                                <th class="px-2 py-2">Payslip</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id" class="border-b border-slate-100">
                                <td class="px-2 py-2 font-semibold text-slate-800">{{ row.staff?.name || 'Unknown' }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ row.month }}/{{ row.year }}</td>
                                <td class="px-2 py-2 text-slate-700">NGN {{ formatMoney(row.gross_pay) }}</td>
                                <td class="px-2 py-2 text-slate-700">NGN {{ formatMoney(row.bonuses) }}</td>
                                <td class="px-2 py-2 text-slate-700">NGN {{ formatMoney(row.deductions) }}</td>
                                <td class="px-2 py-2 font-semibold text-slate-900">NGN {{ formatMoney(row.net_pay) }}</td>
                                <td class="px-2 py-2">
                                    <a :href="route('admin.hr.staff-payslips.download', { staff: row.staff?.id, year: row.year, month: row.month })" class="rounded-full border border-primary-300 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">
                                        Download
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td class="px-2 py-4 text-sm font-semibold text-slate-500" colspan="7">No payroll rows for selected year.</td>
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
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    year: { type: Number, required: true },
    rows: { type: Array, default: () => [] },
});

const yearForm = ref(props.year);

function applyYear() {
    router.get(route('admin.hr.payroll-monthly.index'), { year: yearForm.value }, { preserveState: true });
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString();
}
</script>
