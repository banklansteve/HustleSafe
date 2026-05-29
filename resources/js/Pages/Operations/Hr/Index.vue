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
                        <input v-model="form.start_date" type="date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                        <input v-model="form.end_date" type="date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold sm:col-span-2" />
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
                <h2 class="text-lg font-black text-slate-900">My leave requests</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Type</th>
                                <th class="px-2 py-2">Date range</th>
                                <th class="px-2 py-2">Days</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2">Review note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="request in leaveRequests" :key="request.id" class="border-b border-slate-100">
                                <td class="px-2 py-2 font-semibold text-slate-800">{{ request.leave_type }}</td>
                                <td class="px-2 py-2">{{ request.start_date }} - {{ request.end_date }}</td>
                                <td class="px-2 py-2">{{ request.days_requested }}</td>
                                <td class="px-2 py-2"><span class="rounded-full px-2 py-1 text-[10px] font-black uppercase" :class="statusClass(request.status)">{{ request.status }}</span></td>
                                <td class="px-2 py-2 text-xs text-slate-500">{{ request.review_note || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
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
                                <td class="px-2 py-2 text-slate-700">{{ adjustment.effective_date }}</td>
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
                                <td class="px-2 py-2 text-slate-700">{{ row.leave_type }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ row.start_date }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ row.end_date }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </OperationsShell>
</template>

<script setup>
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineProps({
    balance: { type: Object, required: true },
    leaveRequests: { type: Array, default: () => [] },
    payrollProfile: { type: Object, default: null },
    payslips: { type: Array, default: () => [] },
    currentMonthAdjustments: { type: Array, default: () => [] },
    teamLeaveCalendar: { type: Array, default: () => [] },
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

function statusClass(status) {
    if (status === 'approved') return 'bg-emerald-100 text-emerald-800';
    if (status === 'rejected') return 'bg-rose-100 text-rose-800';
    return 'bg-amber-100 text-amber-800';
}
</script>
