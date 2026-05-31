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
                <p v-if="is_client" class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">
                    Focused Q&amp;A tied to this proposal — not open chat. Ask up to {{ threadState.max_questions }} questions before you award.
                </p>
                <p v-else class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">
                    Focused Q&amp;A tied to this proposal — not open chat. Answer the client's questions clearly so they can decide with confidence.
                </p>
                <p class="mt-2 text-xs font-bold text-slate-500">
                    With {{ counterpartyName }} · {{ threadState.questions_asked }}/{{ threadState.max_questions }} questions used
                </p>
            </header>

            <section v-if="pairedMessages.length" class="space-y-4">
                <div
                    v-for="msg in pairedMessages"
                    :key="msg.question.id"
                    class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-100"
                >
                    <p class="text-[10px] font-black uppercase tracking-wide text-primary-800">
                        {{ msg.question.prompt_category || 'Question' }}
                    </p>
                    <RedactedMessageBody
                        :body="msg.question.body"
                        :is-redacted="msg.question.is_redacted"
                        :redaction-label="msg.question.redaction_label"
                        class="mt-2"
                    />
                    <p class="mt-1 text-[11px] font-bold text-slate-400">
                        {{ authorLabel(msg.question) }} · {{ formatWhen(msg.question.created_at) }}
                    </p>

                    <div v-if="msg.answer" class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                        <RedactedMessageBody
                            :body="msg.answer.body"
                            :is-redacted="msg.answer.is_redacted"
                            :redaction-label="msg.answer.redaction_label"
                        />
                        <p class="mt-1 text-[11px] font-bold text-emerald-800/70">
                            {{ authorLabel(msg.answer) }} · {{ formatWhen(msg.answer.created_at) }}
                        </p>
                    </div>

                    <form
                        v-else-if="is_freelancer && threadState.can_answer"
                        class="mt-4 space-y-2"
                        @submit.prevent="submitAnswer(msg.question.id)"
                    >
                        <textarea
                            v-model="answerDrafts[msg.question.id]"
                            rows="3"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                            placeholder="Answer clearly — this becomes part of the pre-award record."
                        />
                        <InputError :message="answerErrors[msg.question.id]" />
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-600 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="answerSubmitting[msg.question.id]"
                        >
                            <ReLoader4Line v-if="answerSubmitting[msg.question.id]" class="h-4 w-4 animate-spin" aria-hidden="true" />
                            Post answer
                        </button>
                    </form>

                    <p v-else-if="!msg.answer && is_client" class="mt-3 text-xs font-semibold text-amber-800">Awaiting freelancer reply…</p>
                </div>
            </section>

            <p v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm font-semibold text-slate-600">
                <template v-if="is_client">No questions yet. Pick a smart prompt below or write your own.</template>
                <template v-else>No questions from the client yet. When they ask something here, you can reply in this thread.</template>
            </p>

            <section v-if="threadState.can_ask && suggested_prompts.length" class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Suggested for this quest</p>
                <div class="mt-3 flex flex-col gap-2">
                    <button
                        v-for="prompt in suggested_prompts"
                        :key="prompt.key"
                        type="button"
                        class="rounded-xl border px-4 py-3 text-left transition"
                        :class="promptAlreadyUsed(prompt)
                            ? 'cursor-not-allowed border-slate-100 bg-slate-50/80 opacity-60'
                            : 'border-slate-200 bg-slate-50/80 hover:border-primary-200 hover:bg-primary-50/50'"
                        :disabled="promptAlreadyUsed(prompt)"
                        @click="usePrompt(prompt)"
                    >
                        <span class="text-[10px] font-black uppercase tracking-wide" :class="promptAlreadyUsed(prompt) ? 'text-slate-400' : 'text-primary-800'">
                            {{ promptAlreadyUsed(prompt) ? 'Already asked' : prompt.label }}
                        </span>
                        <span class="mt-1 block text-xs font-semibold leading-relaxed text-slate-700">{{ prompt.question }}</span>
                        <span v-if="prompt.hint" class="mt-1 block text-[11px] font-medium text-slate-500">{{ prompt.hint }}</span>
                    </button>
                </div>
            </section>

            <section v-if="threadState.can_ask" class="rounded-2xl border border-primary-200/80 bg-white p-4 sm:p-5">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-800">Your question</p>
                <form class="mt-3 space-y-3" @submit.prevent="submitQuestion">
                    <textarea
                        v-model="questionBody"
                        rows="4"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        placeholder="Ask something specific about scope, timeline, materials, or blockers…"
                    />
                    <InputError :message="questionError" />
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="questionSubmitting || questionBody.trim().length < 20"
                    >
                        <ReLoader4Line v-if="questionSubmitting" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        Send question
                    </button>
                </form>
            </section>
        </div>

        <OperationsToastHost />
    </AppShell>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import RedactedMessageBody from '@/Components/ConversationMonitoring/RedactedMessageBody.vue';
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import AppShell from '@/Layouts/AppShell.vue';
import OperationsToastHost from '@/Pages/Operations/Components/OperationsToastHost.vue';
import { useOperationsToast } from '@/composables/useOperationsToast';
import { ensureEcho } from '@/utils/ensureEcho';
import { broadcastConfigFromPage } from '@/utils/broadcastConfig';
import { Head, usePage } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    thread: { type: Object, required: true },
    suggested_prompts: { type: Array, default: () => [] },
    offer: { type: Object, required: true },
    quest: { type: Object, required: true },
    client: { type: Object, default: () => ({}) },
    is_client: { type: Boolean, default: false },
    is_freelancer: { type: Boolean, default: false },
});

const page = usePage();
const { toast } = useOperationsToast();
const is_client = computed(() => props.is_client);
const is_freelancer = computed(() => props.is_freelancer);

const threadState = reactive({
    id: props.thread.id,
    status: props.thread.status,
    questions_asked: props.thread.questions_asked,
    max_questions: props.thread.max_questions,
    can_ask: props.thread.can_ask,
    can_answer: props.thread.can_answer,
    messages: [...(props.thread.messages || [])],
});

const questionBody = ref('');
const questionPromptKey = ref(null);
const questionPromptCategory = ref(null);
const questionSubmitting = ref(false);
const questionError = ref('');

const answerDrafts = reactive({});
const answerSubmitting = reactive({});
const answerErrors = reactive({});

let pollTimer = null;
let echoChannel = null;

const counterpartyName = computed(() => {
    if (is_client.value) {
        return props.offer.freelancer?.name || 'Freelancer';
    }

    return props.client?.name || 'Client';
});

const pairedMessages = computed(() => {
    const questions = threadState.messages
        .filter((m) => m.role === 'client')
        .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
    const answers = threadState.messages.filter((m) => m.role === 'freelancer');

    return questions.map((q) => ({
        question: q,
        answer: answerForQuestion(q.id, answers),
    }));
});

function answerForQuestion(questionId, answers) {
    const qid = Number(questionId);

    return answers.find((a) => {
        const key = String(a.prompt_key || '');
        if (!key.startsWith('reply:')) {
            return false;
        }

        return Number(key.slice(6)) === qid;
    }) || null;
}

watch(
    () => props.thread,
    (thread) => {
        threadState.id = thread.id;
        threadState.status = thread.status;
        threadState.questions_asked = thread.questions_asked;
        threadState.max_questions = thread.max_questions;
        threadState.can_ask = thread.can_ask;
        threadState.can_answer = thread.can_answer;
        threadState.messages = [...(thread.messages || [])];
    },
    { deep: true },
);

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Lagos' });
    } catch {
        return '';
    }
}

function authorLabel(message) {
    if (message.role === 'client') {
        return is_client.value ? 'You' : (props.client?.name || message.author?.name || 'Client');
    }

    return is_freelancer.value ? 'You' : (props.offer.freelancer?.name || message.author?.name || 'Freelancer');
}

function hasMessage(message) {
    return threadState.messages.some((m) => Number(m.id) === Number(message.id));
}

function applyThreadMeta(meta = {}) {
    if (typeof meta.questions_asked === 'number') {
        threadState.questions_asked = meta.questions_asked;
    }
    if (is_client.value && typeof meta.can_ask === 'boolean') {
        threadState.can_ask = meta.can_ask;
    }
    if (is_freelancer.value && typeof meta.can_answer === 'boolean') {
        threadState.can_answer = meta.can_answer;
    }
}

function ingestMessage(message, meta = {}) {
    if (!message?.id || hasMessage(message)) {
        return;
    }

    threadState.messages.unshift(message);
    applyThreadMeta(meta);
}

function usePrompt(prompt) {
    if (promptAlreadyUsed(prompt)) {
        return;
    }
    questionBody.value = prompt.question;
    questionPromptKey.value = prompt.key;
    questionPromptCategory.value = prompt.category;
}

const usedPromptKeys = computed(() => {
    const keys = new Set();
    for (const m of threadState.messages) {
        if (m.role === 'client' && m.prompt_key) {
            keys.add(m.prompt_key);
        }
    }

    return keys;
});

function promptAlreadyUsed(prompt) {
    return usedPromptKeys.value.has(prompt.key);
}

async function submitQuestion() {
    if (questionSubmitting.value) {
        return;
    }

    questionSubmitting.value = true;
    questionError.value = '';

    try {
        const { data } = await axios.post(
            route('quests.proposals.clarify.ask', [props.quest.route_key, props.offer.id]),
            {
                body: questionBody.value,
                prompt_key: questionPromptKey.value,
                prompt_category: questionPromptCategory.value,
            },
            { headers: { Accept: 'application/json' } },
        );

        ingestMessage(data.message, data.thread);
        questionBody.value = '';
        questionPromptKey.value = null;
        questionPromptCategory.value = null;
        toast(data.flash || 'Question sent.');
    } catch (error) {
        const message = error?.response?.data?.errors?.body?.[0]
            || error?.response?.data?.message
            || 'Could not send your question.';
        questionError.value = message;
        toast(message, 'error');
    } finally {
        questionSubmitting.value = false;
    }
}

async function submitAnswer(messageId) {
    if (answerSubmitting[messageId]) {
        return;
    }

    answerSubmitting[messageId] = true;
    answerErrors[messageId] = '';

    try {
        const { data } = await axios.post(
            route('quests.proposals.clarify.answer', [props.quest.route_key, props.offer.id]),
            {
                body: answerDrafts[messageId] || '',
                reply_to_message_id: messageId,
            },
            { headers: { Accept: 'application/json' } },
        );

        ingestMessage(data.message, data.thread);
        delete answerDrafts[messageId];
        delete answerSubmitting[messageId];
        delete answerErrors[messageId];
        toast(data.flash || 'Answer posted.');
    } catch (error) {
        const message = error?.response?.data?.errors?.body?.[0]
            || error?.response?.data?.message
            || 'Could not post your answer.';
        answerErrors[messageId] = message;
        toast(message, 'error');
    } finally {
        answerSubmitting[messageId] = false;
    }
}

function bindRealtime() {
    const echo = ensureEcho(broadcastConfigFromPage(page));
    if (!echo || !threadState.id) {
        return false;
    }

    const channelName = `proposal-clarifications.${threadState.id}`;
    echoChannel = echo.private(channelName);
    echoChannel.listen('.clarification.message.sent', (payload) => {
        if (payload?.message) {
            ingestMessage(payload.message, payload.thread);
        }
    });

    return true;
}

async function pollForUpdates() {
    try {
        const lastId = threadState.messages.reduce((max, m) => Math.max(max, Number(m.id) || 0), 0);
        const { data } = await axios.get(
            route('quests.proposals.clarify', [props.quest.route_key, props.offer.id]),
            {
                headers: { Accept: 'application/json' },
                params: { after_id: lastId || undefined },
            },
        );

        (data?.thread?.messages || []).forEach((message) => ingestMessage(message, data.thread));
        applyThreadMeta(data?.thread);
    } catch {
        /* best-effort */
    }
}

onMounted(() => {
    window.dispatchEvent(new CustomEvent('app:notifications-changed'));
    if (!bindRealtime()) {
        pollTimer = window.setInterval(pollForUpdates, 3000);
    }
});

onBeforeUnmount(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
    if (window.Echo && threadState.id) {
        window.Echo.leave(`proposal-clarifications.${threadState.id}`);
    }
    echoChannel = null;
});
</script>
