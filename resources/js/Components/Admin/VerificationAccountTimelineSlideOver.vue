<template>
    <AdminSlideOver
        :open="open"
        :title="account?.name || 'Account verifications'"
        :eyebrow="typeLabel ? `${typeLabel} · attempt history` : 'Verification timeline'"
        width-class="max-w-full sm:max-w-3xl xl:max-w-5xl"
        panel-class="bg-white text-slate-950"
        @close="$emit('close')"
    >
        <div v-if="loading" class="py-16 text-center text-sm font-semibold text-slate-500">Loading account history…</div>
        <p v-else-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-900">{{ error }}</p>
        <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,16rem)_minmax(0,1fr)]">
            <div class="space-y-3">
                <div v-if="account" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="font-black text-slate-900">{{ account.name }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ account.email }}</p>
                    <p v-if="account.role_label" class="mt-2 text-[10px] font-black uppercase tracking-wide text-primary-800">{{ account.role_label }}</p>
                </div>
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Chronological history</p>
                <ol class="space-y-2">
                    <li v-for="event in timeline" :key="event.id">
                        <button
                            type="button"
                            class="w-full rounded-2xl border px-3 py-3 text-left transition"
                            :class="selectedEventId === event.id ? 'border-primary-300 bg-primary-50 ring-1 ring-primary-200' : 'border-slate-200 bg-white hover:border-primary-200'"
                            @click="selectEvent(event)"
                        >
                            <p class="text-xs font-black text-slate-900">{{ event.label }}</p>
                            <p class="mt-1 text-[10px] font-bold uppercase tracking-wide" :class="statusClass(event.status)">
                                {{ event.status_label || labelize(event.status) }}
                                <span v-if="event.attempt_number"> · Attempt {{ event.attempt_number }}</span>
                            </p>
                            <p class="mt-1 text-[10px] font-semibold text-slate-500">{{ event.submitted_at_label || '—' }}</p>
                        </button>
                    </li>
                </ol>
                <p v-if="!timeline.length" class="text-sm font-semibold text-slate-500">No verification events recorded yet.</p>
            </div>

            <div class="min-w-0">
                <div v-if="selectedPresentation" class="rounded-2xl border border-slate-200 bg-white p-1">
                    <VerificationReviewPanel
                        :presentation="selectedPresentation"
                        :can-decide="canDecideSelected"
                        :decide-url="decideUrl"
                        :decision-reasons="decisionReasons"
                        @decided="$emit('decided', $event)"
                    />
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-200 px-6 py-16 text-center text-sm font-semibold text-slate-500">
                    Select a timeline entry to review documents and record a decision.
                </div>
            </div>
        </div>
    </AdminSlideOver>
</template>

<script setup>
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import VerificationReviewPanel from '@/Components/Verification/VerificationReviewPanel.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    userId: { type: Number, default: null },
    typeKey: { type: String, default: '' },
    typeLabel: { type: String, default: '' },
    initialVerificationId: { type: Number, default: null },
    decisionReasons: { type: Array, default: () => [] },
});

defineEmits(['close', 'decided']);

const loading = ref(false);
const error = ref('');
const account = ref(null);
const timeline = ref([]);
const selectedEventId = ref(null);
const selectedPresentation = ref(null);

const canDecideSelected = computed(() => {
    const status = selectedPresentation.value?.status;

    return ['pending', 'in_review', 'flagged', 'unverified', 'rejected', 'approved', 'verified'].includes(status);
});

const decideUrl = computed(() => {
    const id = selectedPresentation.value?.id;

    return id ? route('admin.verification-engine.verifications.decision', id) : '';
});

watch(
    () => [props.open, props.userId, props.typeKey, props.initialVerificationId],
    async ([isOpen, userId]) => {
        if (!isOpen || !userId) {
            return;
        }

        await loadTimeline(userId);
    },
    { immediate: true },
);

async function loadTimeline(userId) {
    loading.value = true;
    error.value = '';
    try {
        const params = props.typeKey ? { type_key: props.typeKey } : {};
        const { data } = await window.axios.get(route('admin.verification-engine.users.timeline', userId), { params });
        account.value = data.user ?? null;
        timeline.value = data.timeline ?? [];

        const preferred =
            timeline.value.find((e) => e.verification_id === props.initialVerificationId)
            ?? [...timeline.value].reverse().find((e) => e.kind === 'verification')
            ?? timeline.value[0];

        if (preferred) {
            selectEvent(preferred);
        }
    } catch {
        error.value = 'Could not load this account’s verification history.';
        account.value = null;
        timeline.value = [];
    } finally {
        loading.value = false;
    }
}

function selectEvent(event) {
    selectedEventId.value = event.id;
    selectedPresentation.value = event.presentation ?? null;
}

function labelize(value) {
    return String(value || '').replace(/_/g, ' ');
}

function statusClass(status) {
    if (['approved', 'verified'].includes(status)) {
        return 'text-emerald-700';
    }
    if (['rejected', 'expired'].includes(status)) {
        return 'text-rose-700';
    }
    if (status === 'unverified') {
        return 'text-amber-800';
    }

    return 'text-slate-600';
}
</script>
