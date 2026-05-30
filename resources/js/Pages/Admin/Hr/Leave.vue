<template>
    <AdminShell title="Leave management" subtitle="Approve requests and manage leave balances for staff admins.">
        <div class="space-y-6">
            <section class="grid gap-6 xl:grid-cols-2">
                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitAllocate">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-slate-900">Set leave allocation</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">First-time setup for a staff member, year, and leave type.</p>
                        </div>
                        <span class="rounded-full bg-primary-50 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-primary-800">Initial setup</span>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model.number="allocateForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" required>
                            <option :value="null">Select staff</option>
                            <option v-for="member in staff" :key="`alloc-staff-${member.id}`" :value="member.id">{{ member.name }}</option>
                        </select>
                        <input v-model.number="allocateForm.year" type="number" min="2020" max="2100" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" required />
                        <select v-model="allocateForm.leave_type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" required>
                            <option value="">Leave type</option>
                            <option v-for="type in leaveTypes" :key="`alloc-type-${type.value}`" :value="type.value">{{ type.label }}</option>
                        </select>
                        <input v-model.number="allocateForm.days" type="number" min="1" max="365" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Days to allocate" required />
                    </div>
                    <textarea v-model="allocateForm.reason" rows="2" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Reason for allocation..." required />
                    <p v-if="allocatePreview" class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                        {{ allocatePreview }}
                    </p>
                    <button type="submit" class="mt-3 inline-flex items-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white disabled:opacity-70" :disabled="allocatingLeave || !canAllocate">
                        <span v-if="allocatingLeave" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        Save allocation
                    </button>
                    <p v-if="allocateForm.staff_user_id && allocateForm.leave_type && !canAllocate" class="mt-2 text-xs font-semibold text-amber-700">
                        This leave type is already allocated. Use the adjust form to add or remove days.
                    </p>
                </form>

                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitAdjust">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-slate-900">Adjust leave balance</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Add to or remove from an existing allocation.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-slate-700">Add / remove</span>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model.number="adjustForm.staff_user_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" required>
                            <option :value="null">Select staff</option>
                            <option v-for="member in staff" :key="`adj-staff-${member.id}`" :value="member.id">{{ member.name }}</option>
                        </select>
                        <input v-model.number="adjustForm.year" type="number" min="2020" max="2100" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" required />
                        <select v-model="adjustForm.leave_type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" required>
                            <option value="">Leave type</option>
                            <option v-for="type in leaveTypes" :key="`adj-type-${type.value}`" :value="type.value">{{ type.label }}</option>
                        </select>
                        <input v-model.number="adjustForm.days" type="number" min="1" max="365" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Number of days" required />
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="option in adjustmentDirections"
                            :key="option.value"
                            type="button"
                            class="rounded-full px-4 py-2.5 text-sm font-black transition"
                            :class="adjustForm.adjustment_direction === option.value ? 'bg-primary-700 text-white' : 'border border-slate-300 bg-white text-slate-700'"
                            @click="adjustForm.adjustment_direction = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <textarea v-model="adjustForm.reason" rows="2" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Reason for adjustment..." required />
                    <p v-if="adjustPreview" class="mt-3 rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-900">
                        {{ adjustPreview }}
                    </p>
                    <button type="submit" class="mt-3 inline-flex items-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white disabled:opacity-70" :disabled="adjustingLeave || !canAdjust">
                        <span v-if="adjustingLeave" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        Apply adjustment
                    </button>
                    <p v-if="adjustForm.staff_user_id && adjustForm.leave_type && !canAdjust" class="mt-2 text-xs font-semibold text-amber-700">
                        No allocation found for this selection. Set the initial allocation first.
                    </p>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Leave approvals</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-xs font-black uppercase tracking-wide text-slate-600">
                                <th class="px-4 py-3">Staff</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Range</th>
                                <th class="px-4 py-3">Duration</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Review note</th>
                                <th class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="row in leaveRequests" :key="row.id">
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ row.staff?.name || 'Unknown' }}</td>
                                <td class="px-4 py-3 capitalize text-slate-700">{{ row.leave_type }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ formatLeaveRange(row) }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-700">{{ formatLeaveDurationRequested(row) }}</td>
                                <td class="px-4 py-3 capitalize text-slate-700">{{ row.status }}</td>
                                <td class="px-4 py-3 text-xs font-semibold text-slate-600">{{ row.review_note || '—' }}</td>
                                <td class="px-4 py-3">
                                    <div v-if="row.status === 'pending'" class="flex flex-wrap gap-2">
                                        <button type="button" class="inline-flex items-center gap-2 rounded-full bg-primary-700 px-3 py-2.5 text-sm font-black text-white disabled:opacity-70" :disabled="busyRowId === row.id" @click="approveLeave(row)">
                                            <span v-if="busyRowId === row.id && busyAction === 'approved'" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                            Approve
                                        </button>
                                        <button type="button" class="rounded-full border border-slate-300 bg-white px-3 py-2.5 text-sm font-black text-slate-800" @click="openRejectModal(row)">Reject</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!leaveRequests.length">
                                <td class="px-4 py-6 text-sm font-semibold text-slate-500" colspan="7">No leave requests yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-6">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Staff leave balances</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50">
                                <tr class="text-xs font-black uppercase tracking-wide text-slate-600">
                                    <th class="px-4 py-3">Staff</th>
                                    <th class="px-4 py-3">Year</th>
                                    <th class="px-4 py-3">Annual</th>
                                    <th class="px-4 py-3">Sick</th>
                                    <th class="px-4 py-3">Emergency</th>
                                    <th class="px-4 py-3">Unpaid</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="row in staffLeaveBalances" :key="row.id">
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ row.staff?.name || 'Unknown' }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ row.year }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ leaveBalanceSummary(row, 'annual') }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ leaveBalanceSummary(row, 'sick') }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ leaveBalanceSummary(row, 'emergency') }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ leaveBalanceSummary(row, 'unpaid') }}</td>
                                </tr>
                                <tr v-if="!staffLeaveBalances.length">
                                    <td class="px-4 py-6 text-sm font-semibold text-slate-500" colspan="6">No leave balances recorded yet. Set allocations above to get started.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Approved leave calendar</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50">
                                <tr class="text-xs font-black uppercase tracking-wide text-slate-600">
                                    <th class="px-4 py-3">Staff</th>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3">Dates</th>
                                    <th class="px-4 py-3">Duration</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="row in leaveCalendar" :key="row.id">
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ row.staff?.name || 'Unknown' }}</td>
                                    <td class="px-4 py-3 capitalize text-slate-700">{{ row.leave_type }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ formatLeaveCalendarDates(row) }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ formatCalendarDuration(row) }}</td>
                                </tr>
                                <tr v-if="!leaveCalendar.length">
                                    <td class="px-4 py-6 text-sm font-semibold text-slate-500" colspan="4">No approved upcoming leave on the calendar.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </div>

        <AppConfirmModal
            :show="showRejectModal"
            :busy="busyRowId === rejectTarget?.id && busyAction === 'rejected'"
            title="Reject leave request?"
            description="This action will notify the staff admin. A review note is required."
            confirm-label="Reject request"
            :show-note="true"
            :note="rejectNote"
            note-label="Rejection note"
            note-placeholder="Provide a clear reason for rejection..."
            @update:note="rejectNote = $event"
            @cancel="closeRejectModal"
            @confirm="confirmReject"
        />
    </AdminShell>
</template>

<script setup>
import AppConfirmModal from '@/Components/AppConfirmModal.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { useAdminHrInertia } from '@/composables/useAdminHrInertia';
import { formatLeaveCalendarDates, formatLeaveDurationRequested, formatLeaveRange } from '@/utils/formatHumanDateTime';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    staff: { type: Array, default: () => [] },
    leaveRequests: { type: Array, default: () => [] },
    leaveCalendar: { type: Array, default: () => [] },
    staffLeaveBalances: { type: Array, default: () => [] },
});

const leaveTypes = [
    { value: 'annual', label: 'Annual' },
    { value: 'sick', label: 'Sick' },
    { value: 'emergency', label: 'Emergency' },
    { value: 'unpaid', label: 'Unpaid' },
];

const adjustmentDirections = [
    { value: 'add', label: 'Add to balance' },
    { value: 'remove', label: 'Remove from balance' },
];

const allocateForm = reactive({
    staff_user_id: null,
    year: new Date().getFullYear(),
    leave_type: '',
    days: 1,
    reason: '',
});

const adjustForm = reactive({
    staff_user_id: null,
    year: new Date().getFullYear(),
    leave_type: '',
    adjustment_direction: 'add',
    days: 1,
    reason: '',
});

const { inertiaOptions } = useAdminHrInertia();
const allocatingLeave = ref(false);
const adjustingLeave = ref(false);
const showRejectModal = ref(false);
const rejectTarget = ref(null);
const rejectNote = ref('');
const busyRowId = ref(null);
const busyAction = ref('');

function findBalance(staffUserId, year) {
    if (!staffUserId || !year) {
        return null;
    }

    return props.staffLeaveBalances.find(
        (row) => Number(row.staff_user_id) === Number(staffUserId) && Number(row.year) === Number(year),
    ) ?? null;
}

function assignedForType(balanceRow, leaveType) {
    if (!balanceRow || !leaveType) {
        return 0;
    }

    return Number(balanceRow[`${leaveType}_days`] ?? 0);
}

function usedForType(balanceRow, leaveType) {
    if (!balanceRow || !leaveType) {
        return 0;
    }

    return Number(balanceRow[`${leaveType}_days_used`] ?? 0);
}

const canAllocate = computed(() => {
    if (!allocateForm.staff_user_id || !allocateForm.leave_type) {
        return false;
    }

    const balance = findBalance(allocateForm.staff_user_id, allocateForm.year);

    return assignedForType(balance, allocateForm.leave_type) <= 0;
});

const canAdjust = computed(() => {
    if (!adjustForm.staff_user_id || !adjustForm.leave_type) {
        return false;
    }

    const balance = findBalance(adjustForm.staff_user_id, adjustForm.year);

    return assignedForType(balance, adjustForm.leave_type) > 0;
});

const allocatePreview = computed(() => {
    if (!allocateForm.staff_user_id || !allocateForm.leave_type) {
        return '';
    }

    const member = props.staff.find((item) => Number(item.id) === Number(allocateForm.staff_user_id));
    const typeLabel = leaveTypes.find((item) => item.value === allocateForm.leave_type)?.label ?? allocateForm.leave_type;

    return `${member?.name ?? 'Staff'} will receive ${allocateForm.days} ${typeLabel.toLowerCase()} day(s) for ${allocateForm.year}.`;
});

const adjustPreview = computed(() => {
    if (!adjustForm.staff_user_id || !adjustForm.leave_type) {
        return '';
    }

    const balance = findBalance(adjustForm.staff_user_id, adjustForm.year);
    const assigned = assignedForType(balance, adjustForm.leave_type);
    const used = usedForType(balance, adjustForm.leave_type);
    const remaining = Math.max(0, assigned - used);
    const typeLabel = leaveTypes.find((item) => item.value === adjustForm.leave_type)?.label ?? adjustForm.leave_type;
    const direction = adjustForm.adjustment_direction === 'add' ? 'add' : 'remove';
    const projected = direction === 'add'
        ? remaining + Number(adjustForm.days || 0)
        : Math.max(0, remaining - Number(adjustForm.days || 0));

    return `${remaining} ${typeLabel.toLowerCase()} day(s) remaining now. After you ${direction} ${adjustForm.days} day(s), ${projected} day(s) will remain available.`;
});

function submitAllocate() {
    if (!canAllocate.value) {
        return;
    }

    allocatingLeave.value = true;
    router.post(route('admin.hr.leave-balances.adjust'), {
        ...allocateForm,
        mode: 'allocate',
    }, inertiaOptions(
        { success: 'Leave allocation saved.', error: 'Could not save leave allocation.' },
        {
            onSuccess: () => {
                allocateForm.staff_user_id = null;
                allocateForm.leave_type = '';
                allocateForm.days = 1;
                allocateForm.reason = '';
            },
            onFinish: () => {
                allocatingLeave.value = false;
            },
        },
    ));
}

function submitAdjust() {
    if (!canAdjust.value) {
        return;
    }

    adjustingLeave.value = true;
    router.post(route('admin.hr.leave-balances.adjust'), {
        ...adjustForm,
        mode: 'adjust',
    }, inertiaOptions(
        { success: 'Leave balance adjusted.', error: 'Could not adjust leave balance.' },
        {
            onSuccess: () => {
                adjustForm.days = 1;
                adjustForm.reason = '';
            },
            onFinish: () => {
                adjustingLeave.value = false;
            },
        },
    ));
}

function approveLeave(row) {
    busyRowId.value = row.id;
    busyAction.value = 'approved';
    router.post(route('admin.hr.leave-requests.review', row.id), { status: 'approved', review_note: 'Approved by super admin.' }, inertiaOptions(
        { success: 'Leave request approved.', error: 'Could not approve leave request.' },
        {
            onFinish: () => {
                busyRowId.value = null;
                busyAction.value = '';
            },
        },
    ));
}

function openRejectModal(row) {
    rejectTarget.value = row;
    rejectNote.value = '';
    showRejectModal.value = true;
}

function closeRejectModal() {
    showRejectModal.value = false;
    rejectTarget.value = null;
    rejectNote.value = '';
}

function confirmReject() {
    if (!rejectTarget.value || !String(rejectNote.value).trim()) return;
    busyRowId.value = rejectTarget.value.id;
    busyAction.value = 'rejected';
    router.post(route('admin.hr.leave-requests.review', rejectTarget.value.id), {
        status: 'rejected',
        review_note: rejectNote.value.trim(),
    }, inertiaOptions(
        { success: 'Leave request rejected.', error: 'Could not reject leave request.' },
        {
            onSuccess: () => {
                closeRejectModal();
            },
            onFinish: () => {
                busyRowId.value = null;
                busyAction.value = '';
            },
        },
    ));
}

function leaveBalanceSummary(row, type) {
    const assigned = Number(row?.[`${type}_days`] ?? 0);
    const used = Number(row?.[`${type}_days_used`] ?? 0);
    const balance = Math.max(0, assigned - used);

    if (assigned <= 0) {
        return 'Not set';
    }

    return `${balance} left · ${assigned} assigned · ${used} taken`;
}

function formatCalendarDuration(row) {
    if (row?.duration_type === 'hours') {
        return `${row?.hours_requested || 0} hour(s)`;
    }

    const days = Number(row?.days_requested || 1);

    return days === 1 ? '1 day' : `${days} days`;
}
</script>
