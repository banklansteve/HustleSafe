<template>
    <div class="space-y-3">
        <template v-for="group in messageDayGroups" :key="group.dayKey">
            <div v-if="group.label" class="flex justify-center py-1">
                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-slate-500">
                    {{ group.label }}
                </span>
            </div>

            <div
                v-for="m in group.messages"
                :key="m.id"
                class="group/msg flex w-full gap-2"
                :class="rowClass(m)"
            >
                <img
                    v-if="showAvatar(m) && avatarUrl(m)"
                    :src="avatarUrl(m)"
                    alt=""
                    class="mt-1 h-8 w-8 shrink-0 rounded-full object-cover ring-2 ring-white"
                />
                <span
                    v-else-if="showAvatar(m)"
                    class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-teal-600 text-[10px] font-black text-white ring-2 ring-white"
                >
                    {{ avatarInitials(m) }}
                </span>

                <div class="flex min-w-0 max-w-[88%] flex-col gap-0.5" :class="colClass(m)">
                    <p
                        v-if="senderName(m) && !isSystemOrClosed(m)"
                        class="px-1 text-[10px] font-semibold text-slate-500"
                        :class="isMine(m) ? 'text-right' : ''"
                    >
                        {{ senderName(m) }}
                    </p>

                    <div
                        class="relative"
                        @mouseenter="hoverId = m.id"
                        @mouseleave="hoverId = null"
                    >
                        <div
                            class="rounded-2xl px-3 py-2 shadow-sm"
                            :class="bubbleClass(m)"
                        >
                            <div v-if="m.kind === 'session_closed' || m.is_system" class="text-center">
                                <p class="text-[10px] font-black uppercase tracking-wide text-primary-800">Session ended</p>
                                <p
                                    v-if="sessionClosedDisplayText(m)"
                                    class="mt-2 whitespace-pre-wrap break-words text-sm font-semibold text-slate-700"
                                >{{ sessionClosedDisplayText(m) }}</p>
                                <slot v-if="perspective === 'customer'" name="session-closed" :message="m" />
                            </div>
                            <template v-else>
                                <p v-if="m.visibility === 'internal'" class="mb-1 text-[9px] font-black uppercase text-amber-700">Internal note</p>
                                <p
                                    v-if="m.body"
                                    class="whitespace-pre-wrap break-words"
                                    :class="emojiOnlyClass(m.body)"
                                >{{ m.body }}</p>
                                <div v-if="m.attachments?.length" class="mt-2 grid gap-2">
                                    <template v-for="(att, i) in m.attachments" :key="i">
                                        <img
                                            v-if="isImage(att)"
                                            :src="attUrl(att)"
                                            class="max-h-48 rounded-lg object-cover"
                                            loading="lazy"
                                            @load="$emit('attachment-loaded')"
                                        />
                                        <a v-else :href="attUrl(att)" target="_blank" rel="noopener" class="text-xs font-bold underline">{{ att.name || 'File' }}</a>
                                    </template>
                                </div>
                            </template>

                            <div class="mt-1 flex items-center gap-1.5" :class="isMine(m) ? 'justify-end' : ''">
                                <span class="text-[10px] font-semibold opacity-60">{{ formatChatMessageTime(m.created_at) }}</span>
                                <SupportMessageReceipt v-if="isMine(m) && m.receipt_status" :status="m.receipt_status" />
                            </div>
                        </div>

                        <div
                            v-if="canReact && hoverId === m.id && !isSystemOrClosed(m)"
                            class="absolute -top-9 z-10 flex gap-0.5 rounded-full border border-slate-200 bg-white px-1 py-0.5 shadow-lg"
                            :class="isMine(m) ? 'right-0' : 'left-0'"
                        >
                            <button
                                v-for="em in reactionEmojis"
                                :key="em"
                                type="button"
                                class="rounded-full px-1.5 py-0.5 text-base transition hover:scale-110 hover:bg-primary-50"
                                @mousedown.prevent="$emit('react', { message: m, emoji: em })"
                            >{{ em }}</button>
                        </div>
                    </div>

                    <div
                        v-if="(m.reaction_summary || []).length"
                        class="flex flex-wrap gap-1 px-1"
                        :class="isMine(m) ? 'justify-end' : ''"
                    >
                        <button
                            v-for="r in m.reaction_summary"
                            :key="r.emoji"
                            type="button"
                            class="inline-flex items-center gap-0.5 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs shadow-sm transition hover:border-primary-200"
                            :class="userReacted(m, r.emoji) ? 'border-primary-300 bg-primary-50' : ''"
                            @mousedown.prevent="$emit('react', { message: m, emoji: r.emoji })"
                        >
                            <span>{{ r.emoji }}</span>
                            <span v-if="r.count > 1" class="font-bold text-slate-600">{{ r.count }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { formatChatMessageTime, groupMessagesByChatDay } from '@/utils/chatMessageDates';
import SupportMessageReceipt from '@/Components/Support/SupportMessageReceipt.vue';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    perspective: { type: String, default: 'customer' },
    reactionEmojis: { type: Array, default: () => ['👍', '❤️', '😂', '😮', '🙏', '🎉'] },
    canReact: { type: Boolean, default: true },
    showAvatars: { type: Boolean, default: true },
});

defineEmits(['react', 'attachment-loaded']);

const hoverId = ref(null);

const messageDayGroups = computed(() => groupMessagesByChatDay(props.messages));

function sessionClosedDisplayText(m) {
    if (props.perspective === 'staff') {
        return (
            m.admin_body
            || 'You ended this live support session. The customer has been prompted for feedback.'
        );
    }

    return (
        m.body
        || 'This support session has ended. You can start a new chat anytime if you need more help.'
    );
}

function isMine(m) {
    if (props.perspective === 'staff') {
        return m.sender_type === 'admin' || (!!m.mine && m.sender_type !== 'customer');
    }

    return m.sender_type === 'customer' || !!m.mine;
}

function isSystemOrClosed(m) {
    return m.is_system || m.kind === 'session_closed';
}

function rowClass(m) {
    if (isSystemOrClosed(m)) {
        return 'justify-center';
    }

    return isMine(m) ? 'justify-end flex-row-reverse' : 'justify-start';
}

function colClass(m) {
    if (isSystemOrClosed(m)) {
        return 'max-w-[92%] items-center';
    }

    return isMine(m) ? 'items-end' : 'items-start';
}

function bubbleClass(m) {
    if (m.kind === 'session_closed' || m.is_system) {
        return 'border border-primary-100 bg-gradient-to-b from-primary-50/90 to-white text-slate-900 ring-1 ring-primary-100/80 text-sm';
    }
    if (m.visibility === 'internal') {
        return 'border border-amber-200 bg-amber-50 text-amber-950 text-sm';
    }
    if (isMine(m)) {
        return 'bg-primary-700 text-white text-sm';
    }

    return 'border border-slate-100 bg-white text-slate-900 text-sm';
}

function showAvatar(m) {
    return props.showAvatars && !isSystemOrClosed(m) && !isMine(m);
}

function avatarUrl(m) {
    return m.sender?.avatar_url || null;
}

function avatarInitials(m) {
    const name = senderName(m) || '?';

    return name
        .split(/\s+/)
        .map((p) => p[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function senderName(m) {
    if (m.sender_label) {
        return m.sender_label;
    }
    if (m.sender?.first_name) {
        return m.sender.first_name;
    }
    const name = (m.sender?.name || '').trim();
    if (!name) {
        return props.perspective === 'customer' && m.sender_type === 'admin' ? 'Customer Support' : null;
    }

    return name.split(/\s+/)[0] || name;
}

function emojiOnlyClass(body) {
    const t = String(body || '').trim();
    if (!t) {
        return '';
    }
    const stripped = t.replace(/[\p{Extended_Pictographic}\u200d\uFE0F]/gu, '').trim();
    if (stripped === '' && t.length <= 8) {
        return 'text-3xl leading-none';
    }

    return 'text-sm';
}

function isImage(att) {
    return att?.type === 'gif' || att?.type === 'image' || String(att?.mime || '').startsWith('image/');
}

function attUrl(att) {
    return att?.url || '';
}

function userReacted(m, emoji) {
    const row = (m.reaction_summary || []).find((r) => r.emoji === emoji);

    return !!row?.reacted;
}
</script>
