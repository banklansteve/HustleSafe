<template>
    <div class="min-h-screen bg-gradient-to-br from-primary-50 via-white to-teal-50/80 px-4 py-10 sm:py-14">
        <div class="mx-auto w-full max-w-lg">
            <div class="overflow-hidden rounded-[1.75rem] border border-primary-100/80 bg-white shadow-xl shadow-primary-900/10 ring-1 ring-primary-50">
                <header class="bg-gradient-to-r from-primary-800 via-primary-700 to-teal-600 px-6 py-8 text-white">
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-primary-100">HustleSafe</p>
                    <h1 class="font-display mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                        {{ survey.submitted ? 'Thank you' : 'Share your experience' }}
                    </h1>
                    <p v-if="survey.quest_title" class="mt-2 text-sm font-semibold text-primary-100/90">
                        {{ survey.quest_title }}
                    </p>
                </header>

                <div v-if="survey.submitted" class="px-6 py-10 text-center">
                    <p class="text-5xl" aria-hidden="true">✨</p>
                    <p class="mt-4 text-lg font-black text-slate-900">Feedback received</p>
                    <p class="mt-2 text-sm font-semibold text-slate-600">
                        Your responses are anonymous in our survey. They help us improve the platform for everyone.
                    </p>
                    <a
                        href="/"
                        class="mt-8 inline-flex rounded-xl bg-primary-700 px-6 py-3 text-sm font-black uppercase text-white shadow-md hover:bg-primary-800"
                    >
                        Back to HustleSafe
                    </a>
                </div>

                <template v-else-if="visibleSteps.length">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <div class="flex gap-1.5">
                            <div
                                v-for="(_, idx) in visibleSteps"
                                :key="idx"
                                class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"
                            >
                                <div
                                    class="h-full rounded-full bg-primary-600 transition-all duration-300"
                                    :class="idx <= stepIndex ? 'w-full' : 'w-0'"
                                />
                            </div>
                        </div>
                        <p class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Question {{ stepIndex + 1 }} of {{ visibleSteps.length }}
                        </p>
                        <p class="mt-1 text-[11px] font-semibold text-slate-400">
                            Anonymous survey — no login required
                        </p>
                    </div>

                    <form class="px-6 py-6" @submit.prevent="onContinue">
                        <div v-if="currentStep" class="space-y-4">
                            <h2 class="font-display text-lg font-black leading-snug text-slate-900">
                                {{ currentStep.label }}
                            </h2>

                            <div v-if="currentStep.type === 'text'" class="space-y-2">
                                <textarea
                                    v-model="form.answers[currentStep.key]"
                                    rows="4"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                                    :placeholder="currentStep.optional ? 'Optional' : 'Your answer'"
                                    :maxlength="currentStep.max"
                                />
                                <p class="text-right text-[11px] font-semibold text-slate-400">
                                    {{ (form.answers[currentStep.key] || '').length }} / {{ currentStep.max }}
                                </p>
                            </div>

                            <div v-else-if="currentStep.type === 'nps'" class="space-y-4">
                                <p class="text-center text-xs font-semibold text-slate-500">0 = not at all · 10 = extremely likely</p>
                                <div class="grid grid-cols-6 gap-2 sm:grid-cols-11">
                                    <button
                                        v-for="n in 11"
                                        :key="n - 1"
                                        type="button"
                                        class="aspect-square rounded-xl text-sm font-black transition hover:scale-105"
                                        :class="Number(form.answers[currentStep.key]) === n - 1 ? 'bg-primary-700 text-white shadow-lg' : 'bg-slate-100 text-slate-700 hover:bg-primary-100'"
                                        @click="form.answers[currentStep.key] = n - 1"
                                    >
                                        {{ n - 1 }}
                                    </button>
                                </div>
                            </div>

                            <div v-else class="space-y-2">
                                <label
                                    v-for="opt in currentStep.options"
                                    :key="opt.value"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition"
                                    :class="form.answers[currentStep.key] === opt.value ? 'border-primary-400 bg-primary-50 ring-1 ring-primary-200' : 'border-slate-200 hover:border-primary-200'"
                                >
                                    <input
                                        v-model="form.answers[currentStep.key]"
                                        type="radio"
                                        :value="opt.value"
                                        class="h-4 w-4 border-slate-300 text-primary-600 focus:ring-primary-500"
                                        :required="!currentStep.optional"
                                    />
                                    <span class="text-sm font-semibold text-slate-800">{{ opt.label }}</span>
                                </label>
                            </div>
                        </div>

                        <p v-if="errorMsg" class="mt-4 text-center text-xs font-semibold text-rose-600">{{ errorMsg }}</p>

                        <div class="mt-8 flex gap-3">
                            <button
                                v-if="stepIndex > 0"
                                type="button"
                                class="flex-1 rounded-xl border border-slate-200 py-3 text-sm font-black uppercase text-slate-700 hover:bg-slate-50"
                                @click="stepIndex -= 1"
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

                <div v-else class="px-6 py-10 text-center">
                    <p class="text-sm font-semibold text-slate-600">Loading your survey…</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    survey: { type: Object, required: true },
    steps: { type: Array, default: () => [] },
    prefill: { type: Object, default: () => ({}) },
    submitUrl: { type: String, required: true },
});

const processing = ref(false);
const errorMsg = ref('');
const stepIndex = ref(0);

const form = reactive({
    answers: { ...props.prefill },
});

props.steps.forEach((step) => {
    if (form.answers[step.key] === undefined) {
        form.answers[step.key] = '';
    }
});

const visibleSteps = computed(() => {
    return props.steps.filter((step) => {
        const showWhen = step.show_when;
        if (!showWhen || typeof showWhen !== 'object') {
            return true;
        }

        return Object.entries(showWhen).every(([key, value]) => form.answers[key] === value);
    });
});

const currentStep = computed(() => visibleSteps.value[stepIndex.value] ?? null);
const isLastStep = computed(() => stepIndex.value === visibleSteps.value.length - 1);

const canContinue = computed(() => {
    const step = currentStep.value;
    if (!step) {
        return false;
    }

    const value = form.answers[step.key];
    if (step.optional) {
        return true;
    }

    if (step.type === 'nps') {
        return value !== '' && value !== null && value !== undefined;
    }

    return value !== '' && value !== null && value !== undefined;
});

watch(visibleSteps, (steps) => {
    if (stepIndex.value >= steps.length) {
        stepIndex.value = Math.max(steps.length - 1, 0);
    }
});

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
        { answers: form.answers },
        {
            preserveScroll: true,
            onError: (errors) => {
                errorMsg.value = Object.values(errors).flat().join(' ') || 'Could not submit feedback.';
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>
