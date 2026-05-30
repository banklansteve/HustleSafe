<template>
    <OperationsShell title="Account" subtitle="Manage profile details, security, privacy, and your leave and payroll workspace.">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-primary-50/40 p-5 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-4">
                        <img :src="user.avatar_url || fallbackAvatar" alt="Profile avatar" class="h-20 w-20 rounded-2xl border border-slate-200 object-cover shadow-sm" />
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-primary-700">Staff account</p>
                            <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-900">{{ user.name }}</h2>
                            <p class="text-sm font-semibold text-slate-600">{{ user.email }}</p>
                        </div>
                    </div>
                    <form class="flex flex-wrap items-center gap-2" @submit.prevent="submitAvatar">
                        <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp" @change="onAvatarChange" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700" />
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-700 px-5 py-3 text-sm font-black text-white disabled:opacity-70" :disabled="avatarForm.processing || !avatarForm.avatar">
                            <span v-if="avatarForm.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                            <span>Update picture</span>
                        </button>
                    </form>
                </div>
            </section>

            <section class="flex flex-wrap gap-2">
                <button v-for="tab in tabs" :key="tab.key" type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide" :class="activeTab === tab.key ? 'bg-primary-700 text-white' : 'border border-slate-300 bg-white text-slate-700'" @click="activeTab = tab.key">
                    {{ tab.label }}
                </button>
            </section>

            <section v-if="activeTab === 'profile'" class="grid gap-6 xl:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-black text-slate-900">Profile details</h3>
                        <button type="button" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800" @click="isEditingProfile = !isEditingProfile">
                            {{ isEditingProfile ? 'Cancel' : 'Edit' }}
                        </button>
                    </div>

                    <div v-if="!isEditingProfile" class="mt-4 space-y-2 text-sm font-semibold text-slate-700">
                        <p><span class="font-black text-slate-900">First name:</span> {{ user.first_name || '-' }}</p>
                        <p><span class="font-black text-slate-900">Last name:</span> {{ user.last_name || '-' }}</p>
                        <p><span class="font-black text-slate-900">Phone:</span> {{ user.phone || '-' }}</p>
                        <p><span class="font-black text-slate-900">State:</span> {{ user.state_name || '-' }}</p>
                        <p><span class="font-black text-slate-900">Local government:</span> {{ user.local_government_name || '-' }}</p>
                        <p><span class="font-black text-slate-900">City:</span> {{ user.city || '-' }}</p>
                        <p><span class="font-black text-slate-900">Bio:</span> {{ user.bio || '-' }}</p>
                    </div>

                    <form v-else class="mt-4 space-y-3" @submit.prevent="submitProfile">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input v-model="profileForm.first_name" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="First name" />
                            <input v-model="profileForm.last_name" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Last name" />
                            <input v-model="profileForm.phone" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Phone" />
                            <input v-model="profileForm.city" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="City" />
                            <select v-model="profileForm.state_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option value="">Select state</option>
                                <option v-for="state in locations" :key="`state-${state.id}`" :value="state.id">{{ state.name }}</option>
                            </select>
                            <select v-model="profileForm.local_government_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option value="">Select local government</option>
                                <option v-for="lg in localGovernmentOptions" :key="`lg-${lg.id}`" :value="lg.id">{{ lg.name }}</option>
                            </select>
                        </div>
                        <textarea v-model="profileForm.bio" rows="4" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Short bio..." />
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary-700 px-5 py-3 text-sm font-black text-white disabled:opacity-70" :disabled="profileForm.processing">
                            <span v-if="profileForm.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                            <span>Save profile</span>
                        </button>
                    </form>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-black text-slate-900">Security</h3>
                        <button type="button" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800" @click="isEditingPassword = !isEditingPassword">
                            {{ isEditingPassword ? 'Cancel' : 'Change password' }}
                        </button>
                    </div>
                    <form v-if="isEditingPassword" class="mt-4 space-y-3" @submit.prevent="submitPassword">
                        <input v-model="passwordForm.current_password" type="password" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Current password" />
                        <input v-model="passwordForm.password" type="password" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="New password" />
                        <input v-model="passwordForm.password_confirmation" type="password" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Confirm new password" />
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary-700 px-5 py-3 text-sm font-black text-white disabled:opacity-70" :disabled="passwordForm.processing">
                            <span v-if="passwordForm.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                            <span>Save new password</span>
                        </button>
                    </form>
                </article>
            </section>

            <section v-if="activeTab === 'privacy'" class="grid gap-6 xl:grid-cols-1">
                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitVisibility">
                    <h3 class="text-lg font-black text-slate-900">Profile visibility controls</h3>
                    <div class="mt-4 space-y-2">
                        <label v-for="key in visibilityKeys" :key="key" class="flex items-center justify-between rounded-xl border border-slate-200 p-3">
                            <span class="text-sm font-semibold capitalize text-slate-700">{{ prettifyKey(key) }}</span>
                            <input v-model="visibilityForm.settings[key]" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                        </label>
                    </div>
                    <button type="submit" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary-700 px-5 py-3 text-sm font-black text-white disabled:opacity-70" :disabled="visibilityForm.processing">
                        <span v-if="visibilityForm.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        <span>Save visibility</span>
                    </button>
                </form>
            </section>

            <section v-if="activeTab === 'leave'" class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
                </div>

                <div class="flex flex-wrap gap-2">
                    <a :href="route('operations.hr.exports.performance')" class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800">Download performance PDF</a>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitLeave">
                        <h3 class="text-lg font-black text-slate-900">Request leave</h3>
                        <div class="mt-4 grid gap-3 lg:grid-cols-3">
                            <select v-model="leaveForm.leave_type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option value="">Leave type</option>
                                <option value="annual">Annual leave</option>
                                <option value="sick">Sick leave</option>
                                <option value="emergency">Emergency leave</option>
                                <option value="unpaid">Unpaid leave</option>
                            </select>
                            <select v-model="leaveForm.duration_type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option value="full_day">Full day</option>
                                <option value="hours">Hours</option>
                                <option value="multiple_days">More than one day</option>
                            </select>
                            <OperationsDateInput v-model="leaveForm.start_date" />
                            <OperationsDateInput v-if="leaveForm.duration_type === 'multiple_days'" v-model="leaveForm.end_date" :min="leaveForm.start_date || ''" />
                            <input v-if="leaveForm.duration_type === 'hours'" v-model.number="leaveForm.hours_requested" type="number" min="1" max="23" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Number of hours" />
                        </div>
                        <textarea v-model="leaveForm.reason" rows="3" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Reason (optional)" />
                        <p v-if="leaveBalanceWarning" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900">{{ leaveBalanceWarning }}</p>
                        <p v-if="leaveForm.errors.leave_type" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800">{{ leaveForm.errors.leave_type }}</p>
                        <button type="submit" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary-700 px-5 py-3 text-sm font-black text-white disabled:opacity-70" :disabled="leaveForm.processing || !!leaveBalanceWarning">
                            <span v-if="leaveForm.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                            <span>Submit leave request</span>
                        </button>
                    </form>
                </div>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Recent leave requests</h3>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Your latest submissions and review outcomes.</p>
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
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="leaveStatusClass(row.status)">{{ row.status }}</span>
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
                </article>

                <div class="grid gap-6 xl:grid-cols-1">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-black text-slate-900">Team availability</h3>
                        <ul class="mt-4 space-y-2">
                            <li v-for="entry in teamLeaveCalendar" :key="entry.id" class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm">
                                <p class="font-black text-slate-900">{{ entry.staff?.name || 'Staff' }} · {{ entry.leave_type }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ formatLeaveDate(entry.start_date) }} to {{ formatLeaveDate(entry.end_date) }}</p>
                            </li>
                            <li v-if="!teamLeaveCalendar.length" class="rounded-xl border border-dashed border-slate-300 p-3 text-sm font-semibold text-slate-500">
                                No upcoming approved leave entries.
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <section v-if="activeTab === 'payroll'" class="space-y-6">
                <div class="grid gap-6 xl:grid-cols-2">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-black text-slate-900">Monthly pay summary</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <p class="text-xs font-black uppercase tracking-wide text-emerald-800">Earnings</p>
                                <ul class="mt-2 space-y-1 text-sm font-semibold text-slate-700">
                                    <li class="flex justify-between"><span>Basic salary</span><span>NGN {{ formatMoney(baseSalary) }}</span></li>
                                    <li v-for="item in payrollAllowances" :key="`allowance-${item.id}`" class="flex justify-between">
                                        <span>{{ item.reference || 'Allowance' }}</span>
                                        <span>NGN {{ formatMoney(item.amount) }}</span>
                                    </li>
                                </ul>
                                <p class="mt-3 border-t border-emerald-200 pt-2 text-sm font-black text-emerald-900">Total earnings: NGN {{ formatMoney(totalEarnings) }}</p>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                                <p class="text-xs font-black uppercase tracking-wide text-rose-800">Deductions</p>
                                <ul class="mt-2 space-y-1 text-sm font-semibold text-slate-700">
                                    <li v-for="item in payrollDeductions" :key="`deduction-${item.id}`" class="flex justify-between">
                                        <span>{{ item.reference || 'Deduction' }}</span>
                                        <span>NGN {{ formatMoney(resolveDeductionAmount(item)) }}</span>
                                    </li>
                                </ul>
                                <p class="mt-3 border-t border-rose-200 pt-2 text-sm font-black text-rose-900">Total deductions: NGN {{ formatMoney(totalDeductions) }}</p>
                            </div>
                        </div>
                        <div class="mt-4 rounded-xl border border-primary-200 bg-primary-50 p-4">
                            <p class="text-xs font-black uppercase tracking-wide text-primary-700">Total monthly pay</p>
                            <p class="mt-1 text-2xl font-black text-primary-900">NGN {{ formatMoney(netMonthlyPay) }}</p>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-black text-slate-900">Payslips</h3>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <select v-model.number="payslipDownloadForm.month" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                                <option v-for="month in monthOptions" :key="`slip-month-${month.value}`" :value="month.value">{{ month.label }}</option>
                            </select>
                            <input v-model.number="payslipDownloadForm.year" type="number" min="2020" max="2100" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                            <a :href="route('operations.account.payslips.download-by-period', { month: payslipDownloadForm.month, year: payslipDownloadForm.year })" class="inline-flex items-center justify-center rounded-full bg-primary-700 px-4 py-2.5 text-sm font-black text-white">
                                Download Payslip
                            </a>
                        </div>
                        <ul class="mt-4 space-y-2">
                            <li v-for="slip in payslips" :key="slip.id" class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm">
                                <div>
                                    <p class="font-black text-slate-900">{{ slip.month }}/{{ slip.year }}</p>
                                    <p class="text-xs font-semibold text-slate-600">Net: NGN {{ formatMoney(slip.net_pay) }}</p>
                                </div>
                                <a :href="route('operations.account.payslips.download-by-period', { month: slip.month, year: slip.year })" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-black uppercase tracking-wide text-slate-700">Download Payslip</a>
                            </li>
                            <li v-if="!payslips.length" class="rounded-xl border border-dashed border-slate-300 p-3 text-sm font-semibold text-slate-500">
                                Payslips will appear here once generated.
                            </li>
                        </ul>
                    </article>
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
import { useForm } from '@inertiajs/vue3';
import { useOperationsToast } from '@/composables/useOperationsToast';
import { formatLeaveDate, formatLeaveDateTime, formatLeaveRange, humanizeSlug } from '@/utils/formatHumanDateTime';
import { computed, onMounted, ref } from 'vue';

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
    user: { type: Object, required: true },
    visibility: { type: Object, default: () => ({}) },
    visibilityKeys: { type: Array, default: () => [] },
    avatarConfigured: { type: Boolean, default: false },
    balance: { type: Object, required: true },
    leaveRequests: { type: Array, default: () => [] },
    payrollProfile: { type: Object, default: null },
    payrollAllowances: { type: Array, default: () => [] },
    payrollDeductions: { type: Array, default: () => [] },
    payslips: { type: Array, default: () => [] },
    currentMonthAdjustments: { type: Array, default: () => [] },
    teamLeaveCalendar: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'profile', label: 'Profile & Security' },
    { key: 'privacy', label: 'Privacy' },
    { key: 'leave', label: 'Leave' },
    { key: 'payroll', label: 'Payroll' },
];

const activeTab = ref('profile');
const leaveQueue = useClientQueue(() => props.leaveRequests, {
    defaultSortKey: 'created_at',
    defaultSortDir: 'desc',
    searchFields: ['leave_type', 'status', 'reason', 'review_note'],
    perPage: 10,
});
const isEditingProfile = ref(false);
const isEditingPassword = ref(false);
const fallbackAvatar = computed(() => 'https://ui-avatars.com/api/?name=' + encodeURIComponent(props.user.name || 'Staff'));
const { toast } = useOperationsToast();

const profileForm = useForm({
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    name: props.user.name || '',
    phone: props.user.phone || '',
    headline: props.user.headline || '',
    bio: props.user.bio || '',
    profession: props.user.profession || '',
    job_title: props.user.job_title || '',
    city: props.user.city || '',
    state_id: props.user.state_id || '',
    local_government_id: props.user.local_government_id || '',
    address_line: props.user.address_line || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const visibilityForm = useForm({
    settings: { ...props.visibility },
});

const avatarForm = useForm({
    avatar: null,
});

const leaveForm = useForm({
    leave_type: '',
    duration_type: 'full_day',
    start_date: '',
    end_date: '',
    hours_requested: '',
    reason: '',
});
const payslipDownloadForm = useForm({
    month: new Date().getMonth() + 1,
    year: new Date().getFullYear(),
});
const monthOptions = computed(() => Array.from({ length: 12 }, (_, index) => ({
    value: index + 1,
    label: new Date(2000, index, 1).toLocaleString('en-NG', { month: 'long' }),
})));

const localGovernmentOptions = computed(() => {
    const state = props.locations.find((item) => Number(item.id) === Number(profileForm.state_id));
    return state?.local_governments || [];
});
const baseSalary = computed(() => Number(props.payrollProfile?.base_salary || 0));
const totalAllowances = computed(() => (props.payrollAllowances || []).reduce((sum, item) => sum + Number(item.amount || 0), 0));
const totalEarnings = computed(() => baseSalary.value + totalAllowances.value);
const totalDeductions = computed(() => (props.payrollDeductions || []).reduce((sum, item) => sum + resolveDeductionAmount(item), 0));
const netMonthlyPay = computed(() => totalEarnings.value - totalDeductions.value);

function submitProfile() {
    profileForm.patch(route('operations.account.details'), {
        onSuccess: () => {
            isEditingProfile.value = false;
            toast('Profile updated.');
        },
    });
}

function submitPassword() {
    passwordForm.put(route('password.update'), {
        onSuccess: () => {
            passwordForm.reset();
            isEditingPassword.value = false;
            toast('Password updated.');
        },
    });
}

function submitVisibility() {
    visibilityForm.patch(route('operations.account.visibility'), {
        onSuccess: () => toast('Visibility settings saved.'),
    });
}

function onAvatarChange(event) {
    avatarForm.avatar = event.target.files?.[0] || null;
}

function submitAvatar() {
    if (!avatarForm.avatar) return;

    avatarForm.post(route('operations.account.avatar'), {
        forceFormData: true,
        onSuccess: () => {
            avatarForm.reset();
            toast('Profile photo updated.');
        },
    });
}

function submitLeave() {
    leaveForm.post(route('operations.account.leave-requests.store'), {
        onSuccess: () => {
            leaveForm.reset();
            leaveForm.duration_type = 'full_day';
            leaveForm.hours_requested = '';
            toast('Leave request submitted.');
        },
    });
}

function prettifyKey(key) {
    return String(key).replaceAll('_', ' ');
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString();
}

function resolveDeductionAmount(item) {
    if ((item?.deduction_mode || '') !== 'percentage') {
        return Number(item?.amount || 0);
    }
    const percentage = Number(item?.deduction_percentage || 0) / 100;
    if (item?.deduction_basis === 'total_pay') {
        return totalEarnings.value * percentage;
    }
    if (item?.deduction_basis === 'custom_amount') {
        return Number(item?.deduction_custom_base_amount || 0) * percentage;
    }
    return baseSalary.value * percentage;
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

function leaveStatusClass(status) {
    if (status === 'approved') {
        return 'bg-emerald-100 text-emerald-800';
    }
    if (status === 'rejected') {
        return 'bg-rose-100 text-rose-800';
    }

    return 'bg-amber-100 text-amber-800';
}

function remainingForType(type) {
    const map = {
        annual: props.balance.annual_days - props.balance.annual_days_used,
        sick: props.balance.sick_days - props.balance.sick_days_used,
        emergency: props.balance.emergency_days - props.balance.emergency_days_used,
        unpaid: props.balance.unpaid_days - props.balance.unpaid_days_used,
    };

    return map[type] ?? 0;
}

function requestedLeaveDays() {
    if (!leaveForm.start_date) {
        return 0;
    }
    if (leaveForm.duration_type === 'multiple_days' && leaveForm.end_date) {
        const start = new Date(`${leaveForm.start_date}T00:00:00`);
        const end = new Date(`${leaveForm.end_date}T00:00:00`);
        const diff = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;

        return Math.max(2, diff);
    }

    return 1;
}

const leaveBalanceWarning = computed(() => {
    if (!leaveForm.leave_type || !leaveForm.start_date) {
        return '';
    }

    const needed = requestedLeaveDays();
    const available = remainingForType(leaveForm.leave_type);
    if (needed <= available) {
        return '';
    }

    const alternates = ['annual', 'sick', 'emergency', 'unpaid']
        .filter((type) => type !== leaveForm.leave_type && remainingForType(type) >= needed)
        .map((type) => `${type.charAt(0).toUpperCase()}${type.slice(1)} (${remainingForType(type)} days)`);

    const base = `This request needs ${needed} day(s), but you only have ${available} day(s) of ${leaveForm.leave_type} leave left this year.`;
    if (!alternates.length) {
        return `${base} Contact HR if you need your balance adjusted.`;
    }

    return `${base} Try ${alternates.join(', ')} instead.`;
});

onMounted(() => {
    const tab = new URLSearchParams(window.location.search).get('tab');
    if (tab && tabs.some((entry) => entry.key === tab)) {
        activeTab.value = tab;
    }
});
</script>
