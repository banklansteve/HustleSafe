<template>
    <AppShell>
        <Head :title="`Messages · ${quest.title}`" />

        <div class="mx-auto max-w-3xl space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <BackChevronLink :href="route('quests.show', quest.route_key)" aria-label="Back to quest" />
            </div>

            <section class="rounded-2xl border border-amber-200/90 bg-amber-50/80 px-4 py-3 text-xs font-semibold text-amber-950 ring-1 ring-amber-100 sm:px-5">
                <p class="font-black uppercase tracking-wide text-amber-900">Secure channel</p>
                <p class="mt-1 leading-relaxed">{{ rules.no_contact }}</p>
            </section>

            <section v-if="limits.post_award && limits.post_award_hint" class="rounded-2xl border border-sky-200/90 bg-sky-50/90 px-4 py-3 text-xs font-semibold text-sky-950 ring-1 ring-sky-100 sm:px-5">
                <p class="font-black uppercase tracking-wide text-sky-900">Milestone chat</p>
                <p class="mt-1 leading-relaxed">{{ limits.post_award_hint }}</p>
            </section>

            <section class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <div class="flex items-center gap-3">
                    <UserProfileAvatar
                        :href="counterparty.profile_url"
                        :src="counterparty.avatar_url"
                        :name="counterparty.name"
                        :alt="counterparty.name"
                        frame-class="h-12 w-12 text-xs"
                    />
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Chatting with</p>
                        <p class="font-display text-lg font-black text-slate-900">{{ counterparty.name }}</p>
                    </div>
                </div>
            </section>

            <section ref="threadScrollEl" class="max-h-[55vh] space-y-3 overflow-y-auto rounded-2xl border border-slate-200/90 bg-slate-50/60 p-4 ring-1 ring-slate-100 sm:p-5">
                <div
                    v-for="m in messages"
                    :key="m.id"
                    class="flex gap-2"
                    :class="m.sender.is_me ? 'justify-end' : 'justify-start'"
                >
                    <UserProfileAvatar
                        v-if="!m.sender.is_me"
                        :href="m.sender.profile_url"
                        :src="m.sender.avatar_url"
                        :name="m.sender.name || m.sender.first_name"
                        :alt="m.sender.name || ''"
                        frame-class="mt-1 h-9 w-9 text-[10px] shrink-0"
                    />
                    <div
                        class="max-w-[min(85%,20rem)] rounded-2xl px-4 py-2.5 text-sm font-semibold shadow-sm"
                        :class="m.sender.is_me ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-900'"
                    >
                        <p class="text-[10px] font-black uppercase tracking-wide" :class="m.sender.is_me ? 'text-primary-100' : 'text-slate-500'">
                            {{ m.sender.is_me ? 'You' : m.sender.first_name || m.sender.name }}
                        </p>
                        <p class="mt-1 whitespace-pre-wrap leading-relaxed">
                            {{ m.body }}
                        </p>
                        <p class="mt-1 text-[10px] font-bold opacity-70">
                            {{ formatWhen(m.created_at) }}
                        </p>
                    </div>
                    <UserProfileAvatar
                        v-if="m.sender.is_me"
                        :href="m.sender.profile_url"
                        :src="m.sender.avatar_url"
                        :name="m.sender.name || m.sender.first_name"
                        :alt="m.sender.name || ''"
                        frame-class="mt-1 h-9 w-9 text-[10px] shrink-0"
                    />
                </div>
            </section>

            <form class="space-y-3 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6" @submit.prevent="send">
                <label class="text-[11px] font-black uppercase tracking-wide text-slate-600">Your message</label>
                <textarea
                    v-model="body"
                    rows="3"
                    :maxlength="limits.body_max"
                    class="w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    placeholder="Ask a concise, professional question about the brief."
                    @keydown="onMessageKeydown"
                />
                <p class="text-[11px] font-semibold text-slate-500">
                    {{ body.trim().length }} / {{ limits.body_max }} characters
                </p>
                <InputError :message="fieldError" />
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 py-3 text-sm font-black text-white shadow-md hover:bg-primary-700 disabled:opacity-50 sm:w-auto sm:px-8"
                    :disabled="sending"
                >
                    <ReLoader4Line v-if="sending" class="h-4 w-4 shrink-0 animate-spin" aria-hidden="true" />
                    Send
                </button>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import InputError from '@/Components/InputError.vue';
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    counterparty: { type: Object, required: true },
    thread: { type: Object, required: true },
    post_url: { type: String, required: true },
    rules: { type: Object, default: () => ({}) },
    messaging_limits: {
        type: Object,
        default: () => ({
            body_max: 2000,
            post_award: false,
            post_award_hint: null,
        }),
    },
});

const page = usePage();

const viewerId = computed(() => page.props.auth?.user?.id);

const limits = computed(() => ({
    body_max: Number(props.messaging_limits?.body_max) || 2000,
    post_award: Boolean(props.messaging_limits?.post_award),
    post_award_hint: props.messaging_limits?.post_award_hint ?? null,
}));

function normalizeMessage(m) {
    const sid = m?.sender?.id;

    return {
        ...m,
        sender: {
            ...m.sender,
            is_me: viewerId.value != null && Number(sid) === Number(viewerId.value),
        },
    };
}

const messages = ref(props.thread.messages.map(normalizeMessage));
const body = ref('');
const sending = ref(false);
const errors = ref({});
const threadScrollEl = ref(null);

watch(
    () => props.thread.messages,
    (next) => {
        messages.value = next.map(normalizeMessage);
    },
    { deep: true },
);

watch(
    () => limits.value.body_max,
    (max) => {
        if (body.value.length > max) {
            body.value = body.value.slice(0, max);
        }
    },
);

const fieldError = computed(() => {
    const b = errors.value?.body;
    if (Array.isArray(b)) {
        return b[0] ?? '';
    }
    if (typeof b === 'string') {
        return b;
    }

    return errors.value?.message ?? '';
});

function appendIfNew(raw) {
    const m = normalizeMessage(raw);
    if (messages.value.some((x) => Number(x.id) === Number(m.id))) {
        return;
    }
    messages.value = [...messages.value, m];
    void scrollThreadToEnd({ smooth: true });
}

function onMessageKeydown(e) {
    if (e.key !== 'Enter' || e.shiftKey) {
        return;
    }
    if (e.isComposing) {
        return;
    }
    e.preventDefault();
    if (sending.value) {
        return;
    }
    if (!body.value.trim()) {
        return;
    }
    void send();
}

async function scrollThreadToEnd({ smooth = false } = {}) {
    await nextTick();
    const el = threadScrollEl.value;
    if (!el) {
        return;
    }
    const apply = () => {
        el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    };
    apply();
    requestAnimationFrame(() => {
        apply();
    });
}

async function send() {
    errors.value = {};
    sending.value = true;
    try {
        const { data } = await axios.post(
            props.post_url,
            { body: body.value },
            { headers: { Accept: 'application/json' } },
        );
        if (data?.message) {
            appendIfNew(data.message);
        }
        body.value = '';
    } catch (e) {
        const status = e.response?.status;
        const d = e.response?.data;
        if (status === 422) {
            if (d?.errors) {
                errors.value = d.errors;
            } else if (typeof d?.message === 'string') {
                errors.value = { body: d.message };
            }
        }
    } finally {
        sending.value = false;
    }
}

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString('en-NG', { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short', timeZone: 'Africa/Lagos' });
    } catch {
        return '';
    }
}

let subscribedThreadId = null;

function bindEchoForThread(threadId) {
    const echo = window.Echo;
    if (!echo) {
        return;
    }
    if (subscribedThreadId === threadId) {
        return;
    }
    if (subscribedThreadId != null) {
        echo.leave(`quest-threads.${subscribedThreadId}`);
    }
    subscribedThreadId = threadId;
    const channel = echo.private(`quest-threads.${threadId}`);
    channel.listen('.message.sent', (payload) => {
        if (payload?.message) {
            appendIfNew(payload.message);
        }
    });
}

onMounted(() => {
    void scrollThreadToEnd({ smooth: false });
    bindEchoForThread(props.thread.id);
});

watch(
    () => props.thread.id,
    (id, prev) => {
        if (id === prev) {
            return;
        }
        messages.value = props.thread.messages.map(normalizeMessage);
        void scrollThreadToEnd({ smooth: false });
        bindEchoForThread(id);
    },
);

onBeforeUnmount(() => {
    if (window.Echo && subscribedThreadId != null) {
        window.Echo.leave(`quest-threads.${subscribedThreadId}`);
        subscribedThreadId = null;
    }
});
</script>
