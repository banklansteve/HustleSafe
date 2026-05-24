<template>
    <div class="flex h-[calc(100vh-12rem)] min-h-[520px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:flex-row">
        <aside class="hidden w-52 shrink-0 border-r border-slate-100 bg-slate-50/80 p-3 lg:block">
            <p class="px-2 text-[10px] font-black uppercase tracking-wide text-slate-400">Rooms</p>
            <button
                v-for="r in rooms"
                :key="r.id"
                type="button"
                class="mt-2 w-full rounded-lg px-3 py-2.5 text-left text-sm font-black transition active:scale-[0.98]"
                :class="r.id === room.id ? 'bg-primary-700 text-white shadow-md' : 'text-slate-700 hover:bg-white'"
                @click="selectRoom(r)"
            >
                {{ r.name }}
            </button>
        </aside>

        <section class="flex min-w-0 flex-1 flex-col">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                <div>
                    <p class="text-[10px] font-black uppercase text-primary-700">Team chat</p>
                    <h2 class="font-display text-lg font-black text-slate-950">{{ room.name }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="hidden rounded-full px-2 py-0.5 text-[10px] font-black uppercase sm:inline-flex"
                        :class="connectionMode === 'live' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'"
                    >
                        {{ connectionMode === 'live' ? 'Live' : 'Polling' }}
                    </span>
                    <input v-model="searchQ" type="search" placeholder="Search messages…" class="w-40 rounded-lg border border-slate-200 px-3 py-1.5 text-xs sm:w-52" @keyup.enter="runSearch" />
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-black text-slate-700 lg:hidden" @click="showMembers = !showMembers">Team</button>
                </div>
            </header>

            <p v-if="loadError" class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-900">{{ loadError }}</p>
            <p v-if="sendError" class="border-b border-rose-100 bg-rose-50 px-4 py-2 text-xs font-semibold text-rose-800">{{ sendError }}</p>

            <div ref="streamEl" class="flex-1 space-y-1 overflow-y-auto px-4 py-4" @scroll="onScroll">
                <button v-if="hasMore" type="button" class="mb-3 w-full rounded-lg border border-slate-200 py-2 text-xs font-black text-slate-600 hover:bg-slate-50 active:scale-[0.98]" :disabled="loadingMore" @click="loadOlder">Load older</button>

                <template v-for="group in groupedMessages" :key="group.key">
                    <div v-if="group.showDayDivider && group.dayLabel" class="py-2 text-center">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-slate-500">
                            {{ group.dayLabel }}
                        </span>
                    </div>
                    <div class="group/msg flex gap-2" :class="group.mine ? 'flex-row-reverse' : ''">
                        <div
                            v-if="!group.mine && group.showAvatar"
                            class="mt-1 flex h-8 w-8 shrink-0 overflow-hidden rounded-full bg-gradient-to-br from-primary-600 to-teal-700 ring-1 ring-slate-200"
                        >
                            <img
                                v-if="group.avatar && !brokenAvatarIds[group.id]"
                                :src="group.avatar"
                                :alt="group.senderName"
                                class="h-full w-full object-cover"
                                @error="markAvatarBroken(group.id)"
                            />
                            <span v-else class="flex h-full w-full items-center justify-center text-[10px] font-black text-white">{{ group.initials }}</span>
                        </div>
                        <div v-else-if="!group.mine" class="w-8 shrink-0" />

                        <div class="relative max-w-[85%] sm:max-w-[72%]">
                            <div
                                v-if="!group.mine"
                                class="pointer-events-none absolute -top-11 left-0 z-10 flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 shadow-lg opacity-0 transition-opacity duration-150 group-hover/msg:pointer-events-auto group-hover/msg:opacity-100"
                            >
                                <button
                                    v-for="emoji in reactionOptions"
                                    :key="emoji"
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-xl leading-none transition hover:scale-110 hover:bg-slate-50 active:scale-95"
                                    :title="`React with ${emoji}`"
                                    @click="toggleReaction(group.id, emoji)"
                                >
                                    {{ emoji }}
                                </button>
                            </div>

                            <div v-if="group.showMeta" class="mb-1 flex flex-wrap items-center gap-2" :class="group.mine ? 'justify-end' : ''">
                                <span class="text-xs font-black text-slate-900">{{ group.senderName }}</span>
                                <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[9px] font-black uppercase text-primary-800">{{ group.role }}</span>
                                <span v-if="group.official" class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-black uppercase text-amber-900">Official</span>
                            </div>

                            <div class="rounded-xl px-4 py-2.5 text-sm font-medium shadow-sm" :class="group.mine ? 'bg-primary-700 text-white' : 'border border-slate-100 bg-white text-slate-900'">
                                <p
                                    v-if="group.bodyHtml"
                                    class="whitespace-pre-wrap break-words [&_.mention-link]:font-black [&_.mention-link]:underline [&_.mention-link]:decoration-2 [&_.mention-link]:underline-offset-2"
                                    :class="group.mine ? '[&_.mention-link]:text-primary-100' : '[&_.mention-link]:text-primary-700'"
                                    v-html="group.bodyHtml"
                                />
                                <div v-if="group.attachments?.length" class="mt-2 grid gap-2">
                                    <template v-for="(att, i) in group.attachments" :key="i">
                                        <img
                                            v-if="isGifAttachment(att) || isImageAttachment(att)"
                                            :src="attachmentUrl(att)"
                                            :alt="att.name || 'Attachment'"
                                            class="max-h-48 w-full rounded-lg"
                                            :class="isGifAttachment(att) ? 'object-contain' : 'object-cover'"
                                            loading="lazy"
                                        />
                                        <a
                                            v-else
                                            :href="attachmentUrl(att)"
                                            target="_blank"
                                            rel="noopener"
                                            class="flex items-center gap-2 rounded-lg border border-white/20 px-3 py-2 text-xs font-semibold underline"
                                        >
                                            {{ att.name }}
                                        </a>
                                    </template>
                                </div>
                            </div>

                            <div v-if="hasReactions(group)" class="mt-1 flex flex-wrap gap-1" :class="group.mine ? 'justify-end' : ''">
                                <button
                                    v-for="(rx, emoji) in group.reactions"
                                    :key="emoji"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-bold text-slate-700 shadow-sm transition active:scale-95"
                                    :class="rx.mine ? 'border-primary-200 bg-primary-50 text-primary-900' : ''"
                                    @click="toggleReaction(group.id, emoji)"
                                >
                                    {{ emoji }} <span>{{ rx.count }}</span>
                                </button>
                            </div>

                            <p class="mt-1 text-[10px] font-semibold text-slate-400" :class="group.mine ? 'text-right' : ''">{{ group.time }}<span v-if="group.read_count"> · {{ group.read_count }} read</span></p>
                        </div>
                    </div>
                </template>

                <p v-if="typingLabel" class="text-xs font-semibold text-primary-700">{{ typingLabel }}</p>
            </div>

            <footer class="relative border-t border-slate-100 p-3">
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-2 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="translate-y-2 opacity-0"
                >
                    <div
                        v-if="showGifPicker"
                        class="absolute bottom-full left-3 right-3 z-40 mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    >
                        <GifPickerPanel
                            :open="showGifPicker"
                            :search-url="r('api.team-chat.gifs')"
                            @select="onGifSelected"
                            @close="showGifPicker = false"
                        />
                    </div>
                </Transition>

                <form class="w-full" @submit.prevent="send">
                    <div class="relative w-full rounded-xl border border-slate-200 bg-white shadow-sm ring-1 ring-slate-100 focus-within:border-primary-300 focus-within:ring-2 focus-within:ring-primary-100">
                        <div v-if="pendingGif || files.length" class="flex flex-wrap gap-2 border-b border-slate-100 px-3 py-2">
                            <div v-if="pendingGif" class="flex max-w-[200px] items-center gap-2 rounded-lg bg-slate-50 px-2 py-1.5 text-xs font-semibold text-slate-700">
                                <img :src="pendingGif.preview || pendingGif.url" alt="" class="h-8 w-8 rounded object-cover" />
                                <span class="truncate">GIF</span>
                                <button type="button" class="shrink-0 text-slate-400 hover:text-rose-600" aria-label="Remove GIF" @mousedown.prevent="pendingGif = null">×</button>
                            </div>
                            <div v-for="(file, idx) in files" :key="idx" class="flex max-w-[200px] items-center gap-2 rounded-lg bg-slate-50 px-2 py-1.5 text-xs font-semibold text-slate-700">
                                <img v-if="filePreview(file)" :src="filePreview(file)" alt="" class="h-8 w-8 rounded object-cover" />
                                <span class="truncate">{{ file.name }}</span>
                                <button type="button" class="shrink-0 text-slate-400 hover:text-rose-600" aria-label="Remove attachment" @mousedown.prevent="removeFile(idx)">×</button>
                            </div>
                        </div>

                        <ul
                            v-if="mentionOpen"
                            class="absolute bottom-full left-3 right-3 z-30 mb-1 max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
                        >
                            <li v-if="!filteredMentionables.length" class="px-3 py-2 text-xs font-semibold text-slate-500">
                                No matching team members
                            </li>
                            <li v-for="(user, idx) in filteredMentionables" :key="user.id">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition hover:bg-primary-50"
                                    :class="idx === mentionHighlight ? 'bg-primary-50' : ''"
                                    @mousedown.prevent="insertMention(user)"
                                >
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-teal-700 text-[10px] font-black text-white">
                                        {{ initialsFor(user.name) }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-black text-slate-900">@{{ user.username }}</span>
                                        <span class="block truncate text-xs font-semibold text-slate-500">{{ user.name }} · {{ user.role }}</span>
                                    </span>
                                </button>
                            </li>
                        </ul>

                        <textarea
                            ref="composerEl"
                            v-model="composer"
                            rows="1"
                            class="block w-full min-h-[44px] max-h-40 resize-none overflow-y-auto border-0 bg-transparent px-4 pb-11 pt-3 text-sm font-medium leading-relaxed text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                            placeholder="Message the team… @username to mention. Enter to send, Shift+Enter for new line."
                            @input="onComposerInput"
                            @keydown="onComposerKeydown"
                            @blur="onComposerBlur"
                        />

                        <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 px-2 pb-2">
                            <div class="relative flex items-center gap-1">
                                <label class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition hover:bg-primary-50 hover:text-primary-700 active:scale-95" title="Attach file">
                                    <input type="file" class="sr-only" multiple accept="image/*,.pdf" @change="onFiles" />
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                </label>
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-primary-50 hover:text-primary-700 active:scale-95" title="Emoji" @click="showEmojiPicker = !showEmojiPicker">
                                    <span class="text-lg leading-none">😊</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center justify-center rounded-lg px-2 text-[10px] font-black uppercase tracking-wide transition active:scale-95"
                                    :class="showGifPicker ? 'bg-primary-100 text-primary-800' : 'text-slate-500 hover:bg-primary-50 hover:text-primary-700'"
                                    title="Search GIFs"
                                    @mousedown.prevent="showGifPicker = !showGifPicker; showEmojiPicker = false"
                                >
                                    GIF
                                </button>

                                <div
                                    v-if="showEmojiPicker"
                                    class="absolute bottom-11 left-0 z-20 w-[min(100vw-2rem,20rem)] min-w-[18rem] rounded-2xl border border-slate-200 bg-white p-3 shadow-xl"
                                >
                                    <p class="mb-2 px-1 text-[10px] font-black uppercase tracking-wide text-slate-400">Emoji</p>
                                    <div class="grid grid-cols-6 gap-2">
                                        <button
                                            v-for="em in emojiPalette"
                                            :key="em"
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-2xl transition hover:bg-slate-100 active:scale-95"
                                            @click="insertEmoji(em)"
                                        >
                                            {{ em }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-primary-700 px-4 text-xs font-black uppercase tracking-wide text-white shadow-sm transition hover:bg-primary-800 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="sending || (!composerHasContent && !files.length && !pendingGif)"
                            >
                                Send
                            </button>
                        </div>
                    </div>

                    <label v-if="isSuperAdmin" class="mt-2 flex items-center gap-2 text-xs font-bold text-slate-600">
                        <input v-model="officialGuidance" type="checkbox" class="rounded border-slate-300 text-primary-700 focus:ring-primary-500" />
                        Mark as official guidance
                    </label>
                </form>
            </footer>
        </section>

        <aside class="w-full border-t border-slate-100 bg-slate-50/50 p-3 lg:w-60 lg:border-t-0 lg:border-l" :class="showMembers ? 'block' : 'hidden lg:block'">
            <p class="text-[10px] font-black uppercase text-slate-400">Online team</p>
            <ul class="mt-2 max-h-40 space-y-2 overflow-y-auto lg:max-h-none">
                <li v-for="m in presence" :key="m.id" class="flex items-center gap-2 rounded-lg bg-white px-2 py-2 shadow-sm">
                    <span class="h-2 w-2 rounded-full" :class="m.online ? 'bg-emerald-500' : 'bg-slate-300'" />
                    <span class="truncate text-xs font-bold text-slate-800">{{ m.name }}</span>
                </li>
            </ul>
            <p class="mt-4 text-[10px] font-black uppercase text-slate-400">Pinned</p>
            <ul class="mt-2 space-y-2">
                <li v-for="pin in pinned" :key="pin.id" class="rounded-lg border border-primary-100 bg-primary-50/50 p-2 text-xs font-semibold text-slate-800 line-clamp-3">{{ pin.body }}</li>
            </ul>
        </aside>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import GifPickerPanel from '@/Components/Chat/GifPickerPanel.vue';
import { attachmentUrl, isGifAttachment, isImageAttachment } from '@/utils/chatAttachments';
import { ensureEcho } from '@/utils/ensureEcho';
import { chatDayDividerLabel, dayKeyFromIso, formatChatMessageTime } from '@/utils/chatMessageDates';

const COMPOSER_MAX_HEIGHT = 160;
const POLL_INTERVAL_MS = 800;
const MARK_READ_DEBOUNCE_MS = 500;
const TYPING_TTL_MS = 2500;

const props = defineProps({
    room: { type: Object, required: true },
    routeNamespace: { type: String, default: 'operations' },
    isSuperAdmin: { type: Boolean, default: false },
    chatBootstrap: { type: Object, default: null },
});

const page = usePage();
const myUserId = computed(() => page.props.auth?.user?.id);

const rooms = ref([]);
const messages = ref([]);
const pinned = ref([]);
const presence = ref([]);
const hasMore = ref(false);
const loadingMore = ref(false);
const composer = ref('');
const composerHasContent = ref(false);
const files = ref([]);
const fileUrls = ref([]);
const sending = ref(false);
const officialGuidance = ref(false);
const searchQ = ref('');
const showMembers = ref(false);
const showEmojiPicker = ref(false);
const showGifPicker = ref(false);
const pendingGif = ref(null);
const streamEl = ref(null);
const composerEl = ref(null);
const typingUsers = ref([]);
const brokenAvatarIds = ref({});

const reactionOptions = ['👍', '❤️', '⚠️', '😂', '🎉'];
const emojiPalette = ['😀', '😊', '👍', '❤️', '🙏', '🎉', '🔥', '✅', '⚠️', '👀', '💯', '🚀'];

let channel = null;
let typingTimer = null;
let typingExpiryTimers = {};
let typingSweepTimer = null;
let presenceTimer = null;
let pollTimer = null;
let markReadTimer = null;
let isTypingBroadcast = false;
const channelName = computed(() => `staff-team.${props.room.id}`);
const connectionMode = ref('polling');
const loadError = ref('');
const sendError = ref('');
const mentionables = ref([]);
const mentionOpen = ref(false);
const mentionQuery = ref('');
const mentionHighlight = ref(0);

function r(name, params = {}) {
    return route(`${props.routeNamespace}.${name}`, params);
}

function initialsFor(name) {
    const parts = (name || 'H').trim().split(/\s+/).filter(Boolean);

    return ((parts[0]?.[0] || 'H') + (parts[1]?.[0] || '')).toUpperCase();
}

function markAvatarBroken(messageId) {
    if (brokenAvatarIds.value[messageId]) return;
    brokenAvatarIds.value = { ...brokenAvatarIds.value, [messageId]: true };
}

const typingLabel = computed(() => {
    const names = typingUsers.value.map((e) => e.name).filter(Boolean);
    if (!names.length) return '';
    if (names.length === 1) return `${names[0]} is typing…`;
    if (names.length === 2) return `${names[0]} and ${names[1]} are typing…`;

    return `${names.slice(0, -1).join(', ')}, and ${names[names.length - 1]} are typing…`;
});

const filteredMentionables = computed(() => {
    if (!mentionOpen.value) return [];
    const q = mentionQuery.value.trim().toLowerCase();

    return mentionables.value
        .filter((user) => {
            if (!q) return true;

            return user.username?.toLowerCase().includes(q) || user.name?.toLowerCase().includes(q);
        })
        .slice(0, 12);
});

function reactionList(message) {
    const raw = message?.reactions;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') return Object.values(raw);

    return [];
}

function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatMessageHtml(body) {
    if (!body) return '';

    let safe = escapeHtml(body);
    const sorted = [...mentionables.value].sort((a, b) => (b.username?.length ?? 0) - (a.username?.length ?? 0));

    for (const user of sorted) {
        if (!user.username) continue;
        const pattern = new RegExp(`@${user.username.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?=\\s|$|[.,!?;:])`, 'gi');
        safe = safe.replace(
            pattern,
            `<span class="mention-link" data-user-id="${user.id}">@${escapeHtml(user.username)}</span>`,
        );
    }

    return safe.replace(/\n/g, '<br>');
}

const groupedMessages = computed(() => {
    const out = [];
    let lastSender = null;
    let lastDayKey = '';

    for (const m of messages.value) {
        const dayKey = dayKeyFromIso(m.created_at);
        const showDayDivider = dayKey !== lastDayKey;
        const dayLabel = showDayDivider ? chatDayDividerLabel(m.created_at) : null;
        const mine = m.sender?.id === page.props.auth?.user?.id;
        const showAvatar = lastSender !== m.sender?.id;
        const showMeta = showAvatar;

        const reactions = {};
        reactionList(m).forEach((rx) => {
            if (!rx?.emoji) return;
            reactions[rx.emoji] = { count: rx.count, mine: rx.mine };
        });

        out.push({
            key: `m-${m.id}`,
            id: m.id,
            body: m.body,
            bodyHtml: formatMessageHtml(m.body),
            attachments: m.attachments,
            mine,
            showAvatar,
            showMeta,
            showDayDivider,
            dayLabel,
            senderName: m.sender?.name,
            role: m.sender?.role || 'Staff',
            official: m.is_official_guidance,
            avatar: m.sender?.avatar_url || null,
            initials: initialsFor(m.sender?.name),
            time: formatChatMessageTime(m.created_at),
            read_count: m.read_count ?? 0,
            reactions,
        });

        lastSender = m.sender?.id;
        lastDayKey = dayKey;
    }

    return out;
});

onMounted(async () => {
    loadError.value = '';

    if (props.chatBootstrap) {
        applyBootstrapData(props.chatBootstrap);
        await scrollBottom();
        scheduleMarkRead();
    }

    try {
        await bootstrap();
    } catch (err) {
        if (!messages.value.length) {
            loadError.value =
                err?.response?.data?.message ||
                'Could not load team chat. Refresh the page or sign in again.';
        }
    }

    try {
        bindEcho();
    } catch {
        connectionMode.value = 'polling';
    }

    resizeComposer();
    void pollNewMessages();
    presenceTimer = setInterval(refreshPresence, 45000);
    restartPollTimer();
    typingSweepTimer = setInterval(sweepStaleTypingIndicators, 1000);
    window.addEventListener('focus', onWindowFocus);
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    fileUrls.value.forEach((url) => URL.revokeObjectURL(url));
    stopTypingBroadcast();
    if (channel && window.Echo) {
        window.Echo.leave(channelName.value);
    }
    clearInterval(presenceTimer);
    clearInterval(pollTimer);
    clearInterval(typingSweepTimer);
    clearTimeout(typingTimer);
    Object.values(typingExpiryTimers).forEach((t) => clearTimeout(t));
    window.removeEventListener('focus', onWindowFocus);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    clearTimeout(markReadTimer);
});


function hasReactions(group) {
    return Object.keys(group.reactions || {}).length > 0;
}

function filePreview(file) {
    if (!file?.type?.startsWith('image/')) return null;
    const idx = files.value.indexOf(file);
    return fileUrls.value[idx] ?? null;
}

function selectRoom(r) {
    if (r.id === props.room.id) return;
    window.location.href = r.id === props.room.id ? '#' : window.location.pathname;
}

function applyBootstrapData(data) {
    rooms.value = data.rooms ?? [{ ...props.room }];
    messages.value = data.messages ?? [];
    pinned.value = data.pinned ?? [];
    presence.value = data.presence ?? [];
    mentionables.value = data.mentionables ?? [];
    hasMore.value = data.has_more ?? false;
}

async function bootstrap() {
    const { data } = await window.axios.get(r('api.team-chat.bootstrap'));
    applyBootstrapData(data);
    await scrollBottom();
    scheduleMarkRead();
}

function bindEcho() {
    const echo = ensureEcho(page.props.reverb);
    if (!echo) {
        connectionMode.value = 'polling';
        return;
    }

    if (channel) {
        echo.leave(channelName.value);
        channel = null;
    }

    channel = echo.private(channelName.value);

    channel.listen('.message.sent', (payload) => {
        const msg = payload?.message;
        if (!msg) return;
        if (msg.sender?.id === myUserId.value) {
            reconcileOwnMessage(msg);
            return;
        }
        mergeIncomingMessage(msg);
        void markRead();
    });

    channel.listen('.typing', (payload) => {
        if (payload.user_id === myUserId.value) return;
        setTypingIndicator(payload.user_id, payload.user_name, payload.typing);
    });

    channel.subscribed(() => {
        connectionMode.value = 'live';
        restartPollTimer();
    });

    const pusher = echo.connector?.pusher;
    if (pusher) {
        pusher.connection.bind('connected', () => {
            connectionMode.value = 'live';
        });
        pusher.connection.bind('disconnected', () => {
            connectionMode.value = 'polling';
        });
        pusher.connection.bind('unavailable', () => {
            connectionMode.value = 'polling';
        });
        if (pusher.connection.state === 'connected') {
            connectionMode.value = 'live';
        }
    }
}

async function loadOlder() {
    if (!messages.value.length) return;
    loadingMore.value = true;
    const beforeId = messages.value[0].id;
    try {
        const { data } = await window.axios.get(r('api.team-chat.messages', { room: props.room.id }), { params: { before_id: beforeId } });
        messages.value = [...(data.items ?? []), ...messages.value];
        hasMore.value = data.has_more ?? false;
    } finally {
        loadingMore.value = false;
    }
}

function restartPollTimer() {
    clearInterval(pollTimer);
    pollTimer = setInterval(() => {
        void pollNewMessages();
    }, POLL_INTERVAL_MS);
}

function scheduleMarkRead() {
    clearTimeout(markReadTimer);
    markReadTimer = setTimeout(() => {
        void markRead();
    }, MARK_READ_DEBOUNCE_MS);
}

function textBeforeCaret(el) {
    if (!el) return '';
    const pos = el.selectionStart ?? composer.value.length;

    return composer.value.slice(0, pos);
}

function updateMentionPicker() {
    const el = composerEl.value;
    if (!el || !mentionables.value.length) {
        mentionOpen.value = false;
        return;
    }

    const before = textBeforeCaret(el);
    const match = before.match(/@([a-zA-Z0-9_.-]*)$/);

    if (!match) {
        mentionOpen.value = false;
        mentionQuery.value = '';
        mentionHighlight.value = 0;
        return;
    }

    mentionOpen.value = true;
    mentionQuery.value = match[1] || '';
    mentionHighlight.value = 0;
}

function onWindowFocus() {
    void pollNewMessages();
}

function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
        void pollNewMessages();
    }
}

function sweepStaleTypingIndicators() {
    const now = Date.now();
    typingUsers.value = typingUsers.value.filter((entry) => now - entry.at < TYPING_TTL_MS);
}

function onComposerKeydown(e) {
    if (mentionOpen.value && filteredMentionables.value.length) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            mentionHighlight.value = (mentionHighlight.value + 1) % filteredMentionables.value.length;
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            mentionHighlight.value =
                (mentionHighlight.value - 1 + filteredMentionables.value.length) % filteredMentionables.value.length;
            return;
        }
        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            const pick = filteredMentionables.value[mentionHighlight.value];
            if (pick) insertMention(pick);
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            mentionOpen.value = false;
            return;
        }
    }

    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

function onComposerInput() {
    composerHasContent.value = !!composer.value.trim() || files.value.length > 0;
    resizeComposer();
    updateMentionPicker();
    onTyping();
}

function onComposerBlur() {
    stopTypingBroadcast();
}

function clearComposer() {
    composer.value = '';
}

function insertMention(user) {
    const el = composerEl.value;
    if (!el || !user?.username) return;

    const pos = el.selectionStart ?? composer.value.length;
    const before = composer.value.slice(0, pos);
    const after = composer.value.slice(pos);
    const match = before.match(/@([a-zA-Z0-9_.-]*)$/);

    if (!match) return;

    const insert = `@${user.username} `;
    const nextBefore = before.slice(0, -match[0].length) + insert;
    composer.value = nextBefore + after;
    const caret = nextBefore.length;

    mentionOpen.value = false;
    mentionQuery.value = '';
    composerHasContent.value = true;

    nextTick(() => {
        el.focus();
        el.setSelectionRange(caret, caret);
        resizeComposer();
    });
}

function resizeComposer() {
    const el = composerEl.value;
    if (!el) return;
    el.style.height = 'auto';
    const nextHeight = Math.min(el.scrollHeight, COMPOSER_MAX_HEIGHT);
    el.style.height = `${nextHeight}px`;
    el.style.overflowY = el.scrollHeight > COMPOSER_MAX_HEIGHT ? 'auto' : 'hidden';
}

function reconcileOwnMessage(msg) {
    const byId = messages.value.findIndex((m) => m.id === msg.id);
    if (byId >= 0) {
        messages.value[byId] = msg;
        return;
    }
    const tmpIdx = messages.value.findIndex(
        (m) => String(m.id).startsWith('tmp-') && m.body === msg.body && m.sender?.id === myUserId.value,
    );
    if (tmpIdx >= 0) {
        messages.value[tmpIdx] = msg;
        return;
    }
    if (!messages.value.some((m) => m.id === msg.id)) {
        messages.value.push(msg);
        scrollBottom();
    }
}

function upsertChatMessage(msg) {
    const idx = messages.value.findIndex((m) => m.id === msg.id);
    if (idx >= 0) {
        messages.value[idx] = msg;
        return;
    }
    if (msg.sender?.id === myUserId.value) {
        reconcileOwnMessage(msg);
        return;
    }
    messages.value.push(msg);
    scrollBottom();
    notifyInboxChanged();
}

function mergeIncomingMessage(msg) {
    upsertChatMessage(msg);
}

function setTypingIndicator(userId, name, active) {
    const key = String(userId ?? name ?? '');
    if (!key) return;

    if (active) {
        clearTimeout(typingExpiryTimers[key]);
        typingUsers.value = [
            ...typingUsers.value.filter((e) => e.key !== key),
            { key, name: name || 'Someone', at: Date.now() },
        ];
        typingExpiryTimers[key] = setTimeout(() => {
            typingUsers.value = typingUsers.value.filter((e) => e.key !== key);
            delete typingExpiryTimers[key];
        }, TYPING_TTL_MS);
    } else {
        clearTimeout(typingExpiryTimers[key]);
        delete typingExpiryTimers[key];
        typingUsers.value = typingUsers.value.filter((e) => e.key !== key);
    }
}

function stopTypingBroadcast() {
    clearTimeout(typingTimer);
    if (isTypingBroadcast) {
        isTypingBroadcast = false;
        window.axios.post(r('api.team-chat.typing', { room: props.room.id }), { typing: false }).catch(() => {});
    }
}

async function pollNewMessages() {
    const lastReal = [...messages.value].reverse().find((m) => !String(m.id).startsWith('tmp-'));
    const afterId = lastReal?.id ?? 0;
    try {
        const { data } = await window.axios.get(r('api.team-chat.messages', { room: props.room.id }), {
            params: { after_id: afterId },
        });
        let added = false;
        for (const msg of data.items ?? []) {
            const exists = messages.value.some((m) => m.id === msg.id);
            if (exists) continue;
            if (msg.sender?.id === myUserId.value) {
                reconcileOwnMessage(msg);
            } else {
                mergeIncomingMessage(msg);
            }
            added = true;
        }
        if (added) {
            scrollBottom();
            scheduleMarkRead();
            notifyInboxChanged();
        }
    } catch {
        // ignore transient poll errors
    }
}

function notifyInboxChanged() {
    window.dispatchEvent(new CustomEvent('operations:notifications-changed'));
    window.dispatchEvent(new CustomEvent('admin:notifications-changed'));
}

function onGifSelected(gif) {
    if (!gif?.url) return;
    pendingGif.value = gif;
    showGifPicker.value = false;
}

async function send() {
    const bodyText = composer.value.trim();
    if (!bodyText && !files.value.length && !pendingGif.value) return;
    stopTypingBroadcast();
    sending.value = true;
    sendError.value = '';
    showEmojiPicker.value = false;
    showGifPicker.value = false;

    const gifUrl = pendingGif.value?.url;
    const optimistic = {
        id: `tmp-${Date.now()}`,
        body: bodyText,
        attachments: gifUrl
            ? [{ type: 'gif', url: gifUrl, remote: true, mime: 'image/gif', name: 'GIF' }]
            : files.value.map((f, i) => ({
                  name: f.name,
                  url: fileUrls.value[i] || '',
                  mime: f.type,
              })),
        sender: { id: page.props.auth?.user?.id, name: page.props.auth?.user?.name, role: props.isSuperAdmin ? 'Super Admin' : 'Staff' },
        created_at: new Date().toISOString(),
        reactions: [],
        read_count: 0,
    };
    messages.value.push(optimistic);

    const fd = new FormData();
    fd.append('body', bodyText);
    if (officialGuidance.value) fd.append('is_official_guidance', '1');
    files.value.forEach((f) => fd.append('attachments[]', f));
    if (pendingGif.value?.url) {
        fd.append('gif_url', pendingGif.value.url);
    }

    const bodySnapshot = bodyText;
    const gifSnapshot = pendingGif.value;
    clearComposer();
    pendingGif.value = null;
    composerHasContent.value = false;
    clearFiles();
    officialGuidance.value = false;
    await nextTick();
    resizeComposer();
    await scrollBottom();

    try {
        const { data } = await window.axios.post(r('api.team-chat.send', { room: props.room.id }), fd);
        const idx = messages.value.findIndex((m) => m.id === optimistic.id);
        if (idx >= 0 && data.message) {
            messages.value[idx] = data.message;
        } else if (data.message) {
            reconcileOwnMessage(data.message);
        }
        notifyInboxChanged();
    } catch (err) {
        const msg = err?.response?.data?.message || err?.response?.data?.errors?.body?.[0];
        sendError.value = msg || 'Message could not be sent. Check you are signed in and try again.';
        await recoverAfterSendFailure(optimistic.id, bodySnapshot, gifSnapshot);
    } finally {
        sending.value = false;
    }
}

async function recoverAfterSendFailure(optimisticId, bodySnapshot, gifSnapshot = null) {
    messages.value = messages.value.filter((m) => m.id !== optimisticId);
    pendingGif.value = gifSnapshot;
    try {
        const { data } = await window.axios.get(r('api.team-chat.bootstrap'));
        const latest = data.messages ?? [];
        const saved = latest.find((m) => m.body === bodySnapshot && m.sender?.id === page.props.auth?.user?.id);
        if (saved) {
            mergeIncomingMessage(saved);
            return;
        }
        messages.value = latest;
        hasMore.value = data.has_more ?? false;
    } catch {
        composer.value = bodySnapshot;
        composerHasContent.value = !!bodySnapshot.trim();
        resizeComposer();
    }
}

function onFiles(e) {
    const picked = Array.from(e.target.files || []);
    picked.forEach((file) => {
        files.value.push(file);
        if (file.type?.startsWith('image/')) {
            fileUrls.value.push(URL.createObjectURL(file));
        } else {
            fileUrls.value.push(null);
        }
    });
    e.target.value = '';
}

function removeFile(idx) {
    if (fileUrls.value[idx]) URL.revokeObjectURL(fileUrls.value[idx]);
    files.value.splice(idx, 1);
    fileUrls.value.splice(idx, 1);
}

function clearFiles() {
    fileUrls.value.forEach((url) => url && URL.revokeObjectURL(url));
    files.value = [];
    fileUrls.value = [];
}

function insertEmoji(em) {
    const el = composerEl.value;
    if (el) {
        const pos = el.selectionStart ?? composer.value.length;
        composer.value = composer.value.slice(0, pos) + em + composer.value.slice(pos);
        composerHasContent.value = true;
    }
    showEmojiPicker.value = false;
    showGifPicker.value = false;
    nextTick(() => {
        resizeComposer();
        composerEl.value?.focus();
    });
}

function onTyping() {
    clearTimeout(typingTimer);
    if (!isTypingBroadcast) {
        isTypingBroadcast = true;
        window.axios.post(r('api.team-chat.typing', { room: props.room.id }), { typing: true }).catch(() => {});
    }
    typingTimer = setTimeout(() => {
        stopTypingBroadcast();
    }, 1000);
}

function reactionRows(message) {
    const raw = message?.reactions;
    if (Array.isArray(raw)) {
        return raw.map((rx) => ({ ...rx }));
    }
    if (raw && typeof raw === 'object') {
        return Object.values(raw).map((rx) => ({ ...rx }));
    }

    return [];
}

function applyReactionOptimistic(messageId, emoji) {
    const idx = messages.value.findIndex((m) => m.id === messageId);
    if (idx < 0) return;

    const message = messages.value[idx];
    const reactions = reactionRows(message);
    const existing = reactions.find((rx) => rx.emoji === emoji);

    if (existing?.mine) {
        existing.count = Math.max(0, (existing.count ?? 1) - 1);
        existing.mine = false;
    } else if (existing) {
        existing.count = (existing.count ?? 0) + 1;
        existing.mine = true;
    } else {
        reactions.push({ emoji, count: 1, mine: true });
    }

    messages.value[idx] = {
        ...message,
        reactions: reactions.filter((rx) => (rx.count ?? 0) > 0),
    };
}

async function toggleReaction(messageId, emoji) {
    if (String(messageId).startsWith('tmp-')) return;

    const snapshot = messages.value.find((m) => m.id === messageId);
    applyReactionOptimistic(messageId, emoji);

    try {
        const { data } = await window.axios.post(r('api.team-chat.react', { message: messageId }), { emoji });
        if (data?.message) {
            const idx = messages.value.findIndex((m) => m.id === messageId);
            if (idx >= 0) messages.value[idx] = data.message;
        }
    } catch {
        if (snapshot) {
            const idx = messages.value.findIndex((m) => m.id === messageId);
            if (idx >= 0) messages.value[idx] = snapshot;
        }
    }
}

async function markRead() {
    const last = [...messages.value].reverse().find((m) => m.sender?.id !== myUserId.value && !String(m.id).startsWith('tmp-'));
    const payload = last ? { up_to_message_id: last.id } : {};
    try {
        await window.axios.post(r('api.team-chat.read', { room: props.room.id }), payload);
        notifyInboxChanged();
    } catch {
        // ignore
    }
}

async function refreshPresence() {
    const { data } = await window.axios.get(r('api.team-chat.presence'));
    presence.value = data.presence ?? [];
}

async function runSearch() {
    if (searchQ.value.length < 2) return;
    const { data } = await window.axios.get(r('api.team-chat.search', { room: props.room.id }), { params: { q: searchQ.value } });
    if (data.results?.[0]) {
        const hit = messages.value.find((m) => m.id === data.results[0].id);
        if (!hit) {
            messages.value.unshift({
                id: data.results[0].id,
                body: data.results[0].body,
                sender: { name: data.results[0].sender },
                created_at: data.results[0].created_at,
                reactions: [],
            });
        }
    }
}

function onScroll() {
    if (streamEl.value?.scrollTop < 80 && hasMore.value) loadOlder();
}

async function scrollBottom() {
    await nextTick();
    if (streamEl.value) streamEl.value.scrollTop = streamEl.value.scrollHeight;
}

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString('en-NG', { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' });
    } catch {
        return '';
    }
}
</script>
