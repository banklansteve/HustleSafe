<template>
    <AppShell :title="ticket.subject" subtitle="Customer support">
        <div class="mx-auto flex max-w-2xl flex-col rounded-[1.75rem] border border-slate-200 bg-white shadow-sm" style="min-height: calc(100vh - 10rem)">
            <header class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                <div class="min-w-0">
                    <Link :href="route('support.chat.index')" class="text-xs font-bold text-primary-700 hover:underline">← All chats</Link>
                    <p class="truncate text-sm font-black text-slate-900">{{ ticket.subject }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ ticket.category_label }} · {{ ticket.chat_status }}</p>
                </div>
                <span
                    class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black uppercase"
                    :class="ticket.chat_status === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'"
                >{{ ticket.chat_status }}</span>
            </header>

            <div ref="scrollEl" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                <div
                    v-for="m in localMessages"
                    :key="m.id"
                    class="flex"
                    :class="m.mine ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[85%] rounded-2xl px-3 py-2 text-sm"
                        :class="m.mine ? 'bg-primary-700 text-white' : 'bg-slate-100 text-slate-900'"
                    >
                        <p v-if="m.body" class="whitespace-pre-wrap break-words">{{ m.body }}</p>
                        <div v-if="m.attachments?.length" class="mt-2 grid gap-2">
                            <template v-for="(att, i) in m.attachments" :key="i">
                                <img v-if="isImage(att)" :src="attUrl(att)" class="max-h-48 rounded-lg" loading="lazy" />
                                <a v-else :href="attUrl(att)" target="_blank" rel="noopener" class="text-xs font-bold underline">{{ att.name || 'File' }}</a>
                            </template>
                        </div>
                        <p class="mt-1 text-[10px] font-semibold opacity-70">{{ formatTime(m.created_at) }}</p>
                    </div>
                </div>
                <p v-if="typingLabel" class="text-xs font-semibold text-primary-700">{{ typingLabel }}</p>
            </div>

            <footer v-if="ticket.chat_status !== 'closed'" class="relative border-t border-slate-100 p-3">
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
                        class="absolute bottom-full left-3 right-3 z-40 mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    >
                        <GifPickerPanel
                            :open="gifOpen"
                            :search-url="route('api.support.gifs')"
                            @select="onGifSelected"
                            @close="gifOpen = false"
                        />
                    </div>
                </Transition>

                <form class="rounded-xl border border-slate-200" @submit.prevent="send">
                    <div v-if="pendingGif || pendingFiles.length" class="flex flex-wrap gap-2 border-b border-slate-100 px-3 py-2">
                        <span v-if="pendingGif" class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-2 py-1 text-xs">
                            <img :src="pendingGif.preview || pendingGif.url" alt="" class="h-8 w-8 rounded object-cover" />
                            GIF
                            <button type="button" @mousedown.prevent="pendingGif = null">×</button>
                        </span>
                        <span v-for="(f, i) in pendingFiles" :key="i" class="rounded-lg bg-slate-100 px-2 py-1 text-xs">{{ f.name }} <button type="button" @mousedown.prevent="pendingFiles.splice(i, 1)">×</button></span>
                    </div>
                    <textarea
                        v-model="composer"
                        rows="2"
                        class="block w-full resize-none border-0 bg-transparent px-3 py-2 text-sm focus:outline-none"
                        placeholder="Type a message…"
                        @input="onComposerInput"
                        @blur="stopTyping"
                    />
                    <div class="flex items-center justify-between border-t border-slate-100 px-2 py-2">
                        <div class="flex gap-1">
                            <label class="cursor-pointer rounded-lg px-2 py-1.5 text-xs font-bold text-slate-600">
                                Attach<input type="file" class="sr-only" multiple accept="image/*,.pdf" @change="onFiles" />
                            </label>
                            <button type="button" class="rounded-lg px-2 py-1.5 text-xs font-bold text-slate-600" @mousedown.prevent="gifOpen = !gifOpen">GIF</button>
                        </div>
                        <button type="submit" class="rounded-lg bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white disabled:opacity-50" :disabled="sending || (!composer.trim() && !pendingFiles.length && !pendingGif)">
                            Send
                        </button>
                    </div>
                </form>
            </footer>
            <p v-else class="border-t border-slate-100 px-4 py-6 text-center text-sm font-semibold text-slate-500">This conversation is closed.</p>
        </div>
    </AppShell>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';
import GifPickerPanel from '@/Components/Chat/GifPickerPanel.vue';
import { useSupportChatRealtime } from '@/composables/useSupportChatRealtime';
import { ensureEcho } from '@/utils/ensureEcho';
import { broadcastConfigFromPage } from '@/utils/broadcastConfig';

const props = defineProps({
    ticket: { type: Object, required: true },
    messages: { type: Array, default: () => [] },
    hasMore: { type: Boolean, default: false },
});

const page = usePage();
const localMessages = ref([...props.messages]);
const composer = ref('');
const sending = ref(false);
const gifOpen = ref(false);
const pendingGif = ref(null);
const pendingFiles = ref([]);
const scrollEl = ref(null);
const typingAdmin = ref(null);
let typingTimer = null;

const ticketApiRef = () => props.ticket.uuid || props.ticket.id;
const ticketChannelId = () => props.ticket.id;

function liveBroadcastConfig() {
    return broadcastConfigFromPage(page);
}

const chatRealtime = useSupportChatRealtime({
    reverbConfig: liveBroadcastConfig,
    pollVisibleMs: liveBroadcastConfig()?.pollVisibleMs ?? 500,
    pollHiddenMs: liveBroadcastConfig()?.pollHiddenMs ?? 2000,
    pollMessages: async (afterId) => {
        if (!afterId) {
            return [];
        }

        const { data } = await window.axios.get(route('api.support.chat.messages', { ticket: ticketApiRef() }), {
            params: { after_id: afterId },
        });

        return data.items ?? [];
    },
    onMessage: (msg) => {
        if (localMessages.value.some((m) => m.id === msg.id)) {
            return false;
        }
        localMessages.value.push(msg);
        scrollBottom();

        return true;
    },
    onTyping: (e) => {
        if (e.side === 'admin') {
            typingAdmin.value = e.typing ? e.name : null;
        }
    },
});

const typingLabel = computed(() => (typingAdmin.value ? `${typingAdmin.value} is typing…` : ''));

onMounted(() => {
    ensureEcho(liveBroadcastConfig());
    chatRealtime.subscribe(ticketChannelId(), localMessages.value);
    scrollBottom();
});

onBeforeUnmount(() => {
    chatRealtime.teardown();
    clearTimeout(typingTimer);
});

function route(name, params = {}) {
    return window.route(name, params);
}

function scrollBottom() {
    nextTick(() => {
        if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
    });
}

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function isImage(att) {
    return att?.type === 'gif' || att?.type === 'image' || String(att?.mime || '').startsWith('image/');
}

function attUrl(att) {
    return att?.url || '';
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
    clearTimeout(typingTimer);
    window.axios.post(route('api.support.chat.typing', { ticket: ticketApiRef() }), { typing: true }).catch(() => {});
    typingTimer = setTimeout(stopTyping, 2000);
}

function stopTyping() {
    window.axios.post(route('api.support.chat.typing', { ticket: ticketApiRef() }), { typing: false }).catch(() => {});
}

async function send() {
    if (sending.value) return;
    const body = composer.value.trim();
    if (!body && !pendingFiles.value.length && !pendingGif.value) return;
    sending.value = true;
    stopTyping();
    const fd = new FormData();
    if (body) fd.append('body', body);
    pendingFiles.value.forEach((f) => fd.append('attachments[]', f));
    if (pendingGif.value?.url) fd.append('gif_url', pendingGif.value.url);
    try {
        const { data } = await window.axios.post(route('api.support.chat.send', { ticket: ticketApiRef() }), fd);
        const msg = data.message;
        if (msg && !localMessages.value.some((m) => m.id === msg.id)) {
            localMessages.value.push(msg);
            chatRealtime.setLastMessageId(msg.id);
        }
        composer.value = '';
        pendingFiles.value = [];
        pendingGif.value = null;
        scrollBottom();
    } finally {
        sending.value = false;
    }
}
</script>
