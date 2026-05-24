<template>
    <div class="min-h-screen bg-gradient-to-br from-primary-50 via-white to-teal-50/80 px-4 py-10 sm:py-14">
        <div class="mx-auto w-full max-w-lg">
            <div class="overflow-hidden rounded-[1.75rem] border border-primary-100/80 bg-white shadow-xl shadow-primary-900/10 ring-1 ring-primary-50">
                <header class="bg-gradient-to-r from-primary-800 via-primary-700 to-teal-600 px-6 py-8 text-white">
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-primary-100">HustleSafe Support</p>
                    <h1 class="font-display mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                        {{ done ? 'Thank you' : 'Share your experience' }}
                    </h1>
                    <p class="mt-2 text-sm font-semibold text-primary-100/90">
                        {{ ticket.subject }}
                    </p>
                </header>

                <div v-if="ticket.already_rated || done" class="px-6 py-10 text-center">
                    <p class="text-5xl" aria-hidden="true">✨</p>
                    <p class="mt-4 text-lg font-black text-slate-900">Feedback received</p>
                    <p class="mt-2 text-sm font-semibold text-slate-600">
                        You can only submit one review per support session. We appreciate you taking the time.
                    </p>
                    <p v-if="displayScore" class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-primary-50 px-5 py-3 text-primary-900 ring-1 ring-primary-100">
                        <span class="text-3xl font-black">{{ displayScore }}</span>
                        <span class="text-left text-xs font-bold uppercase tracking-wide text-primary-700">out of 10</span>
                    </p>
                    <a
                        href="/"
                        class="mt-8 inline-flex rounded-xl bg-primary-700 px-6 py-3 text-sm font-black uppercase text-white shadow-md hover:bg-primary-800"
                    >
                        Back to HustleSafe
                    </a>
                </div>

                <template v-else>
                    <div class="border-b border-slate-100 px-6 py-4">
                        <div class="flex gap-1.5">
                            <div
                                v-for="(label, idx) in stepLabels"
                                :key="label"
                                class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"
                            >
                                <div
                                    class="h-full rounded-full bg-primary-600 transition-all duration-300"
                                    :class="idx <= stepIndex ? 'w-full' : 'w-0'"
                                />
                            </div>
                        </div>
                        <p class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Step {{ stepIndex + 1 }} of {{ totalSteps }}
                        </p>
                    </div>

                    <form class="px-6 py-6" @submit.prevent="onContinue">
                        <!-- Reaction -->
                        <div v-if="currentStep === 'reaction'" class="space-y-5">
                            <p class="text-center text-sm font-semibold text-slate-600">
                                How are you feeling about this support session?
                            </p>
                            <div class="flex flex-wrap justify-center gap-3">
                                <button
                                    v-for="r in reactions"
                                    :key="r.key"
                                    type="button"
                                    class="flex w-[4.5rem] flex-col items-center gap-1 rounded-2xl border-2 px-2 py-3 transition hover:scale-105 active:scale-95"
                                    :class="form.reaction === r.key ? 'border-primary-500 bg-primary-50 shadow-md' : 'border-slate-100 bg-slate-50/80'"
                                    @click="form.reaction = r.key"
                                >
                                    <span class="text-3xl" aria-hidden="true">{{ r.emoji }}</span>
                                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-600">{{ r.label }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Survey questions -->
                        <div v-else-if="surveyStep" class="space-y-4">
                            <h2 class="font-display text-lg font-black text-slate-900">
                                {{ surveyStep.question }}
                            </h2>
                            <div class="space-y-2">
                                <label
                                    v-for="opt in surveyStep.options"
                                    :key="opt.value"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition"
                                    :class="form.answers[surveyStep.id] === opt.value ? 'border-primary-400 bg-primary-50 ring-1 ring-primary-200' : 'border-slate-200 hover:border-primary-200'"
                                >
                                    <input
                                        v-model="form.answers[surveyStep.id]"
                                        type="radio"
                                        :value="opt.value"
                                        class="h-4 w-4 border-slate-300 text-primary-600 focus:ring-primary-500"
                                        required
                                    />
                                    <span class="text-sm font-semibold text-slate-800">{{ opt.label }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Score 1-10 -->
                        <div v-else-if="currentStep === 'score'" class="space-y-5">
                            <h2 class="text-center font-display text-lg font-black text-slate-900">
                                Overall, how would you rate our customer support?
                            </h2>
                            <p class="text-center text-xs font-semibold text-slate-500">1 = worst · 10 = best</p>
                            <div class="grid grid-cols-5 gap-2 sm:grid-cols-10">
                                <button
                                    v-for="n in 10"
                                    :key="n"
                                    type="button"
                                    class="aspect-square rounded-xl text-sm font-black transition hover:scale-105"
                                    :class="form.score === n ? 'bg-primary-700 text-white shadow-lg' : 'bg-slate-100 text-slate-700 hover:bg-primary-100'"
                                    @click="form.score = n"
                                >
                                    {{ n }}
                                </button>
                            </div>
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Anything else? (optional)</span>
                                <textarea
                                    v-model="form.comment"
                                    rows="3"
                                    class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                                    placeholder="Tell us what went well or what we can improve…"
                                />
                            </label>
                        </div>

                        <p v-if="errorMsg" class="mt-4 text-center text-xs font-semibold text-rose-600">{{ errorMsg }}</p>

                        <div class="mt-8 flex gap-3">
                            <button
                                v-if="stepIndex > 0"
                                type="button"
                                class="flex-1 rounded-xl border border-slate-200 py-3 text-sm font-black uppercase text-slate-700 hover:bg-slate-50"
                                @click="stepBack"
                            >
                                Back
                            </button>
                            <button
                                type="submit"
                                class="flex-[2] rounded-xl bg-primary-700 py-3 text-sm font-black uppercase text-white shadow-md hover:bg-primary-800 disabled:opacity-50"
                                :disabled="!canContinue || processing"
                            >
                                {{ isLastStep ? (processing ? 'Sending…' : 'Submit feedback') : 'Continue' }}
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    ticket: { type: Object, required: true },
    submitUrl: { type: String, required: true },
    surveySteps: { type: Array, default: () => [] },
    reactions: { type: Array, default: () => [] },
    preselectedReaction: { type: String, default: null },
});

const page = usePage();
const done = ref(false);
const processing = ref(false);
const errorMsg = ref('');
const stepIndex = ref(0);

const form = reactive({
    reaction: props.preselectedReaction || null,
    answers: {},
    score: 0,
    comment: '',
});

props.surveySteps.forEach((s) => {
    form.answers[s.id] = '';
});

const stepSequence = computed(() => {
    const seq = ['reaction'];
    props.surveySteps.forEach((s) => seq.push(s.id));
    seq.push('score');

    return seq;
});

const stepLabels = computed(() => [
    'Mood',
    ...props.surveySteps.map(() => 'Question'),
    'Score',
]);

const totalSteps = computed(() => stepSequence.value.length);
const currentStep = computed(() => stepSequence.value[stepIndex.value] ?? 'reaction');
const surveyStep = computed(() => props.surveySteps.find((s) => s.id === currentStep.value) ?? null);
const isLastStep = computed(() => stepIndex.value === totalSteps.value - 1);

const displayScore = computed(() => {
    if (form.score) {
        return form.score;
    }

    return props.ticket.score ?? null;
});

const canContinue = computed(() => {
    if (currentStep.value === 'reaction') {
        return !!form.reaction;
    }
    if (surveyStep.value) {
        return !!form.answers[surveyStep.value.id];
    }
    if (currentStep.value === 'score') {
        return form.score >= 1 && form.score <= 10;
    }

    return false;
});

function stepBack() {
    if (stepIndex.value > 0) {
        stepIndex.value -= 1;
    }
}

function onContinue() {
    errorMsg.value = '';
    if (!canContinue.value) {
        return;
    }
    if (!isLastStep.value) {
        stepIndex.value += 1;

        return;
    }
    processing.value = true;
    router.post(
        props.submitUrl,
        {
            score: form.score,
            reaction: form.reaction,
            comment: form.comment || null,
            answers: form.answers,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                done.value = true;
            },
            onError: (errors) => {
                errorMsg.value = Object.values(errors).flat().join(' ') || 'Could not submit feedback.';
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

if (page.props.flash?.success) {
    done.value = true;
}
</script>
