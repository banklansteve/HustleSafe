<template>
    <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submit">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-black text-slate-900">Assign role groups</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Select one or more operational groups for the same staff admin.</p>
            </div>
            <span v-if="form.role_groups.length" class="rounded-full bg-primary-50 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-primary-800">
                {{ form.role_groups.length }} selected
            </span>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            <label class="block">
                <span class="text-xs font-black uppercase tracking-wide text-slate-500">Staff admin</span>
                <select
                    v-model.number="form.staff_user_id"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold"
                    required
                >
                    <option :value="null">Select staff</option>
                    <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }} ({{ member.email }})</option>
                </select>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide text-slate-500">Starts on</span>
                    <AdminDateInput v-model="form.starts_on" wrapper-class="mt-1" button-class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900" />
                </label>
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide text-slate-500">Ends on</span>
                    <AdminDateInput v-model="form.ends_on" wrapper-class="mt-1" button-class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900" />
                </label>
            </div>
        </div>

        <div class="mt-4">
            <p class="text-xs font-black uppercase tracking-wide text-slate-500">Role groups</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <label
                    v-for="group in roleGroups"
                    :key="group.value"
                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                    :class="isSelected(group.value) ? 'border-primary-400 bg-primary-50 ring-2 ring-primary-100' : 'border-slate-200 bg-white hover:border-slate-300'"
                >
                    <input
                        v-model="form.role_groups"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary-700 focus:ring-primary-500"
                        :value="group.value"
                    />
                    <span class="min-w-0">
                        <span class="block text-sm font-black text-slate-900">{{ group.label }}</span>
                        <span class="mt-0.5 block text-xs font-semibold leading-snug text-slate-500">{{ group.description }}</span>
                    </span>
                </label>
            </div>
            <p v-if="form.staff_user_id && !form.role_groups.length" class="mt-2 text-xs font-semibold text-amber-700">
                Select at least one role group to save this assignment.
            </p>
        </div>

        <textarea
            v-model="form.reason"
            rows="2"
            class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold"
            placeholder="Reason for this assignment..."
            required
        />

        <button
            type="submit"
            class="mt-3 inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white disabled:opacity-70"
            :disabled="assigning || !canSubmit"
        >
            <span v-if="assigning" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
            <span>Save role assignments</span>
        </button>
    </form>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import { useAdminHrInertia } from '@/composables/useAdminHrInertia';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    staff: { type: Array, default: () => [] },
    activeAssignments: { type: Array, default: () => [] },
});

const roleGroups = [
    {
        value: 'group_a_chat_communications',
        label: 'Group A — Chat & Communications',
        description: 'Support queues, team chat, and customer communications.',
    },
    {
        value: 'group_b_moderation_operations',
        label: 'Group B — Moderation Operations',
        description: 'Content moderation, reviews, patrol, and onboarding quality.',
    },
    {
        value: 'group_c_people_trust_management',
        label: 'Group C — People & Trust',
        description: 'User management, verifications, trust, and onboarding.',
    },
    {
        value: 'group_d_financial_disputes_casework',
        label: 'Group D — Financial & Disputes',
        description: 'Disputes, payments, escrow anomalies, and payout exceptions.',
    },
];

const form = reactive({
    staff_user_id: null,
    role_groups: [],
    starts_on: '',
    ends_on: '',
    reason: '',
});

const { inertiaOptions } = useAdminHrInertia();
const assigning = ref(false);

const canSubmit = computed(() => Boolean(form.staff_user_id && form.role_groups.length && form.starts_on && String(form.reason).trim()));

watch(
    () => form.staff_user_id,
    (staffUserId) => {
        if (!staffUserId) {
            form.role_groups = [];
            form.starts_on = '';
            form.ends_on = '';
            return;
        }

        const assignments = props.activeAssignments.filter(
            (row) => Number(row.staff_user_id) === Number(staffUserId) && row.status === 'active',
        );

        form.role_groups = [...new Set(assignments.map((row) => row.role_group).filter(Boolean))];

        const primary = assignments[0];
        form.starts_on = primary?.starts_on ? String(primary.starts_on).slice(0, 10) : form.starts_on;
        form.ends_on = primary?.ends_on ? String(primary.ends_on).slice(0, 10) : '';
    },
);

function isSelected(value) {
    return form.role_groups.includes(value);
}

function submit() {
    if (!canSubmit.value) {
        return;
    }

    assigning.value = true;
    router.post(route('admin.hr.role-group.assign'), {
        staff_user_id: form.staff_user_id,
        role_groups: form.role_groups,
        starts_on: form.starts_on,
        ends_on: form.ends_on || null,
        reason: form.reason.trim(),
    }, inertiaOptions(
        { success: 'Role group assignments saved.', error: 'Could not save role group assignments.' },
        {
            onFinish: () => {
                assigning.value = false;
            },
        },
    ));
}
</script>
