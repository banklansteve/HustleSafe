<template>
    <AppShell>
        <Head :title="`Clarify · ${quest.title}`" />

        <div class="mx-auto max-w-2xl space-y-4 px-1 sm:px-0">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <BackChevronLink :href="route('quests.proposals.show', [quest.route_key, offer.id])" aria-label="Back to proposal" />
                <span class="rounded-full bg-sky-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-sky-900">
                    Pre-award only
                </span>
            </div>

            <header class="rounded-2xl border border-sky-200/90 bg-gradient-to-br from-sky-50 via-white to-primary-50/40 p-5 ring-1 ring-sky-100 sm:p-6">
                <p class="text-[10px] font-black uppercase tracking-[0.25em] text-sky-800">Clarification thread</p>
                <h1 class="font-display mt-2 text-xl font-black text-slate-900 sm:text-2xl">{{ quest.title }}</h1>
                <p class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">
                    Focused Q&amp;A tied to this proposal — not open chat. Ask up to {{ thread.max_questions }} questions before you award.
                </p>
                <p class="mt-2 text-xs font-bold text-slate-500">
                    With {{ offer.freelancer?.name }} · {{ thread.questions_asked }}/{{ thread.max_questions }} questions used
                </p>
            </header>

            <div
                v-if="page.props.flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-950"
            >
                {{ page.props.flash.success }}
            </div>

            <section v-if="thread.messages.length" class="space-y-4">
                <div
                    v-for="msg in pairedMessages"
                    :key="msg.question.id"
                    class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-100"
                >
                    <p class="text-[10px] font-black uppercase tracking-wide text-primary-800">
                        {{ msg.question.prompt_category || 'Question' }}
                    </p>
                    <p class="mt-2 text-sm font-semibold leading-relaxed text-slate-900">{{ msg.question.body }}</p>
                    <p class="mt-1 text-[11px] font-bold text-slate-400">You · {{ formatWhen(msg.question.created_at) }}</p>

                    <div v-if="msg.answer" class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                        <p class="text-sm font-semibold leading-relaxed text-slate-800">{{ msg.answer.body }}</p>
                        <p class="mt-1 text-[11px] font-bold text-emerald-800/70">{{ offer.freelancer?.name }} · {{ formatWhen(msg.answer.created_at) }}</p>
                    </div>

                    <form
                        v-else-if="thread.can_answer"
                        class="mt-4 space-y-2"
                        @submit.prevent="submitAnswer(msg.question.id)"
                    >
                        <textarea
                            v-model="answerDrafts[msg.question.id]"
                            rows="3"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                            placeholder="Answer clearly — this becomes part of the pre-award record."
                        />
                        <InputError :message="answerForms[msg.question.id]?.errors.body" />
                        <button
                            type="submit"
                            class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-700"
                            :disabled="answerForms[msg.question.id]?.processing"
                        >
                            Post answer
                        </button>
                    </form>

                    <p v-else-if="!msg.answer" class="mt-3 text-xs font-semibold text-amber-800">Awaiting freelancer reply…</p>
                </div>
            </section>

            <p v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm font-semibold text-slate-600">
                No questions yet. Pick a smart prompt below or write your own.
            </p>

            <section v-if="thread.can_ask && suggested_prompts.length" class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Suggested for this quest</p>
                <div class="mt-3 flex flex-col gap-2">
                    <button
                        v-for="prompt in suggested_prompts"
                        :key="prompt.key"
                        type="button"
                        class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-left transition hover:border-primary-200 hover:bg-primary-50/50"
                        @click="usePrompt(prompt)"
                    >
                        <span class="text-[10px] font-black uppercase tracking-wide text-primary-800">{{ prompt.label }}</span>
                        <span class="mt-1 block text-xs font-semibold leading-relaxed text-slate-700">{{ prompt.question }}</span>
                        <span v-if="prompt.hint" class="mt-1 block text-[11px] font-medium text-slate-500">{{ prompt.hint }}</span>
                    </button>
                </div>
            </section>

            <section v-if="thread.can_ask" class="rounded-2xl border border-primary-200/80 bg-white p-4 sm:p-5">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-800">Your question</p>
                <form class="mt-3 space-y-3" @submit.prevent="submitQuestion">
                    <textarea
                        v-model="questionForm.body"
                        rows="4"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        placeholder="Ask something specific about scope, timeline, materials, or blockers…"
                    />
                    <InputError :message="questionForm.errors.body" />
                    <button
                        type="submit"
                        class="rounded-full bg-primary-700 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-primary-800"
                        :disabled="questionForm.processing || questionForm.body.trim().length < 20"
                    >
                        Send question
                    </button>
                </form>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    thread: { type: Object, required: true },
    suggested_prompts: { type: Array, default: () => [] },
    offer: { type: Object, required: true },
    quest: { type: Object, required: true },
});

const page = usePage();
const questionForm = useForm({
    body: '',
    prompt_key: null,
    prompt_category: null,
});

const answerDrafts = reactive({});
const answerForms = reactive({});

const pairedMessages = computed(() => {
    const questions = props.thread.messages.filter((m) => m.role === 'client');
    const answers = props.thread.messages.filter((m) => m.role === 'freelancer');

    return questions.map((q) => ({
        question: q,
        answer: answers.find((a) => a.prompt_key === `reply:${q.id}`) || null,
    }));
});

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Lagos' });
    } catch {
        return '';
    }
}

function usePrompt(prompt) {
    questionForm.body = prompt.question;
    questionForm.prompt_key = prompt.key;
    questionForm.prompt_category = prompt.category;
}

function submitQuestion() {
    questionForm.post(route('quests.proposals.clarify.ask', [props.quest.route_key, props.offer.id]), {
        preserveScroll: true,
        onSuccess: () => questionForm.reset('body', 'prompt_key', 'prompt_category'),
    });
}

function submitAnswer(messageId) {
    if (!answerForms[messageId]) {
        answerForms[messageId] = useForm({
            body: answerDrafts[messageId] || '',
            reply_to_message_id: messageId,
        });
    } else {
        answerForms[messageId].body = answerDrafts[messageId] || '';
        answerForms[messageId].reply_to_message_id = messageId;
    }

    answerForms[messageId].post(route('quests.proposals.clarify.answer', [props.quest.route_key, props.offer.id]), {
        preserveScroll: true,
        onSuccess: () => {
            answerDrafts[messageId] = '';
        },
    });
}
</script>
