<template>
    <OperationsShell title="My leave & payroll" subtitle="Track leave balance, submit leave requests, and review salary breakdown and payslips.">
        <div class="space-y-6">
            <section class="flex flex-wrap gap-2">
                <a :href="route('operations.hr.exports.performance')" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">Download performance PDF</a>
                <a :href="route('operations.hr.exports.payroll-history')" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">Download payroll history PDF</a>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Annual leave</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ balance.annual_days - balance.annual_days_used }} days left</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Sick leave</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ balance.sick_days - balance.sick_days_used }} days left</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Emergency leave</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ balance.emergency_days - balance.emergency_days_used }} days left</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Unpaid leave</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ balance.unpaid_days - balance.unpaid_days_used }} days left</p>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitLeave">
                    <h2 class="text-lg font-black text-slate-900">Request leave</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model="form.leave_type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option value="">Leave type</option>
                            <option value="annual">Annual leave</option>
                            <option value="sick">Sick leave</option>
                            <option value="emergency">Emergency leave</option>
                            <option value="unpaid">Unpaid leave</option>
                        </select>
                        <OperationsDateInput v-model="form.start_date" />
                        <OperationsDateInput v-model="form.end_date" wrapper-class="sm:col-span-2" :min="form.start_date || ''" />
                    </div>
                    <textarea v-model="form.reason" rows="3" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Reason (optional)" />
                    <button type="submit" class="mt-3 rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white">Submit request</button>
                </form>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Salary overview</h2>
                    <p class="mt-3 text-sm font-semibold text-slate-600">Base salary</p>
                    <p class="text-2xl font-black text-slate-900">NGN {{ formatMoney(payrollProfile?.base_salary || 0) }}</p>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ payrollProfile?.payment_frequency || 'monthly' }} · {{ payrollProfile?.currency || 'NGN' }}
                    </p>
                    <p class="mt-4 text-xs font-semibold text-slate-500">Allowances and deductions are reflected per payslip month.</p>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">My leave requests</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Track submissions, review notes, and outcomes.</p>
                    </div>
                    <p class="text-xs font-bold text-slate-500">{{ leaveRequests.length }} total · click headers to sort</p>
                </div>
                <div class="mt-4">
                    <OperationsQueueTable
                        :columns="leaveRequestColumns"
                        :rows="leaveQueue.pageItems.value"
                        v-model:search="leaveQueue.search.value"
                        :page="leaveQueue.page.value"
                        :total="leaveQueue.total.value"
                        :total-pages="leaveQueue.totalPages.value"
                        :sort-key="leaveQueue.sortKey.value"
                        :sort-dir="leaveQueue.sortDir.value"
                        :show-per-page="false"
                        :row-clickable="false"
                        search-placeholder="Filter leave requests…"
                        empty-message="No leave requests yet."
                        @sort="leaveQueue.setSort"
                        @page="(page) => (leaveQueue.page.value = page)"
                    >
                        <template #cell-leave_type="{ row }">
                            <span class="font-semibold capitalize text-slate-900">{{ humanizeSlug(row.leave_type) }}</span>
                        </template>
                        <template #cell-start_date="{ row }">
                            <span class="font-semibold text-slate-700">{{ formatLeaveRange(row) }}</span>
                        </template>
                        <template #cell-duration_type="{ row }">
                            <span class="text-slate-600">{{ leaveDurationLabel(row) }}</span>
                        </template>
                        <template #cell-status="{ row }">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.status)">{{ row.status }}</span>
                        </template>
                        <template #cell-created_at="{ row }">
                            <span class="text-sm font-semibold text-slate-600" :title="row.created_at">{{ formatLeaveDateTime(row.created_at) }}</span>
                        </template>
                        <template #cell-reviewed_at="{ row }">
                            <span class="text-sm font-semibold text-slate-600" :title="row.reviewed_at">{{ row.reviewed_at ? formatLeaveDateTime(row.reviewed_at) : '—' }}</span>
                        </template>
                        <template #cell-review_note="{ row }">
                            <span class="text-xs font-semibold text-slate-600">{{ row.review_note || '—' }}</span>
                        </template>
                    </OperationsQueueTable>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Current month adjustments</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Date</th>
                                <th class="px-2 py-2">Type</th>
                                <th class="px-2 py-2">Amount</th>
                                <th class="px-2 py-2">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="adjustment in currentMonthAdjustments" :key="adjustment.id" class="border-b border-slate-100">
                                <td class="px-2 py-2 text-slate-700">{{ formatLeaveDate(adjustment.effective_date) }}</td>
                                <td class="px-2 py-2 font-semibold" :class="adjustment.type === 'bonus' ? 'text-emerald-700' : 'text-rose-700'">{{ adjustment.type }}</td>
                                <td class="px-2 py-2 font-semibold text-slate-800">NGN {{ formatMoney(adjustment.amount) }}</td>
                                <td class="px-2 py-2 text-slate-600">{{ adjustment.reason }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Payslips</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Month</th>
                                <th class="px-2 py-2">Gross</th>
                                <th class="px-2 py-2">Bonus</th>
                                <th class="px-2 py-2">Deduction</th>
                                <th class="px-2 py-2">Net</th>
                                <th class="px-2 py-2">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="slip in payslips" :key="slip.id" class="border-b border-slate-100">
                                <td class="px-2 py-2 font-semibold text-slate-800">{{ slip.year }}-{{ String(slip.month).padStart(2, '0') }}</td>
                                <td class="px-2 py-2">NGN {{ formatMoney(slip.gross_pay) }}</td>
                                <td class="px-2 py-2">NGN {{ formatMoney(slip.bonuses) }}</td>
                                <td class="px-2 py-2">NGN {{ formatMoney(slip.deductions) }}</td>
                                <td class="px-2 py-2 font-black text-slate-900">NGN {{ formatMoney(slip.net_pay) }}</td>
                                <td class="px-2 py-2">
                                    <a v-if="slip.pdf_path" :href="`/${slip.pdf_path}`" class="rounded-lg border border-primary-200 bg-primary-50 px-2 py-1 text-[10px] font-black uppercase text-primary-800">Download</a>
                                    <span v-else class="text-xs text-slate-400">Pending</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Team availability calendar (upcoming leave)</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Staff</th>
                                <th class="px-2 py-2">Type</th>
                                <th class="px-2 py-2">From</th>
                                <th class="px-2 py-2">To</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in teamLeaveCalendar" :key="`team-${row.id}`" class="border-b border-slate-100">
                                <td class="px-2 py-2 font-semibold text-slate-800">{{ row.staff?.name || 'Unknown' }}</td>
                                <td class="px-2 py-2 capitalize text-slate-700">{{ humanizeSlug(row.leave_type) }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ formatLeaveDate(row.start_date) }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ formatLeaveDate(row.end_date) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </OperationsShell>
</template>

<script setup>
import OperationsDateInput from '@/Pages/Operations/Components/OperationsDateInput.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { formatLeaveDate, formatLeaveDateTime, formatLeaveRange, humanizeSlug } from '@/utils/formatHumanDateTime';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const leaveRequestColumns = [
    { key: 'leave_type', label: 'Type', sortable: true },
    { key: 'start_date', label: 'Dates', sortable: true },
    { key: 'duration_type', label: 'Duration', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'created_at', label: 'Submitted', sortable: true },
    { key: 'reviewed_at', label: 'Reviewed', sortable: true },
    { key: 'review_note', label: 'Review note' },
];

const props = defineProps({
    balance: { type: Object, required: true },
    leaveRequests: { type: Array, default: () => [] },
    payrollProfile: { type: Object, default: null },
    payslips: { type: Array, default: () => [] },
    currentMonthAdjustments: { type: Array, default: () => [] },
    teamLeaveCalendar: { type: Array, default: () => [] },
});

const leaveQueue = useClientQueue(() => props.leaveRequests, {
    defaultSortKey: 'created_at',
    defaultSortDir: 'desc',
    searchFields: ['leave_type', 'status', 'reason', 'review_note'],
    perPage: 10,
});

const form = reactive({
    leave_type: '',
    start_date: '',
    end_date: '',
    reason: '',
});

function submitLeave() {
    router.post(route('operations.hr.leave-requests.store'), form);
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString();
}

function leaveDurationLabel(leave) {
    if (leave?.duration_type === 'hours') {
        return `${leave?.hours_requested || 0} hour(s)`;
    }
    if (leave?.duration_type === 'multiple_days') {
        return `${leave?.days_requested || 0} day(s)`;
    }

    return 'Full day';
}

function statusClass(status) {
    if (status === 'approved') return 'bg-emerald-100 text-emerald-800';
    if (status === 'rejected') return 'bg-rose-100 text-rose-800';
    return 'bg-amber-100 text-amber-800';
}
</script>
