<template>
    <Teleport to="body">
        <!-- Floating launcher -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-4 opacity-0 scale-90"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-4 opacity-0 scale-90"
        >
            <button
                v-if="enabled && !panelOpen"
                type="button"
                class="support-bubble-launcher fixed bottom-5 right-5 z-[85] flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 via-primary-600 to-teal-600 text-white shadow-2xl shadow-primary-900/30 ring-4 ring-white transition hover:scale-105 active:scale-95 sm:bottom-6 sm:right-6 sm:h-16 sm:w-16"
                aria-label="Open support chat"
                @click="openPanel"
            >
                <span class="absolute inset-0 animate-ping rounded-full bg-primary-400 opacity-20" aria-hidden="true" />
                <ChatBubbleLeftRightIcon class="relative h-7 w-7 sm:h-8 sm:w-8" aria-hidden="true" />
                <span
                    v-if="unreadTotal > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-black text-white ring-2 ring-white"
                >{{ unreadTotal > 9 ? '9+' : unreadTotal }}</span>
            </button>
        </Transition>

        <!-- Chat panel -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-8 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-8 opacity-0"
        >
            <div
                v-if="panelOpen"
                class="fixed bottom-0 right-0 z-[86] flex h-[min(100dvh,640px)] max-h-[min(100dvh,640px)] w-full flex-col overflow-hidden rounded-t-[1.75rem] border border-slate-200/80 bg-white shadow-2xl sm:bottom-6 sm:right-6 sm:h-[min(640px,calc(100dvh-3rem))] sm:max-h-[min(640px,calc(100dvh-3rem))] sm:w-[min(100vw-2rem,24rem)] sm:rounded-[1.75rem]"
                role="dialog"
                aria-label="Customer support"
            >
                <!-- Header -->
                <header class="relative shrink-0 overflow-hidden border-b border-primary-100 bg-gradient-to-r from-primary-700 via-primary-600 to-teal-600 px-4 py-4 text-white">
                    <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10" aria-hidden="true" />
                    <div class="relative flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-100">HustleSafe</p>
                            <h2 class="font-display text-lg font-black tracking-tight">{{ headerTitle }}</h2>
                            <p class="mt-0.5 text-xs font-semibold text-primary-100/90">{{ headerSubtitle }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <span
                                class="hidden rounded-full px-2 py-0.5 text-[9px] font-black uppercase sm:inline-flex"
                                :class="live ? 'bg-emerald-400/30 text-emerald-50' : 'bg-white/20 text-white'"
                            >{{ live ? 'Live' : 'Sync' }}</span>
                            <button
                                type="button"
                                class="rounded-full p-2 text-white/90 transition hover:bg-white/15"
                                aria-label="Minimize"
                                @click="panelOpen = false"
                            >
                                <ChevronDownIcon class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Body -->
                <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <!-- Home -->
                    <div v-if="view === 'home'" class="flex min-h-0 flex-1 flex-col overflow-y-auto p-4">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-2xl bg-gradient-to-r from-primary-600 to-teal-600 px-4 py-4 text-left text-white shadow-lg shadow-primary-900/15 transition hover:brightness-105 active:scale-[0.99]"
                            @click="view = 'start'"
                        >
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 text-xl">💬</span>
                            <span>
                                <span class="block text-sm font-black">Start a new chat</span>
                                <span class="block text-xs font-semibold text-primary-100">We typically reply within minutes</span>
                            </span>
                        </button>

                        <button
                            v-if="activeTicket"
                            type="button"
                            class="mt-3 flex w-full items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-left transition hover:bg-emerald-100"
                            @click="resumeTicket(activeTicket)"
                        >
                            <span class="min-w-0">
                                <span class="block text-xs font-black uppercase text-emerald-800">Active chat</span>
                                <span class="mt-0.5 block truncate text-sm font-bold text-emerald-950">{{ activeTicket.subject }}</span>
                            </span>
                            <span v-if="activeTicket.unread_count" class="shrink-0 rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-black text-white">{{ activeTicket.unread_count }}</span>
                        </button>

                        <div v-if="recentChats.length" class="mt-6">
                            <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Recent</p>
                            <ul class="mt-2 space-y-2">
                                <li v-for="c in recentChats" :key="c.id">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5 text-left transition hover:border-primary-200 hover:bg-primary-50/50"
                                        @click="resumeTicket(c)"
                                    >
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-bold text-slate-900">{{ c.subject }}</span>
                                            <span class="text-[10px] font-semibold text-slate-500">{{ c.category_label }} · {{ c.chat_status }}</span>
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Start form -->
                    <form v-else-if="view === 'start'" class="flex min-h-0 flex-1 flex-col overflow-y-auto p-4" @submit.prevent="startChat">
                        <button type="button" class="mb-3 self-start text-xs font-bold text-primary-700 hover:underline" @click="view = 'home'">← Back</button>
                        <label class="text-xs font-black uppercase text-slate-500">Subject</label>
                        <input
                            v-model="startForm.subject"
                            type="text"
                            required
                            maxlength="200"
                            class="mt-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                            placeholder="What do you need help with?"
                        />
                        <label class="mt-4 text-xs font-black uppercase text-slate-500">Category</label>
                        <select
                            v-model="startForm.category"
                            required
                            class="mt-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                        >
                            <option value="" disabled>Select category</option>
                            <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <label class="mt-4 text-xs font-black uppercase text-slate-500">Message</label>
                        <textarea
                            v-model="startForm.initial_message"
                            rows="3"
                            class="mt-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                            placeholder="Describe your issue…"
                        />
                        <p v-if="startError" class="mt-2 text-xs font-semibold text-rose-600">{{ startError }}</p>
                        <button
                            type="submit"
                            class="mt-4 w-full rounded-xl bg-primary-700 py-3 text-sm font-black uppercase text-white hover:bg-primary-800 disabled:opacity-50"
                            :disabled="starting"
                        >
                            {{ starting ? 'Connecting…' : 'Start chat' }}
                        </button>
                    </form>

                    <!-- Active chat -->
                    <template v-else-if="view === 'chat' && ticket">
                        <div ref="scrollEl" class="min-h-0 flex-1 overflow-y-auto bg-gradient-to-b from-slate-50/80 to-white p-4">
                            <div v-if="ticket.chat_status === 'queued'" class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs font-semibold text-amber-900">
                                You're in the queue — an agent will join shortly.
                            </div>
                            <SupportChatMessages
                                :messages="messages"
                                perspective="customer"
                                @react="onMessageReact"
                                @attachment-loaded="scrollBottom"
                            >
                                <template #session-closed="{ message: m }">
                                    <div v-if="!ticket.rated && !ratedDone" class="mt-4 flex flex-wrap justify-center gap-2">
                                        <button
                                            v-for="r in closureReactions"
                                            :key="r.key"
                                            type="button"
                                            class="flex flex-col items-center rounded-xl border border-primary-100 bg-white px-2 py-2 transition hover:scale-105 hover:border-primary-300 hover:shadow-md"
                                            :title="r.label"
                                            @click="openFeedback(r.key)"
                                        >
                                            <span class="text-2xl">{{ r.emoji }}</span>
                                            <span class="mt-0.5 text-[9px] font-black uppercase text-slate-500">{{ r.label }}</span>
                                        </button>
                                    </div>
                                    <button
                                        v-if="!ticket.rated && !ratedDone"
                                        type="button"
                                        class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-primary-700 px-4 py-2.5 text-xs font-black uppercase text-white hover:bg-primary-800"
                                        @click="openFeedback()"
                                    >
                                        Leave detailed feedback
                                    </button>
                                    <p v-else class="mt-3 text-xs font-bold text-emerald-700">Thanks — feedback received.</p>
                                </template>
                            </SupportChatMessages>
                            <div v-if="typingLabel" class="mt-2 flex items-center gap-2 text-xs font-semibold text-primary-700">
                                <span class="flex gap-0.5">
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-500 [animation-delay:0ms]" />
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-500 [animation-delay:150ms]" />
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-500 [animation-delay:300ms]" />
                                </span>
                                {{ typingLabel }}
                            </div>
                        </div>

                        <footer v-if="ticket.chat_status === 'closed'" class="shrink-0 border-t border-slate-100 bg-slate-50 px-4 py-4 text-center text-xs font-semibold text-slate-600">
                            This session has ended. Start a new chat anytime if you need more help.
                        </footer>
                        <footer v-else class="relative shrink-0 border-t border-slate-100 bg-white p-3">
                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="translate-y-2 opacity-0"
                                enter-to-class="translate-y-0 opacity-100"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="translate-y-0 opacity-100"
                                leave-to-class="translate-y-2 opacity-0"
                            >
                                <div
                                    v-if="gifOpen"
                                    class="absolute bottom-full left-2 right-2 z-10 mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                                >
                                    <GifPickerPanel
                                        :open="gifOpen"
                                        :search-url="r('api.support.gifs')"
                                        @select="onGifSelected"
                                        @close="gifOpen = false"
                                    />
                                </div>
                            </Transition>

                            <form class="rounded-xl border border-slate-200 shadow-sm" @submit.prevent="send">
                                <div v-if="pendingGif || pendingFiles.length" class="flex flex-wrap gap-2 border-b border-slate-100 px-2 py-2">
                                    <span v-if="pendingGif" class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs">
                                        <img :src="pendingGif.preview || pendingGif.url" alt="" class="h-7 w-7 rounded object-cover" />
                                        GIF <button type="button" @mousedown.prevent="pendingGif = null">×</button>
                                    </span>
                                    <span v-for="(f, i) in pendingFiles" :key="i" class="rounded-lg bg-slate-100 px-2 py-1 text-xs">{{ f.name }} <button type="button" @mousedown.prevent="pendingFiles.splice(i, 1)">×</button></span>
                                </div>
                                <div class="relative">
                                    <textarea
                                        ref="composerEl"
                                        v-model="composer"
                                        rows="4"
                                        class="block w-full resize-none border-0 bg-transparent px-3 py-3 pr-10 text-sm leading-relaxed focus:outline-none"
                                        placeholder="Type a message… (Enter to send, Shift+Enter for new line)"
                                        @keydown="onComposerKeydown"
                                        @input="onComposerInput"
                                        @blur="onComposerBlur"
                                    />
                                    <div
                                        v-if="emojiOpen"
                                        class="absolute bottom-full left-0 right-0 z-20 mb-1 max-h-36 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
                                    >
                                        <div class="grid grid-cols-8 gap-1">
                                            <button
                                                v-for="em in CHAT_EMOJIS"
                                                :key="em"
                                                type="button"
                                                class="rounded-lg p-1.5 text-lg hover:bg-primary-50 active:scale-95"
                                                @mousedown.prevent="insertEmoji(em)"
                                            >{{ em }}</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between border-t border-slate-100 px-2 py-1.5">
                                    <div class="flex gap-0.5">
                                        <button type="button" class="rounded-lg px-2 py-1.5 text-lg leading-none hover:bg-slate-100" title="Emoji" @mousedown.prevent="emojiOpen = !emojiOpen; gifOpen = false">😊</button>
                                        <label class="cursor-pointer rounded-lg px-2 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100">
                                            📎<input type="file" class="sr-only" multiple accept="image/*,.pdf" @change="onFiles" />
                                        </label>
                                        <button type="button" class="rounded-lg px-2 py-1.5 text-[10px] font-black uppercase text-slate-600 hover:bg-slate-100" :class="gifOpen ? 'bg-primary-100 text-primary-800' : ''" @mousedown.prevent="gifOpen = !gifOpen; emojiOpen = false">GIF</button>
                                    </div>
                                    <button type="submit" class="rounded-xl bg-primary-700 px-5 py-2.5 text-sm font-black uppercase tracking-wide text-white shadow-sm disabled:opacity-50" :disabled="sending || (!composer.trim() && !pendingFiles.length && !pendingGif)">
                                        Send
                                    </button>
                                </div>
                            </form>
                        </footer>
                    </template>

                    <!-- Feedback prompt -->
                    <div v-else-if="view === 'rate' && ticket" class="flex min-h-0 flex-1 flex-col overflow-y-auto p-6 text-center">
                        <p class="text-4xl" aria-hidden="true">💬</p>
                        <h3 class="mt-2 font-display text-lg font-black text-slate-900">Session ended</h3>
                        <p class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">
                            Need more help? Start a new chat anytime. We would love your feedback on this session (one review per chat).
                        </p>
                        <div v-if="ticket.rated || ratedDone" class="mt-6 rounded-xl bg-emerald-50 px-4 py-6 ring-1 ring-emerald-100">
                            <p class="text-sm font-black text-emerald-900">Thank you — feedback already received.</p>
                        </div>
                        <template v-else>
                            <div class="mt-6 flex flex-wrap justify-center gap-2">
                                <button
                                    v-for="r in closureReactions"
                                    :key="r.key"
                                    type="button"
                                    class="flex w-14 flex-col items-center rounded-2xl border border-slate-100 bg-slate-50 py-2 transition hover:border-primary-200 hover:bg-primary-50"
                                    @click="openFeedback(r.key)"
                                >
                                    <span class="text-2xl">{{ r.emoji }}</span>
                                </button>
                            </div>
                            <button
                                type="button"
                                class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-primary-700 py-3 text-sm font-black uppercase text-white shadow-md hover:bg-primary-800"
                                @click="openFeedback()"
                            >
                                Leave feedback
                            </button>
                        </template>
                        <button type="button" class="mt-6 text-xs font-bold text-slate-500 hover:text-primary-700" @click="resetToHome">Done</button>
                    </div>
                </div>
            </div>
        </Transition>

        <SupportFeedbackModal
            :open="feedbackOpen"
            :ticket-id="ticket?.id"
            :reactions="closureReactions"
            :survey="feedbackSurvey"
            :submit-url="feedbackSubmitUrl"
            :initial-reaction="feedbackPresetReaction"
            @close="feedbackOpen = false"
            @submitted="onFeedbackSubmitted"
        />
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ChatBubbleLeftRightIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import GifPickerPanel from '@/Components/Chat/GifPickerPanel.vue';
import SupportChatMessages from '@/Components/Support/SupportChatMessages.vue';
import SupportFeedbackModal from '@/Components/Support/SupportFeedbackModal.vue';
import { ensureEcho } from '@/utils/ensureEcho';
import { broadcastConfigFromPage } from '@/utils/broadcastConfig';
import { useSupportChatRealtime } from '@/composables/useSupportChatRealtime';
import { useChatComposer } from '@/composables/useChatComposer';
import { useMessagingViewPresence } from '@/composables/useMessagingViewPresence';

const CHAT_EMOJIS = ['😀', '😊', '🙂', '😉', '🙏', '👍', '👋', '❤️', '🎉', '✅', '⭐', '🔥', '💡', '😅', '😢', '😮', '🤔', '👏', '💪', '🙌', '✨', '💬', '📎', '🚀'];

const props = defineProps({
    bootstrap: { type: Object, default: () => ({ enabled: false }) },
});

const page = usePage();

const enabled = computed(() => props.bootstrap?.enabled === true);
const categories = ref({ ...(props.bootstrap?.categories ?? {}) });
const activeTicket = ref(props.bootstrap?.active_ticket ?? null);
const recentChats = ref([...(props.bootstrap?.recent_chats ?? [])]);
const unreadTotal = ref(props.bootstrap?.unread_total ?? 0);

const panelOpen = ref(false);
const view = ref('home');
const ticket = ref(null);
const messages = ref([]);
const composer = ref('');
const sending = ref(false);
const starting = ref(false);
const startError = ref('');
const gifOpen = ref(false);
const emojiOpen = ref(false);
const pendingGif = ref(null);
const pendingFiles = ref([]);
const typingAdmin = ref(null);
const scrollEl = ref(null);
const composerEl = ref(null);
const ratedDone = ref(false);
const feedbackOpen = ref(false);
const feedbackPresetReaction = ref('');
const closureReactions = ref([...(props.bootstrap?.closure_reactions ?? [])]);
const feedbackSurvey = ref([...(props.bootstrap?.feedback_survey ?? [])]);

const startForm = ref({ subject: '', category: '', initial_message: '' });

let typingStartDebounce = null;
let typingIdleTimer = null;
let typingActive = false;
let typingPollTimer = null;
let markReadTimer = null;

const chatPresence = useMessagingViewPresence(async () => {
    if (!ticket.value?.id) {
        return;
    }
    const last = [...messages.value].reverse().find((m) => !messageIsMine(m));
    await window.axios.post(r('api.support.chat.read', { ticket: ticket.value.id }), {
        last_message_id: last?.id ?? undefined,
    });
    window.dispatchEvent(new CustomEvent('app:notifications-changed'));
});

function liveBroadcastConfig() {
    return broadcastConfigFromPage(page);
}

function hasMessage(msg) {
    const id = Number(msg?.id);
    if (Number.isFinite(id) && id > 0) {
        return messages.value.some((m) => Number(m.id) === id);
    }

    return messages.value.some((m) => m.id === msg?.id);
}

const chatRealtime = useSupportChatRealtime({
    reverbConfig: liveBroadcastConfig,
    onMessage: (msg) => {
        if (hasMessage(msg)) {
            return false;
        }
        messages.value.push(msg);
        if (!messageIsMine(msg)) {
            if (view.value === 'chat' && panelOpen.value) {
                scheduleMarkRead();
            } else {
                unreadTotal.value += 1;
            }
        }
        scrollBottom();

        return true;
    },
    onSessionUpdated: async (t) => {
        if (t?.id !== ticket.value?.id) {
            return;
        }
        ticket.value = t;
        if (t.chat_status === 'closed') {
            view.value = 'chat';
            stopTyping();
            try {
                const { data } = await window.axios.get(r('api.support.chat.open', { ticket: t.id }));
                ticket.value = data.ticket ?? t;
                messages.value = data.messages ?? messages.value;
                chatRealtime.syncLastMessageId(messages.value);
                if (!ticket.value.rated && !ratedDone.value) {
                    feedbackOpen.value = true;
                }
            } catch {
                /* session.closed may arrive via websocket */
            }
            scrollBottom();
        }
    },
    onTyping: (e) => {
        if (e.side === 'admin') {
            typingAdmin.value = e.typing
                ? (e.first_name || supportAgentFirstName({ name: e.name }) || 'Support')
                : null;
        }
    },
});

const live = chatRealtime.wsConnected;

const headerTitle = computed(() => {
    if (view.value === 'start') return 'New conversation';
    if (view.value === 'chat' && ticket.value) return ticket.value.subject;
    if (view.value === 'rate') return 'Your feedback';
    return 'Support';
});

const headerSubtitle = computed(() => {
    if (view.value === 'chat' && ticket.value?.assigned_admin) {
        const first = supportAgentFirstName(ticket.value.assigned_admin);
        return first ? `Customer Support · ${first}` : 'Customer Support';
    }
    if (view.value === 'chat' && ticket.value?.chat_status === 'queued') return 'Waiting for an agent';
    return 'We\'re here to help';
});

const typingLabel = computed(() => {
    if (!typingAdmin.value) return '';
    const first = supportAgentFirstName({ name: typingAdmin.value });
    return `${first || 'Customer Support'} is typing`;
});

const feedbackSubmitUrl = computed(() => {
    const id = ticket.value?.id;
    if (!id) {
        return '';
    }

    return r('api.support.chat.feedback', { ticket: id });
});

function messageIsMine(m) {
    if (m.sender_type) {
        return m.sender_type === 'customer';
    }

    return !!m.mine;
}

function openFeedback(reactionKey = '') {
    feedbackPresetReaction.value = reactionKey || '';
    feedbackOpen.value = true;
}

function onFeedbackSubmitted() {
    ratedDone.value = true;
    if (ticket.value) {
        ticket.value = { ...ticket.value, rated: true };
    }
    feedbackOpen.value = false;
}

async function onMessageReact({ message, emoji }) {
    if (!ticket.value?.id || !message?.id) {
        return;
    }
    try {
        const { data } = await window.axios.post(
            r('api.support.chat.react', { ticket: ticket.value.id, message: message.id }),
            { emoji },
        );
        const idx = messages.value.findIndex((m) => m.id === message.id);
        if (idx >= 0 && data.message) {
            messages.value[idx] = data.message;
        }
    } catch {
        /* ignore */
    }
}

function supportAgentFirstName(person) {
    if (!person) return null;
    if (person.first_name?.trim()) {
        return person.first_name.trim();
    }
    const name = (person.name || '').trim();
    if (!name) return null;

    return name.split(/\s+/)[0] || null;
}

watch(
    () => props.bootstrap,
    (b) => {
        if (!b?.enabled) return;
        categories.value = { ...(b.categories ?? {}) };
        activeTicket.value = b.active_ticket ?? null;
        recentChats.value = [...(b.recent_chats ?? [])];
        unreadTotal.value = b.unread_total ?? 0;
    },
    { deep: true },
);

function stopTypingPoll() {
    if (typingPollTimer) {
        window.clearInterval(typingPollTimer);
        typingPollTimer = null;
    }
}

function startTypingPoll() {
    stopTypingPoll();
    if (!ticket.value?.id || view.value !== 'chat') {
        return;
    }
    const poll = async () => {
        if (!ticket.value?.id || view.value !== 'chat' || !panelOpen.value) {
            return;
        }
        try {
            const { data } = await window.axios.get(r('api.support.chat.typing-state', { ticket: ticket.value.id }));
            const t = data?.typing;
            const active = t && (t.typing === true || t.typing === 1);
            if (active && String(t.side ?? '').toLowerCase() === 'admin') {
                typingAdmin.value = t.first_name || supportAgentFirstName({ name: t.name }) || 'Support';
            } else if (!typingActive) {
                typingAdmin.value = null;
            }
        } catch {
            /* ignore */
        }
    };
    void poll();
    typingPollTimer = window.setInterval(poll, 1500);
}

onBeforeUnmount(() => {
    teardownRealtime();
    chatPresence.stop();
    stopTypingPoll();
    clearTimeout(markReadTimer);
});

function r(name, params = {}) {
    return window.route(name, params);
}

function openPanel() {
    panelOpen.value = true;
    view.value = activeTicket.value ? 'chat' : 'home';
    if (activeTicket.value) {
        resumeTicket(activeTicket.value);
    } else {
        ensureEcho(liveBroadcastConfig());
    }
}

async function refreshBootstrap() {
    try {
        const { data } = await window.axios.get(r('api.support.widget.bootstrap'));
        if (!data.enabled) return;
        categories.value = data.categories ?? {};
        activeTicket.value = data.active_ticket ?? null;
        recentChats.value = data.recent_chats ?? [];
        unreadTotal.value = data.unread_total ?? 0;
    } catch {
        /* ignore */
    }
}

async function resumeTicket(t) {
    view.value = 'chat';
    ticket.value = t;
    messages.value = [];
    typingAdmin.value = null;
    ensureEcho(liveBroadcastConfig());
    try {
        const { data } = await window.axios.get(r('api.support.chat.open', { ticket: t.id }));
        ticket.value = data.ticket;
        messages.value = data.messages ?? [];
        chatRealtime.syncLastMessageId(messages.value);
        subscribeRealtime(t.id);
        startTypingPoll();
        scrollBottom();
        unreadTotal.value = Math.max(0, unreadTotal.value - (t.unread_count ?? 0));
        chatPresence.start();
    } catch {
        startError.value = 'Could not open chat.';
    }
}

async function startChat() {
    starting.value = true;
    startError.value = '';
    try {
        const { data } = await window.axios.post(r('api.support.chat.start'), startForm.value);
        ticket.value = data.ticket;
        messages.value = data.messages ?? [];
        view.value = 'chat';
        activeTicket.value = data.ticket;
        chatRealtime.syncLastMessageId(messages.value);
        subscribeRealtime(data.ticket.id);
        startTypingPoll();
        scrollBottom();
        chatPresence.start();
        await refreshBootstrap();
    } catch (e) {
        startError.value = e.response?.data?.message || 'Could not start chat. Try again.';
    } finally {
        starting.value = false;
    }
}

function subscribeRealtime(ticketId) {
    chatRealtime.subscribe(ticketId, messages.value);
}

function teardownRealtime() {
    chatRealtime.teardown();
    stopTyping();
    stopTypingPoll();
    chatPresence.stop();
}

function scheduleMarkRead() {
    clearTimeout(markReadTimer);
    markReadTimer = setTimeout(() => {
        chatPresence.markNow();
    }, 300);
}

function scrollBottom() {
    nextTick(() => {
        if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
    });
}

function isImage(att) {
    return att?.type === 'gif' || att?.type === 'image' || String(att?.mime || '').startsWith('image/');
}

function attUrl(att) {
    return att?.url || '';
}

function insertEmoji(em) {
    composer.value += em;
    emojiOpen.value = false;
    composerEl.value?.focus();
}

function onGifSelected(gif) {
    pendingGif.value = gif;
    gifOpen.value = false;
}

function onFiles(e) {
    pendingFiles.value = [...pendingFiles.value, ...Array.from(e.target.files || [])];
    e.target.value = '';
}

function onComposerInput() {
    if (!ticket.value) {
        return;
    }
    clearTimeout(typingIdleTimer);
    clearTimeout(typingStartDebounce);
    typingStartDebounce = setTimeout(() => {
        if (!typingActive) {
            typingActive = true;
            window.axios.post(r('api.support.chat.typing', { ticket: ticket.value.id }), { typing: true }).catch(() => {});
        }
    }, 300);
    typingIdleTimer = setTimeout(stopTyping, 300);
}

function stopTyping() {
    clearTimeout(typingStartDebounce);
    typingStartDebounce = null;
    clearTimeout(typingIdleTimer);
    typingIdleTimer = null;
    if (!ticket.value || !typingActive) {
        return;
    }
    typingActive = false;
    window.axios.post(r('api.support.chat.typing', { ticket: ticket.value.id }), { typing: false }).catch(() => {});
}

function onComposerBlur() {
    if (sending.value) {
        return;
    }
    stopTyping();
}

function customerMessageWasDelivered(body, snap) {
    const trimmed = body.trim();
    if (trimmed) {
        return messages.value.some(
            (m) => m.sender_type === 'customer' && String(m.body ?? '').trim() === trimmed,
        );
    }
    if (snap.gif) {
        return messages.value.some((m) => m.sender_type === 'customer' && (m.attachments?.length ?? 0) > 0);
    }
    if (snap.files.length) {
        return messages.value.some((m) => m.sender_type === 'customer' && (m.attachments?.length ?? 0) > 0);
    }

    return false;
}

async function send() {
    if (!ticket.value || sending.value) {
        return;
    }
    const body = composer.value.trim();
    if (!body && !pendingFiles.value.length && !pendingGif.value) {
        return;
    }

    sending.value = true;
    stopTyping();
    emojiOpen.value = false;
    gifOpen.value = false;

    const fd = new FormData();
    if (body) {
        fd.append('body', body);
    }
    pendingFiles.value.forEach((f) => fd.append('attachments[]', f));
    if (pendingGif.value?.url) {
        fd.append('gif_url', pendingGif.value.url);
    }

    const snap = { body, gif: pendingGif.value, files: [...pendingFiles.value] };
    const optimisticId = `pending-${Date.now()}`;
    const optimistic = {
        id: optimisticId,
        body: body || (pendingGif.value ? '[GIF]' : '[Attachment]'),
        visibility: 'public',
        sender_type: 'customer',
        is_customer: true,
        mine: true,
        align: 'end',
        attachments: pendingGif.value ? [{ type: 'gif', url: pendingGif.value.url }] : [],
        created_at: new Date().toISOString(),
        pending: true,
    };

    messages.value.push(optimistic);
    composer.value = '';
    pendingGif.value = null;
    pendingFiles.value = [];
    scrollBottom();

    try {
        const { data } = await window.axios.post(r('api.support.chat.send', { ticket: ticket.value.id }), fd);
        messages.value = messages.value.filter((m) => m.id !== optimisticId);
        const serverMsg = data?.message;
        if (serverMsg && !hasMessage(serverMsg)) {
            messages.value.push(serverMsg);
        }
        if (serverMsg?.id) {
            chatRealtime.setLastMessageId(serverMsg.id);
        }
        scrollBottom();
    } catch {
        messages.value = messages.value.filter((m) => m.id !== optimisticId);
        if (!customerMessageWasDelivered(body, snap)) {
            composer.value = snap.body;
            pendingGif.value = snap.gif;
            pendingFiles.value = snap.files;
        }
    } finally {
        sending.value = false;
    }
}

const { onComposerKeydown } = useChatComposer(() => {
    if (sending.value) {
        return;
    }
    void send();
});

function resetToHome() {
    view.value = 'home';
    ticket.value = null;
    messages.value = [];
    ratedDone.value = false;
    teardownRealtime();
    refreshBootstrap();
}

watch(
    () => ticket.value?.chat_status,
    (status) => {
        if (status === 'closed' && ticket.value && !ticket.value.rated && !ratedDone.value) {
            feedbackOpen.value = true;
        }
    },
);

watch(panelOpen, (open) => {
    if (open) {
        ensureEcho(liveBroadcastConfig());
        if (view.value === 'chat' && ticket.value?.id) {
            subscribeRealtime(ticket.value.id);
            startTypingPoll();
        }
    } else {
        teardownRealtime();
    }
});
</script>

<style scoped>
@keyframes support-bubble-bounce {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

.support-bubble-launcher {
    animation: support-bubble-bounce 2.8s ease-in-out infinite;
}
</style>
