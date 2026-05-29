<template>
    <AdminShell title="Payment management" subtitle="Manage salary, payout account details, allowances, and deductions.">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Payroll period</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <select v-model.number="filterYear" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                        <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                    </select>
                    <select v-model.number="filterMonth" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                        <option v-for="month in monthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
                    </select>
                    <button type="button" class="rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white sm:col-span-2 lg:col-span-1" @click="applyPeriodFilter">
                        View month payroll
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Download staff payslip</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Generate a branded PDF payslip for any staff admin and period.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <select v-model.number="payslipForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                        <option :value="null">Select staff</option>
                        <option v-for="member in staff" :key="`payslip-${member.id}`" :value="member.id">{{ member.name }}</option>
                    </select>
                    <select v-model.number="payslipForm.month" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                        <option v-for="month in monthOptions" :key="`payslip-month-${month.value}`" :value="month.value">{{ month.label }}</option>
                    </select>
                    <input v-model.number="payslipForm.year" type="number" min="2020" max="2100" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                    <a
                        v-if="payslipForm.staff_user_id"
                        :href="route('admin.hr.staff-payslips.download', { staff: payslipForm.staff_user_id, month: payslipForm.month, year: payslipForm.year })"
                        class="inline-flex items-center justify-center rounded-full bg-primary-700 px-4 py-2.5 text-center text-sm font-black text-white"
                    >
                        Download payslip
                    </a>
                    <span v-else class="inline-flex items-center justify-center rounded-full border border-dashed border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-500">
                        Select staff to download
                    </span>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-black text-slate-900">All staff admins</h2>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ selectedPeriodLabel }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-black uppercase tracking-wide text-slate-600">
                                <th class="px-4 py-3">Staff</th>
                                <th class="px-4 py-3">Basic salary</th>
                                <th class="px-4 py-3">Allowances</th>
                                <th class="px-4 py-3">Deductions</th>
                                <th class="px-4 py-3">Net pay</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm">
                            <tr v-for="member in staffRows" :key="member.id">
                                <td class="px-4 py-3">
                                    <p class="font-black text-slate-900">{{ member.name }}</p>
                                    <p class="text-xs font-semibold text-slate-500">{{ member.email }}</p>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-700">NGN {{ formatMoney(member.basicSalary) }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-700">NGN {{ formatMoney(member.allowancesTotal) }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-700">NGN {{ formatMoney(member.deductionsTotal) }}</td>
                                <td class="px-4 py-3 font-black text-primary-900">NGN {{ formatMoney(member.netPay) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="rounded-full bg-slate-800 px-4 py-2.5 text-sm font-black text-white" @click="startStaffEdit(member.id)">
                                            Edit setup
                                        </button>
                                        <button type="button" class="rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white" @click="openPayrollDrawer(member.id)">
                                            View
                                        </button>
                                        <a :href="route('admin.hr.staff-payslips.download', { staff: member.id, year: filterYear, month: filterMonth })" class="rounded-full border border-primary-300 bg-primary-50 px-4 py-2.5 text-sm font-black text-primary-800">
                                            Download payslip
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="updatePayrollProfile">
                    <h2 class="text-lg font-black text-slate-900">Payroll profile</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model.number="payrollForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option :value="null">Select staff</option>
                            <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</option>
                        </select>
                        <input v-model.number="payrollForm.base_salary" type="number" min="0" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Basic salary" />
                        <select v-model.number="payrollForm.wef_month" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option v-for="month in monthOptions" :key="`profile-wef-${month.value}`" :value="month.value">WEF {{ month.label }}</option>
                        </select>
                        <select v-model.number="payrollForm.wef_year" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option v-for="year in yearOptions" :key="`profile-wef-year-${year}`" :value="year">WEF {{ year }}</option>
                        </select>
                        <input v-model="payrollForm.bank_name" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Bank name" />
                        <input v-model="payrollForm.bank_account_name" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Account name" />
                        <input v-model="payrollForm.bank_account_number" type="text" maxlength="11" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold sm:col-span-2" placeholder="NUBAN (11 chars)" />
                    </div>
                    <button type="submit" class="mt-3 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white">Update payroll profile</button>
                </form>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Allowances</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model.number="allowanceForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option :value="null">Select staff</option>
                            <option v-for="member in staff" :key="`allowance-${member.id}`" :value="member.id">{{ member.name }}</option>
                        </select>
                        <input v-model="allowanceForm.title" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Allowance title" />
                        <input v-model.number="allowanceForm.amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Amount" />
                        <select v-model.number="allowanceForm.wef_month" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option v-for="month in monthOptions" :key="`allowance-wef-${month.value}`" :value="month.value">WEF {{ month.label }}</option>
                        </select>
                        <select v-model.number="allowanceForm.wef_year" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option v-for="year in yearOptions" :key="`allowance-wef-year-${year}`" :value="year">WEF {{ year }}</option>
                        </select>
                    </div>
                    <button type="button" class="mt-3 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white" @click="storePayrollAllowance">Add allowance</button>

                    <ul class="mt-6 space-y-2">
                        <li v-for="item in selectedStaffAllowances" :key="`allowance-item-${item.id}`" class="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-sm font-semibold">
                            <span>{{ item.reference }} - NGN {{ formatMoney(item.amount) }}</span>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-full bg-slate-800 px-4 py-2.5 text-sm font-black text-white" @click="startEditAllowance(item)">Edit</button>
                                <button type="button" class="rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white" @click="removePayrollAllowance(item)">Remove</button>
                            </div>
                        </li>
                        <li v-if="!selectedStaffAllowances.length" class="rounded-xl border border-dashed border-slate-300 p-3 text-sm font-semibold text-slate-500">No allowances added yet.</li>
                    </ul>

                    <form v-if="editAllowance.id" class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-3" @submit.prevent="saveAllowanceEdit">
                        <input v-model="editAllowance.title" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                        <input v-model.number="editAllowance.amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                        <select v-model.number="editAllowance.wef_month" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option v-for="month in monthOptions" :key="`allowance-edit-wef-${month.value}`" :value="month.value">WEF {{ month.label }}</option>
                        </select>
                        <select v-model.number="editAllowance.wef_year" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option v-for="year in yearOptions" :key="`allowance-edit-year-${year}`" :value="year">WEF {{ year }}</option>
                        </select>
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white">Save</button>
                            <button type="button" class="rounded-full bg-slate-200 px-4 py-2.5 text-sm font-black text-slate-700" @click="resetAllowanceEdit">Cancel</button>
                        </div>
                    </form>

                    <div class="mt-10 border-t border-slate-200 pt-6">
                        <h3 class="text-base font-black text-slate-900">Deductions</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <select v-model.number="deductionForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option :value="null">Select staff</option>
                                <option v-for="member in staff" :key="`deduction-${member.id}`" :value="member.id">{{ member.name }}</option>
                            </select>
                            <input v-model="deductionForm.title" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Deduction title" />
                            <select v-model.number="deductionForm.wef_month" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option v-for="month in monthOptions" :key="`deduction-wef-${month.value}`" :value="month.value">WEF {{ month.label }}</option>
                            </select>
                            <select v-model.number="deductionForm.wef_year" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option v-for="year in yearOptions" :key="`deduction-wef-year-${year}`" :value="year">WEF {{ year }}</option>
                            </select>
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
                        <button type="button" class="mt-3 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white" @click="storePayrollDeduction">Add deduction</button>

                        <ul class="mt-5 space-y-2">
                            <li v-for="item in selectedStaffDeductions" :key="`deduction-item-${item.id}`" class="flex items-center justify-between rounded-xl border border-slate-200 p-3 text-sm font-semibold">
                                <span>
                                    {{ item.reference }} -
                                    <span v-if="item.deduction_mode === 'percentage'">{{ item.deduction_percentage }}% {{ item.deduction_basis }}</span>
                                    <span v-else>NGN {{ formatMoney(item.amount) }}</span>
                                </span>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-full bg-slate-800 px-4 py-2.5 text-sm font-black text-white" @click="startEditDeduction(item)">Edit</button>
                                    <button type="button" class="rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white" @click="removePayrollDeduction(item)">Remove</button>
                                </div>
                            </li>
                            <li v-if="!selectedStaffDeductions.length" class="rounded-xl border border-dashed border-slate-300 p-3 text-sm font-semibold text-slate-500">No deductions added yet.</li>
                        </ul>

                        <form v-if="editDeduction.id" class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2" @submit.prevent="saveDeductionEdit">
                            <input v-model="editDeduction.title" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                            <select v-model.number="editDeduction.wef_month" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option v-for="month in monthOptions" :key="`deduction-edit-wef-${month.value}`" :value="month.value">WEF {{ month.label }}</option>
                            </select>
                            <select v-model.number="editDeduction.wef_year" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option v-for="year in yearOptions" :key="`deduction-edit-year-${year}`" :value="year">WEF {{ year }}</option>
                            </select>
                            <select v-model="editDeduction.deduction_mode" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option value="flat">Flat amount</option>
                                <option value="percentage">Percentage</option>
                            </select>
                            <input v-if="editDeduction.deduction_mode === 'flat'" v-model.number="editDeduction.amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                            <template v-else>
                                <select v-model="editDeduction.deduction_basis" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                    <option value="basic_salary">Basic salary</option>
                                    <option value="total_pay">Total pay</option>
                                    <option value="custom_amount">Custom amount</option>
                                </select>
                                <input v-model.number="editDeduction.deduction_percentage" type="number" min="0.01" max="100" step="0.01" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                                <input v-if="editDeduction.deduction_basis === 'custom_amount'" v-model.number="editDeduction.deduction_custom_base_amount" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                            </template>
                            <div class="flex gap-2 sm:col-span-2">
                                <button type="submit" class="rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white">Save</button>
                                <button type="button" class="rounded-full bg-slate-200 px-4 py-2.5 text-sm font-black text-slate-700" @click="resetDeductionEdit">Cancel</button>
                            </div>
                        </form>
                    </div>
                </article>
            </section>
        </div>

        <transition name="fade">
            <div v-if="drawerOpen" class="fixed inset-0 z-40 bg-slate-950/35" @click="drawerOpen = false" />
        </transition>
        <transition name="slide">
            <aside v-if="drawerOpen" class="fixed right-0 top-0 z-50 h-full w-full max-w-xl overflow-y-auto border-l border-slate-200 bg-white p-6 shadow-2xl">
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">{{ drawerStaff?.name }}</h2>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ selectedPeriodLabel }}</p>
                    </div>
                    <button type="button" class="rounded-full bg-slate-200 px-3 py-1 text-xs font-black text-slate-700" @click="drawerOpen = false">Close</button>
                </div>
                <a v-if="drawerStaff?.id" :href="route('admin.hr.staff-payslips.download', { staff: drawerStaff.id, year: filterYear, month: filterMonth })" class="mb-3 inline-flex rounded-full border border-primary-300 bg-primary-50 px-4 py-2.5 text-sm font-black text-primary-800">
                    Download this payslip
                </a>
                <div class="grid gap-3 sm:grid-cols-2">
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Basic salary</p>
                        <p class="mt-1 text-lg font-black text-slate-900">NGN {{ formatMoney(drawerSummary.basicSalary) }}</p>
                    </article>
                    <article class="rounded-xl border border-primary-200 bg-primary-50 p-3">
                        <p class="text-xs font-black uppercase tracking-wide text-primary-700">Net pay</p>
                        <p class="mt-1 text-lg font-black text-primary-900">NGN {{ formatMoney(drawerSummary.netPay) }}</p>
                    </article>
                </div>
                <article class="mt-4 rounded-xl border border-slate-200 p-4">
                    <h3 class="text-sm font-black text-slate-900">Allowances</h3>
                    <ul class="mt-2 space-y-1 text-sm font-semibold text-slate-700">
                        <li v-for="item in drawerAllowances" :key="`drawer-allowance-${item.id}`">{{ item.reference }}: NGN {{ formatMoney(item.amount) }}</li>
                        <li v-if="!drawerAllowances.length" class="text-slate-500">No allowances for this period.</li>
                    </ul>
                </article>
                <article class="mt-3 rounded-xl border border-slate-200 p-4">
                    <h3 class="text-sm font-black text-slate-900">Deductions</h3>
                    <ul class="mt-2 space-y-1 text-sm font-semibold text-slate-700">
                        <li v-for="item in drawerDeductions" :key="`drawer-deduction-${item.id}`">
                            {{ item.reference }}:
                            <span v-if="item.deduction_mode === 'percentage'">{{ item.deduction_percentage }}% of {{ item.deduction_basis }}</span>
                            <span v-else>NGN {{ formatMoney(item.amount) }}</span>
                        </li>
                        <li v-if="!drawerDeductions.length" class="text-slate-500">No deductions for this period.</li>
                    </ul>
                </article>
            </aside>
        </transition>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { useAdminHrInertia } from '@/composables/useAdminHrInertia';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const { inertiaOptions } = useAdminHrInertia();

const props = defineProps({
    staff: { type: Array, default: () => [] },
    payrollProfiles: { type: Array, default: () => [] },
    payrollAllowances: { type: Array, default: () => [] },
    payrollDeductions: { type: Array, default: () => [] },
    selectedYear: { type: Number, default: new Date().getFullYear() },
    selectedMonth: { type: Number, default: new Date().getMonth() + 1 },
});

const payrollForm = reactive({ staff_user_id: null, base_salary: 0, wef_month: new Date().getMonth() + 1, wef_year: new Date().getFullYear(), bank_name: '', bank_account_name: '', bank_account_number: '' });
const allowanceForm = reactive({ staff_user_id: null, title: '', amount: 0, wef_month: new Date().getMonth() + 1, wef_year: new Date().getFullYear() });
const deductionForm = reactive({ staff_user_id: null, title: '', wef_month: new Date().getMonth() + 1, wef_year: new Date().getFullYear(), deduction_mode: 'flat', amount: null, deduction_basis: 'basic_salary', deduction_percentage: null, deduction_custom_base_amount: null });
const editAllowance = reactive({ id: null, title: '', amount: null, wef_month: new Date().getMonth() + 1, wef_year: new Date().getFullYear() });
const editDeduction = reactive({ id: null, title: '', wef_month: new Date().getMonth() + 1, wef_year: new Date().getFullYear(), deduction_mode: 'flat', amount: null, deduction_basis: 'basic_salary', deduction_percentage: null, deduction_custom_base_amount: null });

const drawerOpen = ref(false);
const drawerStaffId = ref(null);
const filterYear = ref(props.selectedYear);
const filterMonth = ref(props.selectedMonth);
const payslipForm = reactive({
    staff_user_id: null,
    month: props.selectedMonth,
    year: props.selectedYear,
});

const selectedStaffId = computed(() => payrollForm.staff_user_id || allowanceForm.staff_user_id || deductionForm.staff_user_id || null);
const selectedProfile = computed(() => resolveProfileForPeriod(selectedStaffId.value));
const selectedStaffAllowances = computed(() => (props.payrollAllowances || []).filter((item) => Number(item.staff_user_id) === Number(selectedStaffId.value) && isAdjustmentApplicable(item, filterYear.value, filterMonth.value)));
const selectedStaffDeductions = computed(() => (props.payrollDeductions || []).filter((item) => Number(item.staff_user_id) === Number(selectedStaffId.value) && isAdjustmentApplicable(item, filterYear.value, filterMonth.value)));
const totalAllowances = computed(() => selectedStaffAllowances.value.reduce((sum, item) => sum + Number(item.amount || 0), 0));
const totalDeductions = computed(() => selectedStaffDeductions.value.reduce((sum, item) => sum + evaluateDeduction(item), 0));
const netPay = computed(() => Number(selectedProfile.value?.base_salary || payrollForm.base_salary || 0) + totalAllowances.value - totalDeductions.value);
const selectedPeriodLabel = computed(() => new Date(filterYear.value, filterMonth.value - 1, 1).toLocaleString('en-NG', { month: 'long', year: 'numeric' }));
const monthOptions = computed(() => Array.from({ length: 12 }, (_, index) => ({ value: index + 1, label: new Date(2000, index, 1).toLocaleString('en-NG', { month: 'long' }) })));
const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    return [current - 1, current, current + 1];
});

const staffRows = computed(() => (props.staff || []).map((member) => {
    const profile = resolveProfileForPeriod(member.id);
    const allowances = (props.payrollAllowances || []).filter((item) => Number(item.staff_user_id) === Number(member.id) && isAdjustmentApplicable(item, filterYear.value, filterMonth.value));
    const deductions = (props.payrollDeductions || []).filter((item) => Number(item.staff_user_id) === Number(member.id) && isAdjustmentApplicable(item, filterYear.value, filterMonth.value));
    const basicSalary = Number(profile?.base_salary || 0);
    const allowancesTotal = allowances.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const deductionsTotal = deductions.reduce((sum, item) => sum + evaluateDeduction(item, basicSalary, allowancesTotal), 0);

    return { ...member, basicSalary, allowancesTotal, deductionsTotal, netPay: basicSalary + allowancesTotal - deductionsTotal };
}));

const drawerStaff = computed(() => (props.staff || []).find((item) => Number(item.id) === Number(drawerStaffId.value)));
const drawerProfile = computed(() => resolveProfileForPeriod(drawerStaffId.value));
const drawerAllowances = computed(() => (props.payrollAllowances || []).filter((item) => Number(item.staff_user_id) === Number(drawerStaffId.value) && isAdjustmentApplicable(item, filterYear.value, filterMonth.value)));
const drawerDeductions = computed(() => (props.payrollDeductions || []).filter((item) => Number(item.staff_user_id) === Number(drawerStaffId.value) && isAdjustmentApplicable(item, filterYear.value, filterMonth.value)));
const drawerSummary = computed(() => {
    const basicSalary = Number(drawerProfile.value?.base_salary || 0);
    const allowancesTotal = drawerAllowances.value.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const deductionsTotal = drawerDeductions.value.reduce((sum, item) => sum + evaluateDeduction(item, basicSalary, allowancesTotal), 0);
    return { basicSalary, allowancesTotal, deductionsTotal, netPay: basicSalary + allowancesTotal - deductionsTotal };
});

function evaluateDeduction(item, basicSalary = Number(selectedProfile.value?.base_salary || 0), allowancesTotal = totalAllowances.value) {
    if (item.deduction_mode !== 'percentage') return Number(item.amount || 0);
    const percentage = Number(item.deduction_percentage || 0) / 100;
    if (item.deduction_basis === 'total_pay') return (basicSalary + allowancesTotal) * percentage;
    if (item.deduction_basis === 'custom_amount') return Number(item.deduction_custom_base_amount || 0) * percentage;
    return basicSalary * percentage;
}

function isAdjustmentApplicable(item, year, month) {
    if (!item?.effective_date) return true;
    const effective = new Date(item.effective_date);
    const periodEnd = new Date(year, month, 0);
    return effective <= periodEnd;
}

function resolveProfileForPeriod(staffId) {
    if (!staffId) return null;
    const periodEnd = new Date(filterYear.value, filterMonth.value, 0);
    const profiles = (props.payrollProfiles || []).filter((item) => Number(item.staff_user_id) === Number(staffId));
    const applicable = profiles.filter((item) => !item.effective_from || new Date(item.effective_from) <= periodEnd);
    return applicable[0] ?? profiles[0] ?? null;
}

function updatePayrollProfile() {
    router.post(route('admin.hr.payroll-profile.update'), payrollForm, inertiaOptions(
        { success: 'Payroll profile updated.', error: 'Could not update payroll profile.' },
        {
            onSuccess: () => {
                payrollForm.staff_user_id = null;
                payrollForm.base_salary = 0;
                payrollForm.wef_month = filterMonth.value;
                payrollForm.wef_year = filterYear.value;
                payrollForm.bank_name = '';
                payrollForm.bank_account_name = '';
                payrollForm.bank_account_number = '';
            },
        },
    ));
}

function storePayrollAllowance() {
    router.post(route('admin.hr.payroll-allowances.store'), allowanceForm, inertiaOptions(
        { success: 'Allowance added.', error: 'Could not add allowance.' },
        {
            onSuccess: () => {
                allowanceForm.staff_user_id = null;
                allowanceForm.title = '';
                allowanceForm.amount = 0;
                allowanceForm.wef_month = filterMonth.value;
                allowanceForm.wef_year = filterYear.value;
            },
        },
    ));
}

function removePayrollAllowance(item) {
    router.delete(route('admin.hr.payroll-allowances.destroy', item.id), inertiaOptions(
        { success: 'Allowance removed.', error: 'Could not remove allowance.' },
    ));
}

function startEditAllowance(item) {
    editAllowance.id = item.id;
    editAllowance.title = item.reference;
    editAllowance.amount = Number(item.amount || 0);
    const effective = item.effective_date ? new Date(item.effective_date) : new Date();
    editAllowance.wef_month = effective.getMonth() + 1;
    editAllowance.wef_year = effective.getFullYear();
}

function resetAllowanceEdit() {
    editAllowance.id = null;
    editAllowance.title = '';
    editAllowance.amount = null;
    editAllowance.wef_month = filterMonth.value;
    editAllowance.wef_year = filterYear.value;
}

function saveAllowanceEdit() {
    if (!editAllowance.id) return;
    router.put(route('admin.hr.payroll-allowances.update', editAllowance.id), {
        title: editAllowance.title,
        amount: editAllowance.amount,
        wef_month: editAllowance.wef_month,
        wef_year: editAllowance.wef_year,
    }, inertiaOptions(
        { success: 'Allowance updated.', error: 'Could not update allowance.' },
        { onSuccess: () => resetAllowanceEdit() },
    ));
}

function storePayrollDeduction() {
    const payload = {
        staff_user_id: deductionForm.staff_user_id,
        title: deductionForm.title,
        deduction_mode: deductionForm.deduction_mode,
        wef_month: deductionForm.wef_month,
        wef_year: deductionForm.wef_year,
        amount: deductionForm.deduction_mode === 'flat' ? deductionForm.amount : null,
        deduction_basis: deductionForm.deduction_mode === 'percentage' ? deductionForm.deduction_basis : null,
        deduction_percentage: deductionForm.deduction_mode === 'percentage' ? deductionForm.deduction_percentage : null,
        deduction_custom_base_amount: deductionForm.deduction_mode === 'percentage' && deductionForm.deduction_basis === 'custom_amount'
            ? deductionForm.deduction_custom_base_amount
            : null,
    };

    router.post(route('admin.hr.payroll-deductions.store'), payload, inertiaOptions(
        { success: 'Deduction added.', error: 'Could not add deduction.' },
        {
            onSuccess: () => {
                deductionForm.staff_user_id = null;
                deductionForm.title = '';
                deductionForm.wef_month = filterMonth.value;
                deductionForm.wef_year = filterYear.value;
                deductionForm.deduction_mode = 'flat';
                deductionForm.amount = null;
                deductionForm.deduction_basis = 'basic_salary';
                deductionForm.deduction_percentage = null;
                deductionForm.deduction_custom_base_amount = null;
            },
        },
    ));
}

function startEditDeduction(item) {
    editDeduction.id = item.id;
    editDeduction.title = item.reference;
    editDeduction.deduction_mode = item.deduction_mode || 'flat';
    editDeduction.amount = Number(item.amount || 0);
    editDeduction.deduction_basis = item.deduction_basis || 'basic_salary';
    editDeduction.deduction_percentage = Number(item.deduction_percentage || 0) || null;
    editDeduction.deduction_custom_base_amount = Number(item.deduction_custom_base_amount || 0) || null;
    const effective = item.effective_date ? new Date(item.effective_date) : new Date();
    editDeduction.wef_month = effective.getMonth() + 1;
    editDeduction.wef_year = effective.getFullYear();
}

function resetDeductionEdit() {
    editDeduction.id = null;
    editDeduction.title = '';
    editDeduction.wef_month = filterMonth.value;
    editDeduction.wef_year = filterYear.value;
    editDeduction.deduction_mode = 'flat';
    editDeduction.amount = null;
    editDeduction.deduction_basis = 'basic_salary';
    editDeduction.deduction_percentage = null;
    editDeduction.deduction_custom_base_amount = null;
}

function saveDeductionEdit() {
    if (!editDeduction.id) return;
    router.patch(route('admin.hr.payroll-deductions.update', editDeduction.id), {
        title: editDeduction.title,
        wef_month: editDeduction.wef_month,
        wef_year: editDeduction.wef_year,
        deduction_mode: editDeduction.deduction_mode,
        amount: editDeduction.deduction_mode === 'flat' ? editDeduction.amount : null,
        deduction_basis: editDeduction.deduction_mode === 'percentage' ? editDeduction.deduction_basis : null,
        deduction_percentage: editDeduction.deduction_mode === 'percentage' ? editDeduction.deduction_percentage : null,
        deduction_custom_base_amount: editDeduction.deduction_mode === 'percentage' && editDeduction.deduction_basis === 'custom_amount'
            ? editDeduction.deduction_custom_base_amount
            : null,
    }, inertiaOptions(
        { success: 'Deduction updated.', error: 'Could not update deduction.' },
        { onSuccess: () => resetDeductionEdit() },
    ));
}

function removePayrollDeduction(item) {
    router.delete(route('admin.hr.payroll-deductions.destroy', item.id), inertiaOptions(
        { success: 'Deduction removed.', error: 'Could not remove deduction.' },
    ));
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString();
}

function applyPeriodFilter() {
    payslipForm.month = filterMonth.value;
    payslipForm.year = filterYear.value;
    router.get(route('admin.hr.payments.index'), { year: filterYear.value, month: filterMonth.value }, { preserveState: true, preserveScroll: true, replace: true });
}

function openPayrollDrawer(staffId) {
    drawerStaffId.value = staffId;
    drawerOpen.value = true;
}

function startStaffEdit(staffId) {
    const profile = resolveProfileForPeriod(staffId);
    payrollForm.staff_user_id = staffId;
    allowanceForm.staff_user_id = staffId;
    deductionForm.staff_user_id = staffId;
    if (profile) {
        payrollForm.base_salary = Number(profile.base_salary || 0);
        const effective = profile.effective_from ? new Date(profile.effective_from) : new Date();
        payrollForm.wef_month = effective.getMonth() + 1;
        payrollForm.wef_year = effective.getFullYear();
    }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.25s ease;
}
.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}
</style>
