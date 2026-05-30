<template>
    <AdminShell title="HR management" subtitle="Role lifecycle, leave governance, payroll visibility, and immutable HR audit controls for staff admins.">
        <div class="space-y-6">
            <div class="pointer-events-none fixed bottom-5 right-5 z-50 space-y-2">
                <div v-for="item in toasts" :key="item.id" class="pointer-events-auto rounded-xl border px-4 py-3 text-sm font-bold shadow-xl" :class="item.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'">
                    {{ item.message }}
                </div>
            </div>
            <section class="grid gap-4 md:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Staff headcount</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ staff.length }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Active role assignments</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ activeAssignments.length }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Monthly payroll base</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">NGN {{ formatMoney(monthlyPayrollCost) }}</p>
                </article>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Role coverage dashboard</p>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-700">{{ roleCoverage.zero_coverage_groups?.length || 0 }} zero coverage</span>
                    </div>
                    <ul class="mt-3 space-y-1 text-sm font-semibold text-slate-700">
                        <li v-for="item in roleCoverage.items || []" :key="item.role_group">{{ item.label || item.role_group }}: {{ item.headcount }}</li>
                    </ul>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Benchmark breaches (2 consecutive weeks)</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ benchmarkBreaches.length }}</p>
                    <p class="mt-2 text-xs font-semibold text-slate-500">Auto-derived from role benchmark minimums and action logs.</p>
                </article>
            </section>

            <section class="flex flex-wrap gap-2">
                <a :href="route('admin.hr.exports.alerts')" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">Export alerts CSV</a>
                <a :href="route('admin.hr.exports.attendance')" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">Export attendance CSV</a>
                <a :href="route('admin.hr.payroll-monthly.index')" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">Monthly payroll page</a>
                <select v-model.number="reportStaffId" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-black uppercase tracking-wide text-slate-700">
                    <option :value="null">Select staff for PDF</option>
                    <option v-for="member in staff" :key="`pdf-${member.id}`" :value="member.id">{{ member.name }}</option>
                </select>
                <a v-if="reportStaffId" :href="route('admin.hr.exports.performance', reportStaffId)" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">Performance PDF</a>
                <a v-if="reportStaffId" :href="route('admin.hr.exports.payroll-history', reportStaffId)" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">Payroll history PDF</a>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <AdminRoleAssignmentForm :staff="staff" :active-assignments="activeAssignments" />

                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="adjustLeaveBalance">
                    <h2 class="text-lg font-black text-slate-900">Adjust leave balance</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model.number="balanceForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option :value="null">Select staff</option>
                            <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</option>
                        </select>
                        <input v-model.number="balanceForm.year" type="number" min="2020" max="2100" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                        <select v-model="balanceForm.leave_type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option value="">Leave type</option>
                            <option value="annual">Annual</option>
                            <option value="sick">Sick</option>
                            <option value="emergency">Emergency</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                        <input v-model.number="balanceForm.days" type="number" min="0" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Number of days" />
                    </div>
                    <textarea v-model="balanceForm.reason" rows="2" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Reason for balance adjustment..." />
                    <button type="submit" class="mt-3 inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white" :disabled="processing.adjustBalance">
                        <span v-if="processing.adjustBalance" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        <span>Apply balance</span>
                    </button>
                </form>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Payroll profiles</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-2 py-2">Staff ID</th>
                                    <th class="px-2 py-2">Salary</th>
                                    <th class="px-2 py-2">Currency</th>
                                    <th class="px-2 py-2">Frequency</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in payrollProfiles" :key="`pp-${row.id}`" class="border-b border-slate-100">
                                    <td class="px-2 py-2 text-slate-700">{{ row.staff_user_id }}</td>
                                    <td class="px-2 py-2 text-slate-700">NGN {{ formatMoney(row.base_salary) }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ row.currency }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ row.payment_frequency }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Payroll allowances / deductions</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-2 py-2">Staff ID</th>
                                    <th class="px-2 py-2">Type</th>
                                    <th class="px-2 py-2">Amount</th>
                                    <th class="px-2 py-2">Effective date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in payrollAdjustments" :key="`pa-${row.id}`" class="border-b border-slate-100">
                                    <td class="px-2 py-2 text-slate-700">{{ row.staff_user_id }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ row.type }}</td>
                                    <td class="px-2 py-2 text-slate-700">NGN {{ formatMoney(row.amount) }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ row.effective_date }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="updatePayrollProfile">
                    <h2 class="text-lg font-black text-slate-900">Payroll profile</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model.number="payrollForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option :value="null">Select staff</option>
                            <option v-for="member in staff" :key="`profile-staff-${member.id}`" :value="member.id">{{ member.name }}</option>
                        </select>
                        <input v-model.number="payrollForm.base_salary" type="number" min="0" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Basic salary" />
                        <input value="NGN" disabled class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-500" />
                        <input value="Monthly" disabled class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-500" />
                    </div>
                    <textarea v-model="payrollForm.bank_details" rows="2" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Encrypted-at-rest bank details input..." />
                    <div class="mt-3 rounded-xl border border-primary-100 bg-primary-50 p-3 text-sm font-semibold text-primary-900">
                        Total allowances: NGN {{ formatMoney(selectedStaffAllowanceTotal) }} · Total monthly pay: NGN {{ formatMoney((payrollForm.base_salary || 0) + selectedStaffAllowanceTotal) }}
                    </div>
                    <button type="submit" class="mt-3 inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white" :disabled="processing.updatePayroll">
                        <span v-if="processing.updatePayroll" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        <span>Update basic salary</span>
                    </button>
                </form>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Allowances</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model.number="allowanceForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option :value="null">Select staff</option>
                            <option v-for="member in staff" :key="`allowance-staff-${member.id}`" :value="member.id">{{ member.name }}</option>
                        </select>
                        <input v-model="allowanceForm.title" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Allowance title" />
                        <input v-model.number="allowanceForm.amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Amount" />
                    </div>
                    <button type="button" class="mt-3 inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white" :disabled="processing.storeAllowance" @click="storePayrollAllowance">
                        <span v-if="processing.storeAllowance" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        <span>Add allowance</span>
                    </button>
                    <div class="mt-4 space-y-2">
                        <div v-for="item in selectedStaffAllowances" :key="`allowance-${item.id}`" class="rounded-xl border border-slate-200 p-3">
                            <div class="grid gap-2 sm:grid-cols-3">
                                <input v-model="item.reference" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                                <input v-model.number="item.amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                                <div class="flex gap-2">
                                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white" @click="updatePayrollAllowance(item)">Save</button>
                                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white" @click="removePayrollAllowance(item)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h3 class="mt-6 text-base font-black text-slate-900">Deductions</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <select v-model.number="deductionForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option :value="null">Select staff</option>
                            <option v-for="member in staff" :key="`deduction-staff-${member.id}`" :value="member.id">{{ member.name }}</option>
                        </select>
                        <input v-model="deductionForm.title" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Deduction title" />
                        <select v-model="deductionForm.deduction_mode" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option value="flat">Flat amount</option>
                            <option value="percentage">Percentage</option>
                        </select>
                        <input v-if="deductionForm.deduction_mode === 'flat'" v-model.number="deductionForm.amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Flat amount" />
                        <template v-else>
                            <select v-model="deductionForm.deduction_basis" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option value="basic_salary">Basic salary</option>
                                <option value="total_pay">Total pay</option>
                                <option value="custom_amount">Custom amount</option>
                            </select>
                            <input v-model.number="deductionForm.deduction_percentage" type="number" min="0.01" max="100" step="0.01" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Percentage %" />
                            <input v-if="deductionForm.deduction_basis === 'custom_amount'" v-model.number="deductionForm.deduction_custom_base_amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Custom base amount" />
                        </template>
                    </div>
                    <button type="button" class="mt-3 inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white" :disabled="processing.storeDeduction" @click="storePayrollDeduction">
                        <span v-if="processing.storeDeduction" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        <span>Add deduction</span>
                    </button>
                    <div class="mt-4 space-y-2">
                        <div v-for="item in selectedStaffDeductions" :key="`deduction-${item.id}`" class="rounded-xl border border-slate-200 p-3">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <input v-model="item.reference" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                                <select v-model="item.deduction_mode" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                    <option value="flat">Flat amount</option>
                                    <option value="percentage">Percentage</option>
                                </select>
                                <input v-if="item.deduction_mode === 'flat'" v-model.number="item.amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                                <template v-else>
                                    <select v-model="item.deduction_basis" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                        <option value="basic_salary">Basic salary</option>
                                        <option value="total_pay">Total pay</option>
                                        <option value="custom_amount">Custom amount</option>
                                    </select>
                                    <input v-model.number="item.deduction_percentage" type="number" min="0.01" max="100" step="0.01" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                                    <input v-if="item.deduction_basis === 'custom_amount'" v-model.number="item.deduction_custom_base_amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                                </template>
                                <div class="flex gap-2 sm:col-span-2">
                                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white" @click="updatePayrollDeduction(item)">Save</button>
                                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white" @click="removePayrollDeduction(item)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

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
                                <td class="px-2 py-2 text-slate-700">{{ assignment.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Leave approvals</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Staff</th>
                                <th class="px-2 py-2">Type</th>
                                <th class="px-2 py-2">Range</th>
                                <th class="px-2 py-2">Duration</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in leaveRequests" :key="row.id" class="border-b border-slate-100">
                                <td class="px-2 py-2 font-semibold text-slate-800">{{ row.staff?.name || 'Unknown' }}</td>
                                <td class="px-2 py-2 font-semibold text-slate-700">{{ row.leave_type }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ formatLeaveRange(row) }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ formatLeaveDurationRequested(row) }}</td>
                                <td class="px-2 py-2">
                                    <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase" :class="statusClass(row.status)">{{ row.status }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <div v-if="row.status === 'pending'" class="flex flex-wrap gap-2">
                                        <button type="button" class="rounded-full bg-primary-700 px-3 py-2.5 text-sm font-black text-white" @click="reviewLeave(row, 'approved')">Approve</button>
                                        <button type="button" class="rounded-full bg-primary-700 px-3 py-2.5 text-sm font-black text-white" @click="reviewLeave(row, 'rejected')">Reject</button>
                                    </div>
                                    <span v-else class="text-xs font-semibold text-slate-400">Reviewed</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Staff leave balances</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-2 py-2">Staff</th>
                                    <th class="px-2 py-2">Year</th>
                                    <th class="px-2 py-2">Annual</th>
                                    <th class="px-2 py-2">Taken</th>
                                    <th class="px-2 py-2">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in staffLeaveBalances" :key="`lb-${row.id}`" class="border-b border-slate-100">
                                    <td class="px-2 py-2 font-semibold text-slate-800">{{ row.staff?.name || 'Unknown' }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ row.year }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ row.annual_days }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ row.annual_days_used }}</td>
                                    <td class="px-2 py-2 font-semibold text-slate-900">{{ row.annual_days - row.annual_days_used }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Staff payslips</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-2 py-2">Staff</th>
                                    <th class="px-2 py-2">Period</th>
                                    <th class="px-2 py-2">Net</th>
                                    <th class="px-2 py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in staffPayslips" :key="`ps-${row.id}`" class="border-b border-slate-100">
                                    <td class="px-2 py-2 font-semibold text-slate-800">{{ row.staff?.name || 'Unknown' }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ row.month }}/{{ row.year }}</td>
                                    <td class="px-2 py-2 text-slate-700">NGN {{ formatMoney(row.net_pay) }}</td>
                                    <td class="px-2 py-2">
                                        <a v-if="row.pdf_path" :href="route('admin.hr.payslips.download', row.id)" class="rounded-lg border border-slate-300 px-2 py-1 text-[10px] font-black uppercase text-slate-700">Download</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Immutable HR audit trail</h2>
                <form class="mt-3 grid gap-2 md:grid-cols-5" @submit.prevent="applyAuditFilters">
                    <input v-model="auditFilterForm.q" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold" placeholder="Search action/ip/user id..." />
                    <select v-model="auditFilterForm.action_type" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold">
                        <option value="">All actions</option>
                        <option v-for="actionType in auditActionTypes" :key="`action-${actionType}`" :value="actionType">{{ actionType }}</option>
                    </select>
                    <AdminDateInput v-model="auditFilterForm.from_date" button-class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900" />
                    <AdminDateInput v-model="auditFilterForm.to_date" button-class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900" />
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white">Filter</button>
                        <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-black uppercase tracking-wide text-slate-700" @click="resetAuditFilters">Reset</button>
                    </div>
                </form>
                <ul class="mt-4 space-y-2">
                    <li v-for="entry in auditTrail" :key="entry.id" class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm">
                        <p class="font-black text-slate-900">{{ entry.action_type }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">{{ formatDateTime(entry.created_at) }} · Actor #{{ entry.actor_user_id }} · Staff #{{ entry.target_staff_user_id || 'n/a' }}</p>
                        <p v-if="extractReason(entry)" class="mt-1 text-xs font-semibold text-primary-800">Reason: {{ extractReason(entry) }}</p>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">HR alerts inbox</h2>
                <ul class="mt-4 space-y-2">
                    <li v-for="alert in alerts" :key="`alert-${alert.id}`" class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-black text-slate-900">{{ alert.alert_type }}</p>
                            <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase" :class="alert.read_at ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">
                                {{ alert.read_at ? 'read' : 'unread' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs font-semibold text-slate-600">{{ alert.message }}</p>
                        <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ alert.triggered_at }} · {{ alert.staff?.name || 'Unknown staff' }}</p>
                        <button v-if="!alert.read_at" type="button" class="mt-2 rounded-full bg-primary-700 px-3 py-2.5 text-sm font-black text-white" @click="markAlertRead(alert)">
                            Mark read
                        </button>
                    </li>
                </ul>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="openComplianceCase">
                    <h2 class="text-lg font-black text-slate-900">Open compliance case</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model.number="complianceForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option :value="null">Select staff</option>
                            <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</option>
                        </select>
                        <select v-model="complianceForm.severity" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option value="">Severity</option>
                            <option value="minor">Minor</option>
                            <option value="serious">Serious</option>
                            <option value="gross_misconduct">Gross misconduct</option>
                        </select>
                    </div>
                    <textarea v-model="complianceForm.incident_note" rows="3" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Incident notes and context..." />
                    <button type="submit" class="mt-3 rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white">Open case</button>
                </form>

                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="flagSuspicious">
                    <h2 class="text-lg font-black text-slate-900">Flag suspicious activity</h2>
                    <select v-model.number="suspiciousForm.staff_user_id" class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                        <option :value="null">Select staff</option>
                        <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</option>
                    </select>
                    <input v-model.number="suspiciousForm.staff_session_log_id" type="number" min="1" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Session log ID (optional)" />
                    <textarea v-model="suspiciousForm.pattern" rows="2" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Suspicious pattern observed..." />
                    <textarea v-model="suspiciousForm.note" rows="2" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Investigator note (optional)" />
                    <button type="submit" class="mt-3 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white">Flag activity</button>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Team leave calendar (upcoming approved)</h2>
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
                            <tr v-for="row in leaveCalendar" :key="`calendar-${row.id}`" class="border-b border-slate-100">
                                <td class="px-2 py-2 font-semibold text-slate-800">{{ row.staff?.name || 'Unknown' }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ row.leave_type }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ formatLeaveDate(row.start_date) }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ formatLeaveDate(row.end_date) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Compliance cases</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Staff</th>
                                <th class="px-2 py-2">Severity</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in complianceCases" :key="`cc-${row.id}`" class="border-b border-slate-100">
                                <td class="px-2 py-2">#{{ row.staff_user_id }}</td>
                                <td class="px-2 py-2 font-semibold text-slate-700">{{ row.severity }}</td>
                                <td class="px-2 py-2"><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-800">{{ row.status }}</span></td>
                                <td class="px-2 py-2">
                                    <button type="button" class="rounded-full bg-primary-700 px-3 py-2.5 text-sm font-black text-white" @click="updateCaseStatus(row)">Update</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminRoleAssignmentForm from '@/Components/Admin/AdminRoleAssignmentForm.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { formatLeaveDate, formatLeaveDurationRequested, formatLeaveRange } from '@/utils/formatHumanDateTime';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    staff: { type: Array, default: () => [] },
    activeAssignments: { type: Array, default: () => [] },
    leaveRequests: { type: Array, default: () => [] },
    leaveCalendar: { type: Array, default: () => [] },
    monthlyPayrollCost: { type: Number, default: 0 },
    payrollProfiles: { type: Array, default: () => [] },
    payrollAdjustments: { type: Array, default: () => [] },
    payrollAllowances: { type: Array, default: () => [] },
    payrollDeductions: { type: Array, default: () => [] },
    staffLeaveBalances: { type: Array, default: () => [] },
    staffPayslips: { type: Array, default: () => [] },
    complianceCases: { type: Array, default: () => [] },
    suspiciousFlags: { type: Array, default: () => [] },
    alerts: { type: Array, default: () => [] },
    roleCoverage: { type: Object, default: () => ({ items: [], zero_coverage_groups: [] }) },
    benchmarkBreaches: { type: Array, default: () => [] },
    auditFilters: { type: Object, default: () => ({ q: '', action_type: '', from_date: '', to_date: '' }) },
    auditActionTypes: { type: Array, default: () => [] },
    auditTrail: { type: Array, default: () => [] },
});

const roleGroups = [
    { value: 'group_a_chat_communications', label: 'Group A - Chat & Communications' },
    { value: 'group_b_moderation_operations', label: 'Group B - Moderation Operations' },
    { value: 'group_c_people_trust_management', label: 'Group C - People & Trust' },
    { value: 'group_d_financial_disputes_casework', label: 'Group D - Financial & Disputes' },
];

const balanceForm = reactive({
    staff_user_id: null,
    year: new Date().getFullYear(),
    leave_type: '',
    mode: 'allocate',
    adjustment_direction: 'add',
    days: 1,
    reason: '',
});

const payrollForm = reactive({
    staff_user_id: null,
    base_salary: 0,
    bank_details: '',
});

const adjustmentForm = reactive({
    staff_user_id: null,
    type: '',
    amount: 0,
    effective_date: '',
    reason: '',
    reference: '',
    is_recurring: false,
});

const allowanceForm = reactive({
    staff_user_id: null,
    title: '',
    amount: 0,
});
const deductionForm = reactive({
    staff_user_id: null,
    title: '',
    deduction_mode: 'flat',
    amount: 0,
    deduction_basis: 'basic_salary',
    deduction_percentage: 0,
    deduction_custom_base_amount: 0,
});

const complianceForm = reactive({
    staff_user_id: null,
    severity: '',
    incident_note: '',
});

const suspiciousForm = reactive({
    staff_user_id: null,
    staff_session_log_id: null,
    pattern: '',
    note: '',
});
const reportStaffId = ref(null);
const toasts = ref([]);
const processing = reactive({
    adjustBalance: false,
    updatePayroll: false,
    storeAllowance: false,
    storeDeduction: false,
});
const auditFilterForm = reactive({
    q: props.auditFilters?.q || '',
    action_type: props.auditFilters?.action_type || '',
    from_date: props.auditFilters?.from_date || '',
    to_date: props.auditFilters?.to_date || '',
});

const selectedAllowanceStaffId = computed(() => allowanceForm.staff_user_id || payrollForm.staff_user_id || null);
const selectedStaffAllowances = computed(() => (props.payrollAllowances || []).filter((item) => Number(item.staff_user_id) === Number(selectedAllowanceStaffId.value)));
const selectedStaffAllowanceTotal = computed(() => selectedStaffAllowances.value.reduce((sum, item) => sum + Number(item.amount || 0), 0));
const selectedDeductionStaffId = computed(() => deductionForm.staff_user_id || payrollForm.staff_user_id || null);
const selectedStaffDeductions = computed(() => (props.payrollDeductions || []).filter((item) => Number(item.staff_user_id) === Number(selectedDeductionStaffId.value)));

function showToast(message, type = 'success') {
    if (!message) return;
    const id = Date.now() + Math.random();
    toasts.value.push({ id, message, type });
    window.setTimeout(() => {
        toasts.value = toasts.value.filter((item) => item.id !== id);
    }, 3200);
}

function adjustLeaveBalance() {
    processing.adjustBalance = true;
    router.post(route('admin.hr.leave-balances.adjust'), balanceForm, {
        onSuccess: () => showToast('Leave balance updated.'),
        onError: () => showToast('Failed to update leave balance.', 'error'),
        onFinish: () => { processing.adjustBalance = false; },
    });
}

function reviewLeave(row, status) {
    const reviewNote = window.prompt(`Provide a mandatory note to ${status} this leave request:`);
    if (!reviewNote) {
        return;
    }

    router.post(route('admin.hr.leave-requests.review', row.id), {
        status,
        review_note: reviewNote,
    }, {
        onSuccess: () => showToast(`Leave ${status}.`),
        onError: () => showToast('Failed to review leave request.', 'error'),
    });
}

function updatePayrollProfile() {
    processing.updatePayroll = true;
    router.post(route('admin.hr.payroll-profile.update'), payrollForm, {
        onSuccess: () => showToast('Basic salary updated.'),
        onError: () => showToast('Failed to update payroll profile.', 'error'),
        onFinish: () => { processing.updatePayroll = false; },
    });
}

function storePayrollAdjustment() {
    router.post(route('admin.hr.payroll-adjustments.store'), adjustmentForm, {
        onSuccess: () => showToast('Payroll adjustment logged.'),
        onError: () => showToast('Failed to log payroll adjustment.', 'error'),
    });
}

function storePayrollAllowance() {
    processing.storeAllowance = true;
    router.post(route('admin.hr.payroll-allowances.store'), allowanceForm, {
        onSuccess: () => {
            showToast('Allowance added.');
            allowanceForm.title = '';
            allowanceForm.amount = 0;
        },
        onError: () => showToast('Failed to add allowance.', 'error'),
        onFinish: () => { processing.storeAllowance = false; },
    });
}

function updatePayrollAllowance(item) {
    router.patch(route('admin.hr.payroll-allowances.update', item.id), {
        title: item.reference || 'Allowance',
        amount: item.amount,
    }, {
        onSuccess: () => showToast('Allowance updated.'),
        onError: () => showToast('Failed to update allowance.', 'error'),
    });
}

function removePayrollAllowance(item) {
    router.delete(route('admin.hr.payroll-allowances.destroy', item.id), {
        onSuccess: () => showToast('Allowance removed.'),
        onError: () => showToast('Failed to remove allowance.', 'error'),
    });
}

function storePayrollDeduction() {
    processing.storeDeduction = true;
    router.post(route('admin.hr.payroll-deductions.store'), deductionForm, {
        onSuccess: () => {
            showToast('Deduction added.');
            deductionForm.title = '';
            deductionForm.amount = 0;
            deductionForm.deduction_percentage = 0;
            deductionForm.deduction_custom_base_amount = 0;
        },
        onError: () => showToast('Failed to add deduction.', 'error'),
        onFinish: () => { processing.storeDeduction = false; },
    });
}

function updatePayrollDeduction(item) {
    router.patch(route('admin.hr.payroll-deductions.update', item.id), {
        title: item.reference || 'Deduction',
        deduction_mode: item.deduction_mode || 'flat',
        amount: item.amount,
        deduction_basis: item.deduction_basis || null,
        deduction_percentage: item.deduction_percentage || null,
        deduction_custom_base_amount: item.deduction_custom_base_amount || null,
    }, {
        onSuccess: () => showToast('Deduction updated.'),
        onError: () => showToast('Failed to update deduction.', 'error'),
    });
}

function removePayrollDeduction(item) {
    router.delete(route('admin.hr.payroll-deductions.destroy', item.id), {
        onSuccess: () => showToast('Deduction removed.'),
        onError: () => showToast('Failed to remove deduction.', 'error'),
    });
}

function openComplianceCase() {
    router.post(route('admin.hr.compliance-cases.store'), complianceForm, {
        onSuccess: () => showToast('Compliance case opened.'),
        onError: () => showToast('Failed to open compliance case.', 'error'),
    });
}

function flagSuspicious() {
    router.post(route('admin.hr.suspicious-flags.store'), suspiciousForm, {
        onSuccess: () => showToast('Suspicious activity flagged.'),
        onError: () => showToast('Failed to flag suspicious activity.', 'error'),
    });
}

function updateCaseStatus(row) {
    const status = window.prompt('New status: open, under_review, resolved, escalated', row.status || 'under_review');
    if (!status) {
        return;
    }

    const note = window.prompt('Optional note', '') || '';
    router.post(route('admin.hr.compliance-cases.status', row.id), {
        status,
        note,
    }, {
        onSuccess: () => showToast('Compliance case status updated.'),
        onError: () => showToast('Failed to update compliance case status.', 'error'),
    });
}

function markAlertRead(alert) {
    router.post(route('admin.hr.alerts.read', alert.id), {}, {
        onSuccess: () => showToast('Alert marked as read.'),
        onError: () => showToast('Failed to mark alert as read.', 'error'),
    });
}

function applyAuditFilters() {
    router.get(route('admin.hr.index'), {
        q: auditFilterForm.q || undefined,
        action_type: auditFilterForm.action_type || undefined,
        from_date: auditFilterForm.from_date || undefined,
        to_date: auditFilterForm.to_date || undefined,
    }, { preserveState: true });
}

function resetAuditFilters() {
    auditFilterForm.q = '';
    auditFilterForm.action_type = '';
    auditFilterForm.from_date = '';
    auditFilterForm.to_date = '';
    router.get(route('admin.hr.index'), {}, { preserveState: true });
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString();
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
}

function statusClass(status) {
    if (status === 'approved') return 'bg-emerald-100 text-emerald-800';
    if (status === 'rejected') return 'bg-rose-100 text-rose-800';
    return 'bg-amber-100 text-amber-800';
}

function roleGroupLabel(value) {
    return roleGroups.find((group) => group.value === value)?.label || value;
}

function extractReason(entry) {
    if (!entry) return '';
    const metadata = toObject(entry.metadata);
    const after = toObject(entry.after_values);
    const before = toObject(entry.before_values);
    return metadata.note || metadata.reason || after.review_note || after.reason || before.review_note || before.reason || '';
}

function toObject(value) {
    if (!value) return {};
    if (typeof value === 'object') return value;
    try {
        return JSON.parse(value);
    } catch {
        return {};
    }
}
</script>
