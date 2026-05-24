<template>
    <div class="flex min-h-[calc(100dvh-12rem)] w-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
        <div class="flex min-h-0 flex-1">
            <div
                class="flex w-full shrink-0 flex-col border-r border-slate-100 bg-slate-50/80 md:w-72 lg:w-80 dark:border-white/10 dark:bg-slate-900/50"
                :class="showThread && isMobile ? 'hidden' : 'flex'"
            >
                <div class="border-b border-slate-100 p-3 dark:border-white/10">
                    <input
                        v-model="searchQ"
                        type="search"
                        placeholder="Search conversations…"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-slate-950 dark:text-white"
                        @input="debouncedSearch"
                    />
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <button
                        v-for="c in conversations"
                        :key="c.id"
                        type="button"
                        class="flex w-full gap-3 border-b border-slate-100 px-3 py-3 text-left transition hover:bg-white dark:border-white/10 dark:hover:bg-white/5"
                        :class="activeConversation?.id === c.id ? 'bg-white ring-1 ring-inset ring-primary-200 dark:bg-white/5' : ''"
                        @click="openConversation(c)"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-teal-700 text-xs font-black text-white">
                            {{ initials(c.participant?.name) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-black text-slate-900 dark:text-white">{{ c.participant?.name }}</span>
                                <span class="shrink-0 text-[10px] font-semibold text-slate-400">{{ formatShort(c.last_message_at) }}</span>
                            </span>
                            <span class="mt-0.5 flex items-center gap-2">
                                <span class="rounded-full bg-primary-50 px-1.5 py-0.5 text-[9px] font-black uppercase text-primary-800 dark:bg-primary-400/15 dark:text-primary-200">{{ c.participant?.role }}</span>
                                <span v-if="c.unread_count" class="rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-black text-white">{{ c.unread_count }}</span>
                            </span>
                            <p class="mt-1 truncate text-xs font-medium text-slate-500">{{ previewText(c) }}</p>
                        </span>
                    </button>
                    <p v-if="!conversations.length" class="px-4 py-8 text-center text-sm text-slate-500">No conversations yet. Start one below.</p>
                </div>
                <div class="border-t border-slate-100 p-3 dark:border-white/10">
                    <p class="mb-2 text-[10px] font-black uppercase text-slate-400">New message</p>
                    <select v-model="newRecipientId" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-slate-950 dark:text-white">
                        <option value="">Select admin…</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }} (@{{ s.username }})</option>
                    </select>
                    <button
                        type="button"
                        class="mt-2 w-full rounded-lg bg-primary-700 py-2 text-xs font-black uppercase text-white disabled:opacity-50"
                        :disabled="!newRecipientId"
                        @click="startNewConversation"
                    >
                        Open chat
                    </button>
                </div>
            </div>

            <div class="flex min-w-0 flex-1 flex-col" :class="!showThread && isMobile ? 'hidden md:flex' : 'flex'">
                <template v-if="activeConversation">
                    <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 dark:border-white/10">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-teal-700 text-xs font-black text-white">
                            {{ initials(activeConversation.participant?.name) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-black text-slate-900 dark:text-white">{{ activeConversation.participant?.name }}</p>
                            <p class="text-[10px] font-bold uppercase text-primary-700 dark:text-primary-300">{{ activeConversation.participant?.role }}</p>
                        </div>
                        <button
                            v-if="activeConversation.participant?.id"
                            type="button"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                            @click="emit('open-activity', activeConversation.participant)"
                        >
                            Activity
                        </button>
                    </div>

                    <div ref="streamEl" class="flex-1 space-y-2 overflow-y-auto px-4 py-4">
                        <template v-for="group in messageDayGroups" :key="group.dayKey">
                            <div v-if="group.label" class="flex justify-center py-1">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-slate-500 dark:bg-white/10">{{ group.label }}</span>
                            </div>
                            <div
                                v-for="m in group.messages"
                                :key="m.id"
                                class="flex"
                                :class="m.mine ? 'justify-end' : 'justify-start'"
                            >
                                <div class="max-w-[85%] rounded-xl px-3 py-2 text-sm shadow-sm" :class="m.mine ? 'bg-primary-700 text-white' : 'border border-slate-100 bg-white text-slate-900 dark:border-white/10 dark:bg-slate-900 dark:text-white'">
                                    <p v-if="m.body" class="whitespace-pre-wrap break-words" v-html="formatBody(m.body)" />
                                    <div v-if="m.attachments?.length" class="mt-2 grid gap-2">
                                        <template v-for="(att, i) in m.attachments" :key="i">
                                            <img
                                                v-if="isGifAttachment(att) || isImageAttachment(att)"
                                                :src="attachmentUrl(att)"
                                                :alt="att.name || 'Attachment'"
                                                class="max-h-48 rounded-lg"
                                                :class="isGifAttachment(att) ? 'object-contain' : 'object-cover'"
                                                loading="lazy"
                                            />
                                            <a v-else :href="attachmentUrl(att)" target="_blank" rel="noopener" class="text-xs font-semibold underline">{{ att.name }}</a>
                                        </template>
                                    </div>
                                    <p class="mt-1 flex items-center justify-end gap-1 text-[10px] font-semibold opacity-80">
                                        <span>{{ formatChatMessageTime(m.created_at) }}</span>
                                        <span v-if="m.mine" :title="m.status">{{ statusTicks(m.status) }}</span>
                                    </p>
                                </div>
                            </div>
                        </template>
                        <p v-if="typingLabel" class="text-xs font-semibold text-primary-700">{{ typingLabel }}</p>
                    </div>

                    <footer class="relative border-t border-slate-100 p-3 dark:border-white/10">
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
                                class="absolute bottom-full left-3 right-3 z-40 mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
                            >
                                <GifPickerPanel :open="gifOpen" :search-url="r('api.messenger.gifs')" @select="onGifSelected" @close="gifOpen = false" />
                            </div>
                        </Transition>

                        <form class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm focus-within:border-primary-300 focus-within:ring-2 focus-within:ring-primary-100 dark:border-white/10 dark:bg-slate-950" @submit.prevent="send">
                            <ul
                                v-if="mentionOpen"
                                class="absolute bottom-full left-0 right-0 z-30 mb-1 max-h-40 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-white/10 dark:bg-slate-950"
                            >
                                <li v-if="!filteredMentions.length" class="px-3 py-2 text-xs font-semibold text-slate-500">No matching staff</li>
                                <li v-for="(user, idx) in filteredMentions" :key="user.id">
                                    <button
                                        type="button"
                                        class="flex w-full px-3 py-2 text-left text-sm hover:bg-primary-50 dark:hover:bg-primary-400/10"
                                        :class="idx === mentionIdx ? 'bg-primary-50 dark:bg-primary-400/10' : ''"
                                        @mousedown.prevent="pickMention(user)"
                                    >
                                        <span class="font-black">@{{ user.username }}</span>
                                        <span class="ml-2 text-slate-500">{{ user.name }}</span>
                                    </button>
                                </li>
                            </ul>

                            <div v-if="pendingGif || pendingFiles.length" class="flex flex-wrap gap-2 border-b border-slate-100 px-3 py-2 dark:border-white/10">
                                <span v-if="pendingGif" class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-2 py-1 text-xs dark:bg-white/10">
                                    <img :src="pendingGif.preview || pendingGif.url" alt="" class="h-8 w-8 rounded object-cover" />
                                    GIF
                                    <button type="button" @mousedown.prevent="pendingGif = null">×</button>
                                </span>
                                <span v-for="(f, i) in pendingFiles" :key="i" class="rounded-lg bg-slate-100 px-2 py-1 text-xs dark:bg-white/10">{{ f.name }} <button type="button" @mousedown.prevent="removeFile(i)">×</button></span>
                            </div>
                            <textarea
                                ref="composerEl"
                                v-model="composer"
                                rows="2"
                                class="block w-full resize-none border-0 bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-0 dark:text-white"
                                placeholder="Message… @username to mention"
                                @input="onComposerInput"
                                @keydown="onComposerKeydown"
                                @blur="stopTyping"
                            />
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-2 py-2 dark:border-white/10">
                                <div class="relative flex items-center gap-1">
                                    <label class="cursor-pointer rounded-lg px-2 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10">
                                        Attach
                                        <input type="file" class="sr-only" multiple @change="onFiles" />
                                    </label>
                                    <button type="button" class="rounded-lg px-2 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10" @mousedown.prevent="showEmoji = !showEmoji; gifOpen = false">😊</button>
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1.5 text-xs font-bold transition"
                                        :class="gifOpen ? 'bg-primary-100 text-primary-800 dark:bg-primary-400/15' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'"
                                        @mousedown.prevent="gifOpen = !gifOpen; showEmoji = false"
                                    >
                                        GIF
                                    </button>
                                    <div v-if="showEmoji" class="absolute bottom-full left-0 z-20 mb-1 flex flex-wrap gap-1 rounded-xl border bg-white p-2 shadow-lg dark:border-white/10 dark:bg-slate-950">
                                        <button v-for="em in emojis" :key="em" type="button" class="text-lg" @mousedown.prevent="composer += em">{{ em }}</button>
                                    </div>
                                </div>
                                <button type="submit" class="rounded-lg bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white disabled:opacity-50" :disabled="sending || (!composer.trim() && !pendingFiles.length && !pendingGif)">
                                    {{ sending ? 'Sending…' : 'Send' }}
                                </button>
                            </div>
                        </form>
                    </footer>
                </template>
                <div v-else class="flex flex-1 flex-col items-center justify-center p-8 text-center text-sm text-slate-500">
                    <p class="font-semibold text-slate-700 dark:text-slate-200">Select a conversation</p>
                    <p class="mt-1">Or start a new chat with another admin.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import GifPickerPanel from '@/Components/Chat/GifPickerPanel.vue';
import { attachmentUrl, isGifAttachment, isImageAttachment } from '@/utils/chatAttachments';
import { ensureEcho } from '@/utils/ensureEcho';
import { formatChatMessageTime, groupMessagesByChatDay } from '@/utils/chatMessageDates';

const POLL_MS = 800;

const props = defineProps({
    routeNamespace: { type: String, default: 'admin' },
    eventPrefix: { type: String, default: 'admin' },
    initialConversationId: { type: [String, Number], default: null },
});

const emit = defineEmits(['unread-changed', 'open-activity']);

const page = usePage();
const myUserId = computed(() => page.props.auth?.user?.id);

const conversations = ref([]);
const staff = ref([]);
const messages = ref([]);
const activeConversation = ref(null);
const hasMore = ref(false);
const composer = ref('');
const searchQ = ref('');
const newRecipientId = ref('');
const pendingFiles = ref([]);
const sending = ref(false);
const connectionMode = ref('polling');
const showThread = ref(false);
const isMobile = ref(false);
const streamEl = ref(null);
const composerEl = ref(null);

const mentionOpen = ref(false);
const mentionQuery = ref('');
const mentionIdx = ref(0);
const showEmoji = ref(false);
const gifOpen = ref(false);
const pendingGif = ref(null);
const typingUsers = ref([]);

const emojis = ['😀', '😊', '👍', '❤️', '🙏', '🎉', '🔥', '✅'];

let channel = null;
let pollTimer = null;
let typingTimer = null;
let typingExpiry = {};
let searchDebounce = null;
let markReadTimer = null;
let isTyping = false;

const mentionables = computed(() => {
    const list = [...staff.value];
    const participant = activeConversation.value?.participant;
    if (participant && !list.some((u) => String(u.id) === String(participant.id))) {
        list.unshift(participant);
    }

    return list;
});

const filteredMentions = computed(() => {
    const q = mentionQuery.value.toLowerCase();
    return mentionables.value
        .filter((u) => u.username && (!q || u.username?.toLowerCase().includes(q) || u.name?.toLowerCase().includes(q)))
        .slice(0, 10);
});

const typingLabel = computed(() => {
    const names = typingUsers.value.map((t) => t.name);
    if (!names.length) return '';
    return names.length === 1 ? `${names[0]} is typing…` : `${names.join(', ')} are typing…`;
});

const messageDayGroups = computed(() => groupMessagesByChatDay(messages.value));

onMounted(async () => {
    const mq = window.matchMedia('(max-width: 767px)');
    const apply = () => {
        isMobile.value = mq.matches;
    };
    apply();
    mq.addEventListener('change', apply);
    onBeforeUnmount(() => mq.removeEventListener('change', apply));

    await loadBootstrap();
    restartPoll();
    bindEcho();

    if (props.initialConversationId) {
        const found = conversations.value.find((c) => String(c.id) === String(props.initialConversationId));
        if (found) {
            await openConversation(found);
        }
    }

    const openEvent = `${props.eventPrefix}:open-messenger`;
    window.addEventListener(openEvent, onOpenMessengerEvent);
    onBeforeUnmount(() => window.removeEventListener(openEvent, onOpenMessengerEvent));
});

onBeforeUnmount(() => {
    teardownEcho();
    clearInterval(pollTimer);
});

function r(name, params = {}) {
    return route(`${props.routeNamespace}.${name}`, params);
}

function notifyChanged() {
    window.dispatchEvent(new CustomEvent(`${props.eventPrefix}:notifications-changed`));
    window.dispatchEvent(new CustomEvent(`${props.eventPrefix}:messenger-changed`));
}

function normalizeMessage(msg) {
    if (!msg) return null;
    const mine = Number(msg.sender?.id) === Number(myUserId.value);
    return {
        ...msg,
        mine,
    };
}

async function loadBootstrap() {
    const { data } = await window.axios.get(r('api.messenger.bootstrap'));
    staff.value = data.staff ?? [];
    conversations.value = data.conversations ?? [];
    emit('unread-changed', data.unread_count ?? 0);
}

async function refreshUnread() {
    const { data } = await window.axios.get(r('api.messenger.unread-count'));
    emit('unread-changed', data.count ?? 0);
}

async function searchConversations() {
    const { data } = await window.axios.get(r('api.messenger.conversations'), { params: { q: searchQ.value || undefined } });
    conversations.value = data.conversations ?? [];
}

function debouncedSearch() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(searchConversations, 300);
}

async function openConversation(c) {
    if (!c?.id) return;
    showThread.value = true;
    activeConversation.value = c;
    try {
        const { data } = await window.axios.get(r('api.messenger.messages', { conversation: c.id }));
        messages.value = (data.items ?? []).map(normalizeMessage).filter(Boolean);
        hasMore.value = data.has_more ?? false;
    } catch {
        if (c.participant?.id) {
            const { data } = await window.axios.post(r('api.messenger.open', { recipient: c.participant.id }));
            activeConversation.value = data.conversation ?? c;
            messages.value = (data.messages ?? []).map(normalizeMessage).filter(Boolean);
            hasMore.value = data.has_more ?? false;
        }
    }
    await scrollBottom();
    scheduleMarkRead();
    bindConversationChannel();
    void pollMessages();
}

async function startNewConversation() {
    const recipient = staff.value.find((s) => String(s.id) === String(newRecipientId.value));
    if (!recipient) return;
    const existing = conversations.value.find((c) => c.participant?.id === recipient.id);
    if (existing) {
        await openConversation(existing);
    } else {
        showThread.value = true;
        const { data } = await window.axios.post(r('api.messenger.open', { recipient: recipient.id }));
        activeConversation.value = data.conversation;
        messages.value = (data.messages ?? []).map(normalizeMessage).filter(Boolean);
        hasMore.value = data.has_more ?? false;
        await scrollBottom();
        bindConversationChannel();
        await loadBootstrap();
    }
    newRecipientId.value = '';
}

function bindEcho() {
    ensureEcho(page.props.reverb);
}

function bindConversationChannel() {
    teardownEcho();
    if (!activeConversation.value) return;
    const echo = ensureEcho(page.props.reverb);
    if (!echo) return;

    channel = echo.private(`admin-dm.${activeConversation.value.id}`);
    channel.listen('.message.sent', (payload) => {
        const msg = normalizeMessage(payload?.message);
        if (!msg) return;
        upsertMessage(msg);
        if (!msg.mine) {
            window.axios.post(r('api.messenger.delivered', { message: msg.id })).catch(() => {});
            scheduleMarkRead();
            notifyChanged();
        }
        refreshListPreview(msg);
    });
    channel.listen('.typing', (p) => {
        if (p.user_id === myUserId.value) return;
        setTyping(p.user_id, p.user_name, p.typing);
    });
    if (typeof channel.subscribed === 'function') {
        channel.subscribed(() => {
            connectionMode.value = 'live';
        });
    }
}

function teardownEcho() {
    if (channel && window.Echo && activeConversation.value) {
        window.Echo.leave(`admin-dm.${activeConversation.value.id}`);
    }
    channel = null;
}

function restartPoll() {
    clearInterval(pollTimer);
    pollTimer = setInterval(() => {
        if (activeConversation.value) void pollMessages();
        void refreshUnread();
    }, POLL_MS);
}

async function pollMessages() {
    if (!activeConversation.value) return;
    const last = [...messages.value].reverse().find((m) => !String(m.id).startsWith('tmp-'));
    const afterId = last?.id ?? 0;
    try {
        const { data } = await window.axios.get(r('api.messenger.messages', { conversation: activeConversation.value.id }), {
            params: { after_id: afterId },
        });
        for (const raw of data.items ?? []) {
            const msg = normalizeMessage(raw);
            if (!msg) continue;
            if (!messages.value.some((m) => m.id === msg.id)) {
                upsertMessage(msg);
                if (!msg.mine) {
                    window.axios.post(r('api.messenger.delivered', { message: msg.id })).catch(() => {});
                    notifyChanged();
                }
            } else {
                const idx = messages.value.findIndex((m) => m.id === msg.id);
                if (idx >= 0) messages.value[idx] = msg;
            }
        }
    } catch {
        // ignore polling errors
    }
}

function upsertMessage(msg) {
    const normalized = normalizeMessage(msg);
    if (!normalized) return;
    const idx = messages.value.findIndex((m) => m.id === normalized.id);
    if (idx >= 0) {
        messages.value[idx] = normalized;
        return;
    }
    const tmpIdx = messages.value.findIndex((m) => String(m.id).startsWith('tmp-') && m.body === normalized.body && m.mine);
    if (tmpIdx >= 0) {
        messages.value[tmpIdx] = normalized;
    } else {
        messages.value.push(normalized);
    }
    scrollBottom();
}

function refreshListPreview(msg) {
    const c = conversations.value.find((x) => x.id === msg.conversation_id);
    if (c) {
        c.last_message = { id: msg.id, body: msg.body, sender_id: msg.sender?.id, created_at: msg.created_at };
        c.last_message_at = msg.created_at;
        if (!msg.mine) c.unread_count = (c.unread_count ?? 0) + 1;
    }
    conversations.value.sort((a, b) => (b.last_message_at || '').localeCompare(a.last_message_at || ''));
}

function onGifSelected(gif) {
    if (!gif?.url) return;
    pendingGif.value = gif;
    gifOpen.value = false;
}

async function onOpenMessengerEvent(event) {
    const conversationId = event?.detail?.conversationId;
    if (!conversationId) return;
    await loadBootstrap();
    const found = conversations.value.find((c) => String(c.id) === String(conversationId));
    if (found) await openConversation(found);
}

async function send() {
    if (!activeConversation.value) return;
    const body = composer.value.trim();
    if (!body && !pendingFiles.value.length && !pendingGif.value) return;
    stopTyping();
    sending.value = true;
    const gifUrl = pendingGif.value?.url;
    const tmp = {
        id: `tmp-${Date.now()}`,
        body,
        mine: true,
        status: 'sent',
        attachments: gifUrl ? [{ type: 'gif', url: gifUrl, remote: true, mime: 'image/gif', name: 'GIF' }] : [],
        created_at: new Date().toISOString(),
        sender: { id: myUserId.value },
    };
    messages.value.push(tmp);
    const fd = new FormData();
    fd.append('body', body);
    pendingFiles.value.forEach((f) => fd.append('attachments[]', f));
    if (pendingGif.value?.url) {
        fd.append('gif_url', pendingGif.value.url);
    }
    composer.value = '';
    const filesSnap = pendingFiles.value;
    const gifSnap = pendingGif.value;
    pendingFiles.value = [];
    pendingGif.value = null;
    await scrollBottom();
    try {
        const { data } = await window.axios.post(r('api.messenger.send', { conversation: activeConversation.value.id }), fd);
        const message = normalizeMessage(data.message);
        upsertMessage(message);
        refreshListPreview(message);
    } catch {
        messages.value = messages.value.filter((m) => m.id !== tmp.id);
        composer.value = body;
        pendingFiles.value = filesSnap;
        pendingGif.value = gifSnap;
    } finally {
        sending.value = false;
    }
}

function onComposerInput() {
    updateMention();
    clearTimeout(typingTimer);
    if (!isTyping) {
        isTyping = true;
        window.axios.post(r('api.messenger.typing', { conversation: activeConversation.value.id }), { typing: true }).catch(() => {});
    }
    typingTimer = setTimeout(stopTyping, 1000);
}

function stopTyping() {
    clearTimeout(typingTimer);
    if (isTyping && activeConversation.value) {
        isTyping = false;
        window.axios.post(r('api.messenger.typing', { conversation: activeConversation.value.id }), { typing: false }).catch(() => {});
    }
}

function onComposerKeydown(e) {
    if (mentionOpen.value && filteredMentions.value.length) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            mentionIdx.value = (mentionIdx.value + 1) % filteredMentions.value.length;
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            mentionIdx.value = (mentionIdx.value - 1 + filteredMentions.value.length) % filteredMentions.value.length;
            return;
        }
        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            pickMention(filteredMentions.value[mentionIdx.value]);
            return;
        }
    }
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

function updateMention() {
    const el = composerEl.value;
    if (!el) return;
    const pos = el.selectionStart ?? composer.value.length;
    const before = composer.value.slice(0, pos);
    const match = before.match(/@([a-zA-Z0-9_.-]*)$/);
    if (!match) {
        mentionOpen.value = false;
        return;
    }
    mentionOpen.value = true;
    mentionQuery.value = match[1] || '';
    mentionIdx.value = 0;
}

function pickMention(user) {
    const el = composerEl.value;
    if (!el || !user?.username) return;
    const pos = el.selectionStart ?? composer.value.length;
    const before = composer.value.slice(0, pos);
    const after = composer.value.slice(pos);
    const match = before.match(/@([a-zA-Z0-9_.-]*)$/);
    if (!match) return;
    const next = before.slice(0, -match[0].length) + `@${user.username} ` + after;
    composer.value = next;
    mentionOpen.value = false;
    nextTick(() => {
        el.focus();
        const caret = before.length - match[0].length + user.username.length + 2;
        el.setSelectionRange(caret, caret);
    });
}

function onFiles(e) {
    pendingFiles.value.push(...Array.from(e.target.files || []));
    e.target.value = '';
}

function removeFile(i) {
    pendingFiles.value.splice(i, 1);
}

function scheduleMarkRead() {
    clearTimeout(markReadTimer);
    markReadTimer = setTimeout(async () => {
        if (!activeConversation.value) return;
        const last = [...messages.value].reverse().find((m) => !m.mine && !String(m.id).startsWith('tmp-'));
        await window.axios.post(r('api.messenger.read', { conversation: activeConversation.value.id }), {
            up_to_message_id: last?.id,
        });
        void refreshUnread();
        void loadBootstrap();
    }, 400);
}

function setTyping(userId, name, active) {
    const key = String(userId);
    if (active) {
        typingUsers.value = [...typingUsers.value.filter((t) => t.key !== key), { key, name }];
        clearTimeout(typingExpiry[key]);
        typingExpiry[key] = setTimeout(() => {
            typingUsers.value = typingUsers.value.filter((t) => t.key !== key);
        }, 2500);
    } else {
        typingUsers.value = typingUsers.value.filter((t) => t.key !== key);
    }
}

function statusTicks(status) {
    if (status === 'read') return '✓✓';
    if (status === 'delivered') return '✓✓';
    return '✓';
}

function formatBody(body) {
    if (!body) return '';
    let safe = String(body).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    for (const u of staff.value) {
        if (!u.username) continue;
        const re = new RegExp(`@${u.username.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?=\\s|$)`, 'gi');
        safe = safe.replace(re, `<span class="font-black underline">@${u.username}</span>`);
    }
    return safe.replace(/\n/g, '<br>');
}

function previewText(c) {
    const b = c.last_message?.body;
    if (!b?.trim()) return c.last_message ? 'Attachment' : 'No messages yet';
    if (b.includes('tenor.com') || b.includes('giphy.com')) return 'GIF';
    return b.length > 48 ? b.slice(0, 48) + '…' : b;
}

function initials(name) {
    const p = (name || 'A').trim().split(/\s+/);
    return ((p[0]?.[0] || 'A') + (p[1]?.[0] || '')).toUpperCase();
}

function formatShort(iso) {
    try {
        const d = new Date(iso);
        const now = new Date();
        if (d.toDateString() === now.toDateString()) {
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    } catch {
        return '';
    }
}

async function scrollBottom() {
    await nextTick();
    if (streamEl.value) streamEl.value.scrollTop = streamEl.value.scrollHeight;
}
</script>
