<template>
    <component :is="shellComponent" title="Live support" subtitle="Queue, assigned chats, and full customer context — stay on one page.">
        <div class="flex h-[calc(100dvh-10rem)] max-h-[calc(100dvh-10rem)] flex-col overflow-hidden">
        <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 rounded-2xl border border-primary-100 bg-primary-50/60 px-4 py-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-700">Where to work</p>
                <p class="text-sm font-semibold text-slate-700">
                    <strong class="text-slate-900">Sidebar → Live support</strong> or
                    <a :href="pageRoute('customer-support.index')" class="font-bold text-primary-800 underline">{{ supportPath }}</a>
                </p>
            </div>
            <p v-if="assignmentToast" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black uppercase text-white">{{ assignmentToast }}</p>
        </div>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm lg:flex-row">
            <aside
                class="flex w-full shrink-0 flex-col border-b border-slate-200 bg-slate-50/80 lg:w-[22rem] lg:border-b-0 lg:border-r"
                :class="mobileView === 'chat' ? 'hidden lg:flex' : 'flex'"
            >
                <div class="border-b border-slate-100 p-3 space-y-2">
                    <div class="flex gap-1 rounded-xl bg-slate-100/90 p-1">
                        <button
                            type="button"
                            class="flex-1 rounded-lg px-2 py-2 text-[10px] font-black uppercase tracking-wide transition"
                            :class="sidebarTab === 'live' ? 'bg-white text-primary-800 shadow-sm' : 'text-slate-600'"
                            @click="sidebarTab = 'live'"
                        >
                            Live
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg px-2 py-2 text-[10px] font-black uppercase tracking-wide transition"
                            :class="sidebarTab === 'history' ? 'bg-white text-primary-800 shadow-sm' : 'text-slate-600'"
                            @click="openHistoryTab"
                        >
                            History
                        </button>
                    </div>

                    <template v-if="sidebarTab === 'live'">
                    <input
                        v-model="searchQ"
                        type="search"
                        placeholder="Search chats…"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                        @input="debouncedQueueRefresh"
                    />
                    <div v-if="!isSuperAdmin" class="flex gap-1 overflow-x-auto pb-0.5">
                        <button
                            v-for="sec in sections"
                            :key="sec.key"
                            type="button"
                            class="shrink-0 rounded-lg px-2.5 py-1.5 text-[10px] font-black uppercase tracking-wide transition"
                            :class="activeSection === sec.key ? 'bg-primary-700 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'"
                            @click="switchSection(sec.key)"
                        >
                            {{ sec.label }}
                            <span v-if="sec.count" class="ml-1 opacity-80">({{ sec.count }})</span>
                        </button>
                    </div>
                    <template v-else>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-primary-800">All live chats</p>
                        <UiSelect v-model="queueSort" :options="sortOptions" placeholder="Sort by" />
                        <UiSelect v-model="queueFilterStatus" :options="statusFilterOptions" placeholder="Status" />
                        <UiSelect v-model="queueFilterCategory" :options="categoryFilterOptions" placeholder="Category" />
                        <UiSelect v-model="queueFilterAssign" :options="assignFilterOptions" placeholder="Assignment" />
                        <UiSelect
                            v-model="filterAdminId"
                            :options="filterAdminOptions"
                            placeholder="Handled by"
                            @update:model-value="debouncedQueueRefresh"
                        />
                    </template>
                    </template>

                    <template v-else>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Past sessions</p>
                        <UiSelect
                            v-if="isSuperAdmin"
                            v-model="historyFilterAdminId"
                            :options="filterAdminOptions"
                            placeholder="Handled by"
                            @update:model-value="loadHistory"
                        />
                        <input
                            v-model="historySearchQ"
                            type="search"
                            placeholder="Search history…"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                            @input="debouncedHistoryLoad"
                        />
                    </template>
                </div>

                <div v-if="sidebarTab === 'live'" class="min-h-0 flex-1 overflow-y-auto">
                    <button
                        v-for="item in displayQueue"
                        :key="item.id"
                        type="button"
                        class="flex w-full gap-3 border-b border-slate-100 px-3 py-3 text-left transition hover:bg-white"
                        :class="selected?.id === item.id ? 'bg-white ring-1 ring-inset ring-primary-200' : ''"
                        @click="selectTicket(item)"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-teal-600 text-xs font-black text-white">
                            {{ initials(item.customer?.name) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-1">
                                <span class="truncate text-sm font-black text-slate-900">{{ item.customer?.name }}</span>
                                <span v-if="item.unread_count" class="shrink-0 rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-black text-white">{{ item.unread_count }}</span>
                            </span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-1">
                                <span class="rounded-full bg-primary-50 px-1.5 py-0.5 text-[9px] font-black uppercase text-primary-800">{{ item.category_label }}</span>
                                <span class="rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase" :class="statusClass(item.chat_status)">{{ item.chat_status }}</span>
                            </span>
                            <p class="mt-1 truncate text-xs font-semibold text-slate-600">{{ item.subject }}</p>
                            <p class="text-[10px] font-semibold text-slate-400">
                                <span v-if="item.assigned_admin">→ {{ item.assigned_admin.name }}</span>
                                <span v-else>Unassigned</span>
                                · Wait {{ item.wait_minutes }}m
                            </p>
                        </span>
                    </button>
                    <p v-if="!displayQueue.length" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">No conversations match these filters.</p>
                </div>

                <div v-else class="min-h-0 flex-1 overflow-y-auto">
                    <div v-if="historyLoading" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">Loading history…</div>
                    <template v-else>
                        <section v-for="group in historyRecent" :key="`recent-${group.date}`" class="border-b border-slate-100">
                            <p class="sticky top-0 z-[1] bg-slate-50/95 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-slate-500 backdrop-blur-sm">{{ group.label }}</p>
                            <button
                                v-for="item in group.sessions"
                                :key="item.id"
                                type="button"
                                class="flex w-full gap-3 border-b border-slate-100/80 px-3 py-3 text-left transition hover:bg-white"
                                :class="selected?.id === item.id ? 'bg-white ring-1 ring-inset ring-primary-200' : ''"
                                @click="selectTicket(item)"
                            >
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-black text-slate-700">
                                    {{ initials(item.customer?.name) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-black text-slate-900">{{ item.customer?.name }}</span>
                                    <span class="mt-0.5 block truncate text-xs font-semibold text-slate-600">{{ item.subject }}</span>
                                    <span class="text-[10px] font-semibold text-slate-400">{{ formatHistoryTime(item.closed_at) }}</span>
                                </span>
                            </button>
                        </section>
                        <section v-for="group in historyArchived" :key="`arch-${group.date}`" class="border-b border-slate-100">
                            <p class="sticky top-0 z-[1] bg-slate-50/95 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-slate-400 backdrop-blur-sm">{{ group.label }}</p>
                            <button
                                v-for="item in group.sessions"
                                :key="item.id"
                                type="button"
                                class="flex w-full gap-3 border-b border-slate-100/80 px-3 py-3 text-left transition hover:bg-white"
                                :class="selected?.id === item.id ? 'bg-white ring-1 ring-inset ring-primary-200' : ''"
                                @click="selectTicket(item)"
                            >
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-black text-slate-700">
                                    {{ initials(item.customer?.name) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-black text-slate-900">{{ item.customer?.name }}</span>
                                    <span class="mt-0.5 block truncate text-xs font-semibold text-slate-600">{{ item.subject }}</span>
                                </span>
                            </button>
                        </section>
                        <p v-if="!historyRecent.length && !historyArchived.length" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">No closed sessions yet.</p>
                        <p v-else class="px-3 py-4 text-center text-[10px] font-semibold text-slate-400">Sessions older than {{ historyRetentionDays }} days are archived in the list above.</p>
                    </template>
                </div>
            </aside>

            <section class="flex min-h-0 min-w-0 flex-1 flex-col" :class="mobileView === 'queue' ? 'hidden lg:flex' : 'flex'">
                <template v-if="selected">
                    <header class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="mobileView = 'queue'">←</button>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-900">{{ selected.subject }}</p>
                                <p class="text-xs font-semibold text-slate-500">{{ selected.customer?.name }} · @{{ selected.customer?.username }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-black uppercase text-primary-900 hover:bg-primary-100"
                                @click="openUserProfile"
                            >
                                User profile
                            </button>
                            <button v-if="isSuperAdmin" type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-black uppercase text-slate-700" @click="showReassign = !showReassign">Reassign</button>
                            <button
                                v-if="selected.chat_status !== 'closed'"
                                type="button"
                                class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-black uppercase text-white disabled:opacity-50"
                                :disabled="ending"
                                @click="endModalOpen = true"
                            >
                                End session
                            </button>
                        </div>
                    </header>

                    <div v-if="handoffNotice" class="border-b border-sky-100 bg-sky-50 px-4 py-2.5 text-xs font-semibold text-sky-950">
                        {{ handoffNotice }}
                    </div>

                    <div v-if="showReassign && isSuperAdmin" class="border-b border-amber-100 bg-amber-50 px-4 py-3 space-y-2">
                        <p class="text-xs font-black uppercase text-amber-900">Reassign chat</p>
                        <UiSelect
                            v-model="reassignPickId"
                            :options="reassignAdminOptions"
                            placeholder="Choose staff admin…"
                        />
                        <button
                            type="button"
                            class="w-full rounded-lg bg-amber-700 px-3 py-2 text-xs font-black uppercase text-white disabled:opacity-50"
                            :disabled="!reassignPickId"
                            @click="openReassignConfirm"
                        >
                            Reassign…
                        </button>
                    </div>

                    <div ref="scrollEl" class="min-h-0 flex-1 overflow-y-auto p-4">
                        <SupportChatMessages
                            :messages="messages"
                            perspective="staff"
                            @react="onMessageReact"
                            @attachment-loaded="scrollBottom"
                        />
                    </div>

                    <div
                        v-if="typingLabel"
                        class="flex shrink-0 items-center gap-2 border-t border-primary-100 bg-primary-50/90 px-4 py-2.5 text-xs font-semibold text-primary-800"
                    >
                        <span class="flex gap-0.5" aria-hidden="true">
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-600 [animation-delay:0ms]" />
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-600 [animation-delay:150ms]" />
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-600 [animation-delay:300ms]" />
                        </span>
                        {{ typingLabel }}
                    </div>

                    <footer v-if="selected.chat_status !== 'closed' && !canCompose" class="border-t border-slate-100 px-4 py-5 text-center text-sm font-semibold text-slate-600">
                        <p v-if="isSuperAdmin">Reassign this chat to yourself to reply.</p>
                        <p v-else>You no longer have access to reply on this chat.</p>
                    </footer>
                    <footer v-if="selected.chat_status !== 'closed' && canCompose" class="relative shrink-0 border-t border-slate-100 bg-white p-3">
                        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-2 opacity-0">
                            <div v-if="gifOpen" class="absolute bottom-full left-3 right-3 z-40 mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                                <GifPickerPanel :open="gifOpen" :search-url="apiRoute('gifs')" @select="onGifSelected" @close="gifOpen = false" />
                            </div>
                        </Transition>
                        <SupportChatQuickTemplates
                            v-if="!noteMode"
                            :templates="messageTemplates"
                            :disabled="sending || ending"
                            @send-opening="onTemplateOpening"
                            @send-closing="onTemplateClosing"
                        />
                        <p v-if="!noteMode && messageTemplates.agent_signature" class="mb-2 text-[10px] font-semibold text-slate-500">
                            Templates sign as <strong class="text-slate-700">{{ messageTemplates.agent_signature }}</strong>
                        </p>
                        <form class="relative rounded-xl border border-slate-200 bg-white shadow-sm" @submit.prevent="send">
                            <div v-if="pendingGif || pendingFiles.length" class="flex flex-wrap gap-2 border-b border-slate-100 px-3 py-2">
                                <span v-if="pendingGif" class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-2 py-1 text-xs">
                                    <img :src="pendingGif.preview || pendingGif.url" alt="" class="h-8 w-8 rounded object-cover" /> GIF
                                    <button type="button" @mousedown.prevent="pendingGif = null">×</button>
                                </span>
                                <span v-for="(f, i) in pendingFiles" :key="i" class="rounded-lg bg-slate-100 px-2 py-1 text-xs">{{ f.name }} <button type="button" @mousedown.prevent="pendingFiles.splice(i, 1)">×</button></span>
                            </div>
                            <div class="flex gap-2 border-b border-slate-100 px-2 py-1">
                                <button type="button" class="rounded-lg px-2 py-1 text-[10px] font-black uppercase" :class="noteMode ? 'bg-amber-100 text-amber-900' : 'text-slate-500'" @mousedown.prevent="noteMode = !noteMode">{{ noteMode ? 'Internal note' : 'Reply' }}</button>
                            </div>
                            <textarea
                                v-model="composer"
                                rows="4"
                                class="block w-full resize-none border-0 bg-transparent px-3 py-3 text-sm leading-relaxed focus:outline-none"
                                :placeholder="noteMode ? 'Internal note… (Enter to send, Shift+Enter for new line)' : 'Reply… (Enter to send, Shift+Enter for new line)'"
                                @keydown="onComposerKeydown"
                                @input="onComposerInput"
                                @focus="chatPresence.markNow"
                                @blur="stopTyping"
                            />
                            <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-2 py-2.5">
                                <div class="flex gap-1">
                                    <label class="cursor-pointer rounded-lg px-2.5 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">Attach<input type="file" class="sr-only" multiple @change="onFiles" /></label>
                                    <button type="button" class="rounded-lg px-2.5 py-2 text-xs font-bold text-slate-600" @mousedown.prevent="gifOpen = !gifOpen">GIF</button>
                                </div>
                                <button type="submit" class="rounded-xl bg-primary-700 px-6 py-3 text-sm font-black uppercase tracking-wide text-white shadow-sm disabled:opacity-50" :disabled="sending || (!composer.trim() && !pendingFiles.length && !pendingGif)">
                                    Send
                                </button>
                            </div>
                        </form>
                    </footer>
                    <p v-if="selected.chat_status === 'closed'" class="border-t border-slate-100 px-4 py-6 text-center text-sm font-semibold text-slate-500">
                        Session ended. The customer can no longer send messages and will see a feedback prompt in their chat.
                    </p>
                </template>
                <div v-else class="flex flex-1 flex-col items-center justify-center p-8 text-center text-sm text-slate-500">
                    <p class="font-black text-slate-700">Select a chat from the queue</p>
                    <p class="mt-1 max-w-xs">
                        <template v-if="isSuperAdmin">All live chats appear in the sidebar — filter, sort, and open any conversation.</template>
                        <template v-else>Use <strong>My chats</strong> for conversations assigned to you.</template>
                    </p>
                </div>
            </section>
        </div>
        </div>

        <CustomerSupportUserSlideOver
            :open="profileSlideOpen"
            :context="userContext"
            :current-ticket-id="selected?.id"
            @close="profileSlideOpen = false"
            @open-chat="openTicketFromProfile"
        />

        <PortfolioConfirmModal
            :open="reassignModalOpen"
            title="Reassign live chat?"
            :message="reassignConfirmMessage"
            confirm-label="Reassign"
            :processing="reassigning"
            @cancel="reassignModalOpen = false"
            @confirm="confirmReassign"
        />

        <PortfolioConfirmModal
            :open="endModalOpen"
            title="End live support session?"
            message="The customer will see a feedback prompt in their chat. You will not be able to send new messages after ending."
            confirm-label="End session"
            :processing="ending"
            @cancel="endModalOpen = false"
            @confirm="confirmEndChat"
        />

        <Teleport to="body">
            <Transition name="fade">
                <div
                    v-if="closingTemplateModalOpen"
                    class="fixed inset-0 z-[100] flex items-end justify-center bg-slate-900/50 p-3 backdrop-blur-sm sm:items-center sm:p-4"
                    role="dialog"
                    aria-modal="true"
                    @click.self="closingTemplateModalOpen = false"
                >
                    <div class="max-h-[85dvh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:rounded-xl">
                        <h2 class="font-display text-lg font-bold text-slate-900">Send closing message & end session?</h2>
                        <p class="mt-1 text-sm font-medium text-slate-600">
                            This sends your closing message to the customer, then ends the live chat. They will be prompted for feedback.
                        </p>
                        <pre class="mt-4 max-h-48 overflow-y-auto whitespace-pre-wrap rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm font-medium leading-relaxed text-slate-800">{{ closingPreviewMessage }}</pre>
                        <div class="mt-5 flex flex-wrap justify-end gap-3">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"
                                @click="closingTemplateModalOpen = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-bold text-white shadow-md hover:bg-rose-700 disabled:opacity-60"
                                :disabled="sending || ending"
                                @click="confirmClosingTemplate"
                            >
                                Send & end session
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </component>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import GifPickerPanel from '@/Components/Chat/GifPickerPanel.vue';
import SupportChatMessages from '@/Components/Support/SupportChatMessages.vue';
import SupportChatQuickTemplates from '@/Components/Support/SupportChatQuickTemplates.vue';
import PortfolioConfirmModal from '@/Components/Portfolio/PortfolioConfirmModal.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import CustomerSupportUserSlideOver from '@/Pages/Admin/CustomerSupport/Components/CustomerSupportUserSlideOver.vue';
import { ensureEcho } from '@/utils/ensureEcho';
import { broadcastConfigFromPage } from '@/utils/broadcastConfig';
import { formatChatMessageTime, groupMessagesByChatDay } from '@/utils/chatMessageDates';
import { useSupportChatRealtime } from '@/composables/useSupportChatRealtime';
import { useChatComposer } from '@/composables/useChatComposer';
import { useMessagingViewPresence } from '@/composables/useMessagingViewPresence';

const props = defineProps({
    routeNamespace: { type: String, default: 'admin' },
    queuePanels: { type: Object, required: true },
    selectedTicket: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    hasMore: { type: Boolean, default: false },
    userContext: { type: Object, default: null },
    onlineAdmins: { type: Array, default: () => [] },
    allAdmins: { type: Array, default: () => [] },
    filterAdmins: { type: Array, default: () => [] },
    isSuperAdmin: { type: Boolean, default: false },
    viewerId: { type: Number, default: null },
    categories: { type: Object, default: () => ({}) },
});

const page = usePage();
const shellComponent = computed(() => (props.routeNamespace === 'operations' ? OperationsShell : AdminShell));
const supportPath = computed(() => (props.routeNamespace === 'operations' ? '/operations/customer-support' : '/admin/customer-support'));
const supportChangedEvent = computed(() => (props.routeNamespace === 'operations' ? 'operations:support-changed' : 'admin:support-changed'));
const notificationsChangedEvent = computed(() => (props.routeNamespace === 'operations' ? 'operations:notifications-changed' : 'admin:notifications-changed'));

const sections = ref(props.queuePanels.sections ?? []);
const activeSection = ref(props.queuePanels.active_section ?? 'all');
const queue = ref(props.queuePanels.items ?? []);
const selected = ref(props.selectedTicket);
const messages = ref([...props.messages]);
const userContext = ref(props.userContext);
const isSuperAdmin = props.isSuperAdmin;
const reassignTargets = ref([...props.allAdmins]);
const filterAdmins = ref([...(props.filterAdmins.length ? props.filterAdmins : props.allAdmins)]);

const sidebarTab = ref('live');
const searchQ = ref('');
const filterAdminId = ref('');
const historySearchQ = ref('');
const historyFilterAdminId = ref('');
const historyRecent = ref([]);
const historyArchived = ref([]);
const historyRetentionDays = ref(30);
const historyLoading = ref(false);
const endModalOpen = ref(false);
const closingTemplateModalOpen = ref(false);
const pendingClosingTemplate = ref(null);
const mobileView = ref(props.selectedTicket ? 'chat' : 'queue');
const profileSlideOpen = ref(!!props.selectedTicket);
const showReassign = ref(false);
const reassignPickId = ref(null);
const reassignModalOpen = ref(false);
const reassigning = ref(false);
const assignmentToast = ref('');
const queueSort = ref('activity');
const queueFilterStatus = ref('');
const queueFilterCategory = ref('');
const queueFilterAssign = ref('');
const composer = ref('');
const sending = ref(false);
const ending = ref(false);
const gifOpen = ref(false);
const pendingGif = ref(null);
const pendingFiles = ref([]);
const noteMode = ref(false);
const scrollEl = ref(null);
const typingUsers = ref({});
const loadError = ref('');

let typingStartDebounce = null;
let typingIdleTimer = null;
let typingActive = false;
let searchTimer = null;
let historyTimer = null;
let userChannel = null;
let staffChannel = null;
let assignmentTimer = null;
let typingClearTimer = null;
let typingPollTimer = null;
let markReadTimer = null;
let scrollBottomTimers = [];

function liveBroadcastConfig() {
    return broadcastConfigFromPage(page);
}

const chatRealtime = useSupportChatRealtime({
    reverbConfig: liveBroadcastConfig,
    normalizeMessage: normalizeStaffMessage,
    getMessageCutoff: () => selected.value?.message_cutoff_id ?? null,
    onMessage: (msg) => {
        if (messages.value.some((m) => m.id === msg.id)) {
            return false;
        }
        messages.value.push(msg);
        if (msg.is_customer || msg.sender_type === 'customer') {
            clearCustomerTyping();
        }
        scrollBottom();
        emitSupportChanged();
        debouncedQueueRefresh();
        scheduleMarkRead();

        return true;
    },
    onSessionUpdated: (t) => {
        if (!selected.value || t?.id !== selected.value.id) {
            return;
        }
        selected.value = t;
        if (t.chat_status === 'closed') {
            moveClosedTicketToHistory(t);
            sidebarTab.value = 'history';
            void loadHistory();
        }
        debouncedQueueRefresh();
    },
    onTyping: (e) => applyCustomerTyping(e),
});

const typingLabel = computed(() => {
    const names = Object.values(typingUsers.value).filter(Boolean);
    return names.length ? `${names[0]} is typing…` : '';
});

function clearCustomerTyping() {
    typingUsers.value = {};
    clearTimeout(typingClearTimer);
    typingClearTimer = null;
}

function isTypingActive(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
}

function applyCustomerTyping(payload) {
    if (!payload || typeof payload !== 'object' || !selected.value) {
        return;
    }

    const side = String(payload.side ?? '').toLowerCase();
    if (side === 'admin') {
        return;
    }

    const ticketId = payload.ticket_id ?? payload.ticketId;
    if (ticketId != null && Number(ticketId) !== Number(selected.value.id)) {
        return;
    }

    clearTimeout(typingClearTimer);

    if (!isTypingActive(payload.typing)) {
        clearCustomerTyping();

        return;
    }

    const displayName = payload.name || payload.first_name || selected.value?.customer?.name || 'Customer';
    typingUsers.value = { customer: displayName };
    scrollBottom();
    typingClearTimer = setTimeout(() => {
        clearCustomerTyping();
    }, 4500);
}

const canCompose = computed(() => selected.value?.can_compose === true);
const handoffNotice = computed(() => selected.value?.handoff_notice || '');

const sortOptions = [
    { value: 'activity', label: 'Last activity' },
    { value: 'wait', label: 'Longest wait' },
    { value: 'priority', label: 'Priority' },
    { value: 'name', label: 'Customer name' },
];

const statusFilterOptions = [
    { value: '', label: 'All statuses' },
    { value: 'queued', label: 'Queued' },
    { value: 'active', label: 'Active' },
];

const categoryFilterOptions = computed(() => [
    { value: '', label: 'All categories' },
    ...Object.entries(props.categories || {}).map(([value, label]) => ({ value, label })),
]);

const assignFilterOptions = [
    { value: '', label: 'Any assignment' },
    { value: 'unassigned', label: 'Unassigned only' },
    { value: 'assigned', label: 'Assigned only' },
];

const filterAdminOptions = computed(() => [
    { value: '', label: 'All staff admins' },
    ...filterAdmins.value.map((a) => ({ value: String(a.id), label: a.name })),
]);

const reassignAdminOptions = computed(() =>
    reassignTargets.value.map((a) => ({
        value: a.id,
        label: a.online ? `${a.name} · Online` : a.name,
    })),
);

const pendingReassignAdmin = computed(() =>
    reassignTargets.value.find((a) => Number(a.id) === Number(reassignPickId.value)) ?? null,
);

const reassignConfirmMessage = computed(() => {
    const name = pendingReassignAdmin.value?.name?.replace(/\s*\(assign to me\)\s*/i, '') ?? 'this admin';
    return `Reassign this chat to ${name}? The full conversation history will be shared with them. The previous handler keeps history up to this point but cannot send new messages or see replies after handoff.`;
});

const priorityRank = { urgent: 0, high: 1, normal: 2, low: 3 };

const displayQueue = computed(() => {
    let items = [...queue.value];
    if (!isSuperAdmin) {
        return items;
    }
    if (queueFilterStatus.value) {
        items = items.filter((i) => i.chat_status === queueFilterStatus.value);
    }
    if (queueFilterCategory.value) {
        items = items.filter((i) => i.category === queueFilterCategory.value);
    }
    if (queueFilterAssign.value === 'unassigned') {
        items = items.filter((i) => !i.assigned_admin);
    } else if (queueFilterAssign.value === 'assigned') {
        items = items.filter((i) => !!i.assigned_admin);
    }
    const sortKey = queueSort.value;
    items.sort((a, b) => {
        if (sortKey === 'wait') {
            return (b.wait_minutes ?? 0) - (a.wait_minutes ?? 0);
        }
        if (sortKey === 'priority') {
            return (priorityRank[a.priority] ?? 9) - (priorityRank[b.priority] ?? 9);
        }
        if (sortKey === 'name') {
            return (a.customer?.name || '').localeCompare(b.customer?.name || '');
        }
        const at = new Date(a.last_activity_at || a.opened_at || 0).getTime();
        const bt = new Date(b.last_activity_at || b.opened_at || 0).getTime();
        return bt - at;
    });
    return items;
});

function pageRoute(suffix, params = {}) {
    return window.route(`${props.routeNamespace}.${suffix}`, params);
}

function apiRoute(suffix, params = {}) {
    return window.route(`${props.routeNamespace}.api.customer-support.${suffix}`, params);
}

function emitSupportChanged() {
    window.dispatchEvent(new CustomEvent(supportChangedEvent.value));
}

function emitNotificationsChanged() {
    window.dispatchEvent(new CustomEvent(notificationsChangedEvent.value));
}

function clearTicketUnreadInQueue(ticketId) {
    const patch = (item) => (Number(item.id) === Number(ticketId) ? { ...item, unread_count: 0 } : item);
    queue.value = queue.value.map(patch);
    sections.value = sections.value.map((sec) => ({
        ...sec,
        items: (sec.items ?? []).map(patch),
    }));
}

const chatPresence = useMessagingViewPresence(async () => {
    if (!selected.value?.id) {
        return;
    }
    const last = [...messages.value].reverse().find((m) => m.is_customer || m.sender_type === 'customer');
    await window.axios.post(apiRoute('read', { ticket: selected.value.id }), {
        last_message_id: last?.id ?? undefined,
    });
    clearTicketUnreadInQueue(selected.value.id);
    emitSupportChanged();
    emitNotificationsChanged();
});

onMounted(() => {
    ensureEcho(liveBroadcastConfig());
    bindStaffQueueChannel();
    if (isSuperAdmin) {
        activeSection.value = 'all';
    }
    if (selected.value) {
        subscribe(selected.value.id);
        chatPresence.start();
        scrollBottom();
    }
    bindAssignmentNotifications();
    void window.axios.post(apiRoute('reconcile-notifications')).then(() => {
        emitSupportChanged();
        emitNotificationsChanged();
    }).catch(() => {});
    const params = new URLSearchParams(window.location.search);
    const ticketId = params.get('ticket');
    if (ticketId && !selected.value) {
        const hit = queue.value.find((q) => String(q.id) === ticketId);
        if (hit) selectTicket(hit);
        else openTicketById(ticketId);
    }
});

onBeforeUnmount(() => {
    clearTimeout(markReadTimer);
    chatPresence.stop();
    leaveChannel();
    leaveUserChannel();
    leaveStaffChannel();
    clearTimeout(typingStartDebounce);
    clearTimeout(typingIdleTimer);
    clearTimeout(searchTimer);
    clearTimeout(historyTimer);
    clearTimeout(assignmentTimer);
    clearTimeout(typingClearTimer);
    stopTypingPoll();
    scrollBottomTimers.forEach((id) => clearTimeout(id));
    scrollBottomTimers = [];
});

function bindAssignmentNotifications() {
    const echo = ensureEcho(liveBroadcastConfig());
    const uid = props.viewerId || page.props.auth?.user?.id;
    if (!echo || !uid) return;
    userChannel = echo.private(`App.Models.User.${uid}`);
    userChannel.listen('.support.chat.assigned', (e) => {
        assignmentToast.value = `New chat assigned: ${e.ticket?.subject ?? 'Support'}`;
        clearTimeout(assignmentTimer);
        assignmentTimer = setTimeout(() => { assignmentToast.value = ''; }, 6000);
        debouncedQueueRefresh();
        emitSupportChanged();
        if (e.ticket?.id && !selected.value) {
            selectTicket(e.ticket);
        }
    });
}

function leaveUserChannel() {
    const uid = props.viewerId || page.props.auth?.user?.id;
    if (uid && window.Echo) window.Echo.leave(`App.Models.User.${uid}`);
    userChannel = null;
}

function bindStaffQueueChannel() {
    const echo = ensureEcho(liveBroadcastConfig());
    if (!echo) {
        return;
    }

    if (staffChannel) {
        leaveStaffChannel();
    }

    staffChannel = echo.private('customer-support.staff');

    const handleStaffTyping = (payload) => {
        if (!payload) {
            return;
        }
        applyCustomerTyping(payload);
    };

    const handleStaffMessage = (payload) => {
        const ticketId = payload?.ticket_id;
        if (!payload?.message || !selected.value || ticketId == null) {
            return;
        }
        if (Number(ticketId) !== Number(selected.value.id)) {
            return;
        }
        const msg = normalizeStaffMessage(payload.message);
        if (messages.value.some((m) => Number(m.id) === Number(msg.id))) {
            return;
        }
        messages.value.push(msg);
        if (msg.is_customer || msg.sender_type === 'customer') {
            clearCustomerTyping();
        }
        chatRealtime.setLastMessageId(msg.id);
        scrollBottom();
        emitSupportChanged();
        debouncedQueueRefresh();
        scheduleMarkRead();
    };

    staffChannel.listen('.queue.changed', (e) => {
        debouncedQueueRefresh();
        const ticketId = e.ticket?.id;
        if (!ticketId || !selected.value) {
            return;
        }
        if (Number(selected.value.id) !== Number(ticketId)) {
            return;
        }
        if (e.ticket) {
            selected.value = { ...selected.value, ...e.ticket };
        }
    });
    staffChannel.listen('.message.sent', handleStaffMessage);
    staffChannel.listen('message.sent', handleStaffMessage);
    staffChannel.listen('.typing', handleStaffTyping);
    staffChannel.listen('typing', handleStaffTyping);
}

function leaveStaffChannel() {
    if (window.Echo) {
        window.Echo.leave('customer-support.staff');
    }
    staffChannel = null;
}

function hasMessage(msg) {
    const id = Number(msg?.id);
    if (Number.isFinite(id) && id > 0) {
        return messages.value.some((m) => Number(m.id) === id);
    }

    return messages.value.some((m) => m.id === msg?.id);
}

function switchSection(key) {
    activeSection.value = key;
    debouncedQueueRefresh();
}

function initials(name) {
    return (name || '?').split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
}

function statusClass(status) {
    if (status === 'active') return 'bg-emerald-50 text-emerald-800';
    if (status === 'queued') return 'bg-amber-50 text-amber-900';
    return 'bg-slate-100 text-slate-600';
}

function formatHistoryTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function openHistoryTab() {
    sidebarTab.value = 'history';
    mobileView.value = 'queue';
    loadHistory();
}

async function loadHistory() {
    historyLoading.value = true;
    try {
        const { data } = await window.axios.get(apiRoute('history'), {
            params: {
                q: historySearchQ.value || undefined,
                admin_id: isSuperAdmin
                    ? (historyFilterAdminId.value || filterAdminId.value
                        ? Number(historyFilterAdminId.value || filterAdminId.value)
                        : undefined)
                    : undefined,
            },
        });
        historyRecent.value = data.recent ?? [];
        historyArchived.value = data.archived ?? [];
        historyRetentionDays.value = data.retention_days ?? 30;
    } finally {
        historyLoading.value = false;
    }
}

function debouncedHistoryLoad() {
    clearTimeout(historyTimer);
    historyTimer = setTimeout(loadHistory, 300);
}

function sessionClosedAdminText(m) {
    return m.body || m.admin_body || 'Live support session has ended.';
}

function normalizeStaffMessage(msg) {
    if (!msg || msg.kind !== 'session_closed') {
        return msg;
    }

    return {
        ...msg,
        body: msg.admin_body || msg.body || 'You ended this live support session. The customer has been prompted for feedback.',
        admin_body: null,
        reactions: null,
        feedback_url: null,
    };
}

function openReassignConfirm() {
    if (!reassignPickId.value) {
        return;
    }
    reassignModalOpen.value = true;
}

function isImage(att) {
    return att?.type === 'gif' || att?.type === 'image' || String(att?.mime || '').startsWith('image/');
}

function attUrl(att) {
    return att?.url || att?.path || '';
}

function syncTicketUrl(ticketId) {
    const url = new URL(window.location.href);
    if (ticketId) {
        url.searchParams.set('ticket', String(ticketId));
    } else {
        url.searchParams.delete('ticket');
    }
    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
}

async function loadUserContext(userId) {
    if (!userId) {
        userContext.value = null;
        return;
    }
    try {
        const { data } = await window.axios.get(apiRoute('user-context', { user: userId }));
        userContext.value = data;
    } catch {
        userContext.value = null;
    }
}

function openUserProfile() {
    profileSlideOpen.value = true;
    const userId = selected.value?.customer?.id;
    if (userId) {
        loadUserContext(userId);
    }
}

async function openTicketFromProfile(ticketId) {
    profileSlideOpen.value = false;
    sidebarTab.value = 'history';
    mobileView.value = 'chat';
    await openTicketById(ticketId);
}

async function openTicketById(ticketId) {
    loadError.value = '';
    clearCustomerTyping();
    try {
        const { data } = await window.axios.get(apiRoute('open', { ticket: ticketId }));
        selected.value = data.ticket;
        messages.value = (data.messages || []).map(normalizeStaffMessage);
        userContext.value = null;
        mobileView.value = 'chat';
        syncLastMessageId();
        subscribe(ticketId);
        syncTicketUrl(ticketId);
        await nextTick();
        scrollBottom();
        emitSupportChanged();
    } catch {
        loadError.value = 'Could not open this conversation.';
    }
}

async function selectTicket(item) {
    mobileView.value = 'chat';
    loadError.value = '';
    clearCustomerTyping();
    try {
        const { data } = await window.axios.get(apiRoute('open', { ticket: item.id }));
        selected.value = data.ticket;
        messages.value = (data.messages || []).map(normalizeStaffMessage);
        userContext.value = null;
        syncLastMessageId();
        subscribe(item.id);
        syncTicketUrl(item.id);
        await nextTick();
        scrollBottom();
        emitSupportChanged();
    } catch {
        loadError.value = 'Could not load this chat. Try again.';
        selected.value = null;
        messages.value = [];
    }
}

async function refreshQueueNow() {
    if (sidebarTab.value !== 'live') {
        return;
    }
    const section = isSuperAdmin ? 'all' : activeSection.value;
    try {
        const { data } = await window.axios.get(apiRoute('queue'), {
            params: {
                q: searchQ.value || undefined,
                section,
                admin_id: filterAdminId.value ? Number(filterAdminId.value) : undefined,
            },
        });
        sections.value = data.sections ?? [];
        queue.value = data.items ?? [];
        activeSection.value = data.active_section ?? activeSection.value;
    } catch {
        /* ignore */
    }
}

async function debouncedQueueRefresh() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(refreshQueueNow, 900);
}

function syncLastMessageId() {
    chatRealtime.syncLastMessageId(messages.value);
}

function scheduleMarkRead() {
    clearTimeout(markReadTimer);
    markReadTimer = setTimeout(() => {
        chatPresence.markNow();
    }, 400);
}

function stopTypingPoll() {
    if (typingPollTimer) {
        window.clearInterval(typingPollTimer);
        typingPollTimer = null;
    }
}

function startTypingPoll() {
    stopTypingPoll();
    if (!selected.value?.id) {
        return;
    }
    const poll = async () => {
        if (!selected.value?.id) {
            return;
        }
        try {
            const { data } = await window.axios.get(apiRoute('typing-state', { ticket: selected.value.id }));
            const t = data?.typing;
            if (t && isTypingActive(t.typing) && String(t.side ?? '').toLowerCase() === 'customer') {
                applyCustomerTyping({ ...t, typing: true });
            } else {
                clearCustomerTyping();
            }
        } catch {
            /* ignore */
        }
    };
    void poll();
    typingPollTimer = window.setInterval(poll, 1500);
}

function subscribe(ticketId) {
    clearCustomerTyping();
    chatRealtime.subscribe(ticketId, messages.value);
    startTypingPoll();
    chatPresence.start();
    scheduleMarkRead();
}

function leaveChannel() {
    chatRealtime.teardown();
    stopTypingPoll();
    chatPresence.stop();
}

function scrollBottom() {
    scrollBottomTimers.forEach((id) => clearTimeout(id));
    scrollBottomTimers = [];

    const run = () => {
        const el = scrollEl.value;
        if (!el) {
            return;
        }
        el.scrollTop = el.scrollHeight;
    };

    nextTick(() => {
        run();
        requestAnimationFrame(() => {
            run();
            requestAnimationFrame(run);
        });
    });

    for (const delay of [50, 150, 400, 800]) {
        scrollBottomTimers.push(setTimeout(run, delay));
    }
}

watch(
    () => [selected.value?.id, messages.value.length],
    () => {
        if (selected.value) {
            scrollBottom();
        }
    },
    { flush: 'post' },
);

function onGifSelected(gif) {
    pendingGif.value = gif;
    gifOpen.value = false;
}

function onFiles(e) {
    pendingFiles.value = [...pendingFiles.value, ...Array.from(e.target.files || [])];
    e.target.value = '';
}

function onComposerInput() {
    if (!selected.value) {
        return;
    }
    clearTimeout(typingIdleTimer);
    clearTimeout(typingStartDebounce);
    typingStartDebounce = setTimeout(() => {
        if (!typingActive) {
            typingActive = true;
            window.axios.post(apiRoute('typing', { ticket: selected.value.id }), { typing: true }).catch(() => {});
        }
    }, 300);
    typingIdleTimer = setTimeout(stopTyping, 300);
}

function stopTyping() {
    clearTimeout(typingStartDebounce);
    clearTimeout(typingIdleTimer);
    if (!selected.value || !typingActive) {
        return;
    }
    typingActive = false;
    window.axios.post(apiRoute('typing', { ticket: selected.value.id }), { typing: false }).catch(() => {});
}

async function onMessageReact({ message, emoji }) {
    if (!selected.value?.id || !message?.id) {
        return;
    }
    try {
        const { data } = await window.axios.post(
            apiRoute('react', { ticket: selected.value.id, message: message.id }),
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

function customerFirstNameFromTicket(ticket) {
    const customer = ticket?.customer;
    if (customer?.first_name) {
        return String(customer.first_name).trim() || 'there';
    }
    const name = String(customer?.name ?? '').trim();
    return name ? name.split(/\s+/)[0] : 'there';
}

function resolveTemplateBody(body) {
    const customer = customerFirstNameFromTicket(selected.value);
    const signature = props.messageTemplates?.agent_signature ?? '';
    return String(body ?? '')
        .replace(/\{\{customer_name\}\}/g, customer)
        .replace(/\{\{agent_signature\}\}/g, signature);
}

const closingPreviewMessage = computed(() => {
    if (!pendingClosingTemplate.value?.body) {
        return '';
    }
    return resolveTemplateBody(pendingClosingTemplate.value.body);
});

async function postComposerMessage({ body, visibility, attachments, gifUrl, restoreOnFail }) {
    if (!selected.value || !canCompose.value) {
        return false;
    }

    stopTyping();
    const fd = new FormData();
    if (body) {
        fd.append('body', body);
    }
    if (visibility === 'internal') {
        fd.append('visibility', 'internal');
    }
    (attachments ?? []).forEach((f) => fd.append('attachments[]', f));
    if (gifUrl) {
        fd.append('gif_url', gifUrl);
    }

    const optimisticId = `pending-${Date.now()}`;
    const viewer = page.props.auth?.user;
    const isNote = visibility === 'internal';
    const optimistic = {
        id: optimisticId,
        body: body || (gifUrl ? '[GIF]' : '[Attachment]'),
        visibility: isNote ? 'internal' : 'public',
        sender_type: 'admin',
        is_admin_message: true,
        is_customer: false,
        align: isNote ? 'center' : 'end',
        sender: viewer ? { id: viewer.id, name: viewer.name } : null,
        attachments: gifUrl ? [{ type: 'gif', url: gifUrl }] : [],
        created_at: new Date().toISOString(),
        pending: true,
    };

    messages.value.push(optimistic);
    scrollBottom();
    sending.value = true;
    try {
        const { data } = await window.axios.post(apiRoute('send', { ticket: selected.value.id }), fd);
        messages.value = messages.value.filter((m) => m.id !== optimisticId);
        if (!hasMessage(data.message)) {
            messages.value.push(normalizeStaffMessage(data.message));
            chatRealtime.setLastMessageId(data.message.id);
        }
        scrollBottom();
        emitSupportChanged();
        return true;
    } catch {
        messages.value = messages.value.filter((m) => m.id !== optimisticId);
        if (restoreOnFail) {
            restoreOnFail();
        }
        return false;
    } finally {
        sending.value = false;
    }
}

async function sendPublicBody(body) {
    const trimmed = String(body ?? '').trim();
    if (!trimmed || noteMode.value) {
        return false;
    }
    return postComposerMessage({ body: trimmed, visibility: 'public' });
}

async function onTemplateOpening(template) {
    if (!template?.body || sending.value || ending.value) {
        return;
    }
    await sendPublicBody(resolveTemplateBody(template.body));
}

function onTemplateClosing(template) {
    if (!template?.body || sending.value || ending.value) {
        return;
    }
    pendingClosingTemplate.value = template;
    closingTemplateModalOpen.value = true;
}

async function confirmClosingTemplate() {
    if (!pendingClosingTemplate.value || !selected.value) {
        return;
    }
    const body = resolveTemplateBody(pendingClosingTemplate.value.body);
    closingTemplateModalOpen.value = false;
    const sent = await sendPublicBody(body);
    pendingClosingTemplate.value = null;
    if (sent) {
        await confirmEndChat();
    }
}

async function send() {
    if (!selected.value || !canCompose.value) {
        return;
    }
    const body = composer.value.trim();
    if (!body && !pendingFiles.value.length && !pendingGif.value) {
        return;
    }

    const snap = { body, gif: pendingGif.value, files: [...pendingFiles.value] };
    composer.value = '';
    pendingGif.value = null;
    pendingFiles.value = [];

    await postComposerMessage({
        body,
        visibility: noteMode.value ? 'internal' : 'public',
        attachments: snap.files,
        gifUrl: snap.gif?.url,
        restoreOnFail: () => {
            composer.value = snap.body;
            pendingGif.value = snap.gif;
            pendingFiles.value = snap.files;
        },
    });
}

const { onComposerKeydown } = useChatComposer(() => send());

function moveClosedTicketToHistory(ticket) {
    if (!ticket?.id) {
        return;
    }
    queue.value = queue.value.filter((q) => Number(q.id) !== Number(ticket.id));
}

async function confirmEndChat() {
    if (!selected.value || ending.value) {
        return;
    }
    ending.value = true;
    loadError.value = '';
    const ticketId = selected.value.id;
    try {
        const { data } = await window.axios.post(apiRoute('end', { ticket: ticketId }));
        selected.value = data.ticket;
        moveClosedTicketToHistory(data.ticket);
        endModalOpen.value = false;
        sidebarTab.value = 'history';
        try {
            const { data: openData } = await window.axios.get(apiRoute('open', { ticket: ticketId }));
            selected.value = openData.ticket ?? data.ticket;
            messages.value = (openData.messages ?? []).map(normalizeStaffMessage);
            syncLastMessageId();
        } catch {
            /* session.closed message may arrive via websocket */
        }
        await nextTick();
        scrollBottom();
        void loadHistory();
        void refreshQueueNow();
    } catch (err) {
        endModalOpen.value = false;
        const status = err?.response?.status;
        loadError.value =
            status === 429
                ? 'Too many requests — wait a few seconds, then try ending the session again.'
                : err?.response?.data?.message || 'Could not end this session. Try again.';
    } finally {
        ending.value = false;
    }
}

async function confirmReassign() {
    if (!selected.value || !reassignPickId.value || reassigning.value) {
        return;
    }
    reassigning.value = true;
    try {
        const { data } = await window.axios.post(apiRoute('reassign', { ticket: selected.value.id }), {
            admin_id: Number(reassignPickId.value),
        });
        selected.value = data.ticket;
        const openRes = await window.axios.get(apiRoute('open', { ticket: selected.value.id }));
        messages.value = (openRes.data.messages || []).map(normalizeStaffMessage);
        syncLastMessageId();
        showReassign.value = false;
        reassignModalOpen.value = false;
        reassignPickId.value = null;
        await nextTick();
        scrollBottom();
        debouncedQueueRefresh();
    } finally {
        reassigning.value = false;
    }
}
</script>
