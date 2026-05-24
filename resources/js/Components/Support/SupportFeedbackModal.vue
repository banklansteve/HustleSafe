<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[90] flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                @click.self="$emit('close')"
            >
                <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <header class="border-b border-slate-100 px-5 py-4">
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Support feedback</p>
                        <h3 class="font-display text-lg font-black text-slate-900">{{ done ? 'Thank you' : stepTitle }}</h3>
                    </header>

                    <div v-if="done" class="px-5 py-8 text-center">
                        <p class="text-4xl" aria-hidden="true">✨</p>
                        <p class="mt-3 text-sm font-semibold text-slate-600">Your feedback helps us improve every conversation.</p>
                        <button
                            type="button"
                            class="mt-6 w-full rounded-xl bg-primary-700 py-3 text-sm font-black uppercase text-white"
                            @click="$emit('close')"
                        >
                            Done
                        </button>
                    </div>

                    <form v-else class="px-5 py-5" @submit.prevent="onContinue">
                        <div v-if="step === 'reaction'" class="space-y-4">
                            <p class="text-center text-sm font-semibold text-slate-600">How was your support experience?</p>
                            <div class="flex flex-wrap justify-center gap-2">
                                <button
                                    v-for="r in reactions"
                                    :key="r.key"
                                    type="button"
                                    class="flex w-14 flex-col items-center rounded-2xl border-2 py-2 transition hover:scale-105"
                                    :class="form.reaction === r.key ? 'border-primary-500 bg-primary-50' : 'border-slate-100'"
                                    @click="form.reaction = r.key"
                                >
                                    <span class="text-2xl">{{ r.emoji }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-else-if="surveyStep" class="space-y-3">
                            <p class="text-sm font-black text-slate-900">{{ surveyStep.question }}</p>
                            <label
                                v-for="opt in surveyStep.options"
                                :key="opt.value"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                                :class="form.answers[surveyStep.id] === opt.value ? 'border-primary-400 bg-primary-50' : 'border-slate-200'"
                            >
                                <input v-model="form.answers[surveyStep.id]" type="radio" :value="opt.value" class="text-primary-600" required />
                                {{ opt.label }}
                            </label>
                        </div>

                        <div v-else-if="step === 'score'" class="space-y-4">
                            <p class="text-center text-sm font-black text-slate-900">Rate your support agent (0–10)</p>
                            <div class="grid grid-cols-6 gap-1.5 sm:grid-cols-11">
                                <button
                                    v-for="n in 11"
                                    :key="n - 1"
                                    type="button"
                                    class="aspect-square rounded-lg text-xs font-black transition"
                                    :class="form.score === n - 1 ? 'bg-primary-700 text-white' : 'bg-slate-100 text-slate-700'"
                                    @click="form.score = n - 1"
                                >
                                    {{ n - 1 }}
                                </button>
                            </div>
                            <textarea
                                v-model="form.comment"
                                rows="2"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                placeholder="Optional comment…"
                            />
                        </div>

                        <p v-if="error" class="mt-3 text-xs font-semibold text-rose-600">{{ error }}</p>

                        <div class="mt-5 flex gap-2">
                            <button
                                v-if="stepIndex > 0"
                                type="button"
                                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-xs font-black uppercase text-slate-600"
                                @click="prevStep"
                            >
                                Back
                            </button>
                            <button
                                type="submit"
                                class="flex-1 rounded-xl bg-primary-700 py-2.5 text-xs font-black uppercase text-white disabled:opacity-50"
                                :disabled="!canContinue || submitting"
                            >
                                {{ isLastStep ? (submitting ? 'Sending…' : 'Submit') : 'Continue' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    ticketId: { type: [Number, String], default: null },
    reactions: { type: Array, default: () => [] },
    survey: { type: Array, default: () => [] },
    submitUrl: { type: String, required: true },
    initialReaction: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submitted']);

const form = reactive({
    reaction: '',
    answers: {},
    score: null,
    comment: '',
});

const done = ref(false);
const submitting = ref(false);
const error = ref('');
const stepIndex = ref(0);

const steps = computed(() => {
    const s = ['reaction'];
    for (const q of props.survey || []) {
        s.push(`survey:${q.id}`);
    }
    s.push('score');

    return s;
});

const step = computed(() => steps.value[stepIndex.value] ?? 'reaction');
const surveyStep = computed(() => {
    if (!String(step.value).startsWith('survey:')) {
        return null;
    }
    const id = step.value.split(':')[1];

    return (props.survey || []).find((q) => q.id === id) ?? null;
});

const stepTitle = computed(() => {
    if (step.value === 'reaction') {
        return 'Quick rating';
    }
    if (step.value === 'score') {
        return 'Agent rating';
    }

    return 'A few questions';
});

const isLastStep = computed(() => stepIndex.value >= steps.value.length - 1);

const canContinue = computed(() => {
    if (step.value === 'reaction') {
        return !!form.reaction;
    }
    if (surveyStep.value) {
        return !!form.answers[surveyStep.value.id];
    }
    if (step.value === 'score') {
        return form.score !== null && form.score >= 0;
    }

    return false;
});

watch(
    () => props.open,
    (v) => {
        if (!v) {
            return;
        }
        done.value = false;
        error.value = '';
        stepIndex.value = 0;
        form.reaction = props.initialReaction || '';
        form.answers = {};
        form.score = null;
        form.comment = '';
    },
);

function prevStep() {
    stepIndex.value = Math.max(0, stepIndex.value - 1);
}

async function onContinue() {
    if (!canContinue.value) {
        return;
    }
    if (!isLastStep.value) {
        stepIndex.value += 1;

        return;
    }

    if (!props.submitUrl) {
        error.value = 'Feedback is not available for this session.';

        return;
    }

    submitting.value = true;
    error.value = '';
    try {
        await window.axios.post(props.submitUrl, {
            reaction: form.reaction,
            answers: form.answers,
            score: form.score,
            comment: form.comment || null,
        });
        done.value = true;
        emit('submitted');
    } catch (e) {
        if (e.response?.status === 422 && e.response?.data?.already_rated) {
            done.value = true;
            emit('submitted');
        } else {
            error.value = e.response?.data?.message || 'Could not submit feedback. Try again.';
        }
    } finally {
        submitting.value = false;
    }
}
</script>
