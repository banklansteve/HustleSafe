<template>
    <AdminSlideOver
        :open="open"
        title="Customer profile"
        eyebrow="Support context"
        width-class="w-full max-w-lg"
        panel-class="bg-white text-slate-950"
        @close="emit('close')"
    >
        <div v-if="context" class="space-y-6 text-sm">
            <section class="rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50 to-white p-4">
                <div class="flex items-start gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-600 to-teal-600 text-sm font-black text-white">
                        {{ initials(context.user?.name) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-lg font-black text-slate-950">{{ context.user?.name }}</p>
                        <p class="text-xs font-semibold text-slate-500">@{{ context.user?.username }} · {{ context.user?.email }}</p>
                        <p class="mt-1 text-xs font-bold uppercase text-primary-800">{{ context.user?.role }} · Tier {{ context.user?.verification_level ?? 0 }}</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a :href="context.user?.profile_url" target="_blank" rel="noopener" class="rounded-xl bg-primary-700 px-3 py-2 text-[10px] font-black uppercase text-white hover:bg-primary-800">Full profile</a>
                    <a :href="context.user?.kyc_url" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">KYC centre</a>
                    <a :href="context.user?.verification_url" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Verifications</a>
                    <a :href="context.user?.financial_url" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Financial</a>
                </div>
            </section>

            <section v-if="context.previous_support_chats?.length" class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Previous support chats</p>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black text-slate-600 ring-1 ring-slate-200">
                        {{ context.previous_support_chats.length }}
                    </span>
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-500">Tap a session to open it in the chat panel — no page reload.</p>
                <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-0.5">
                    <li v-for="item in context.previous_support_chats" :key="item.id">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 rounded-xl border px-3 py-3 text-left transition"
                            :class="Number(currentTicketId) === Number(item.id)
                                ? 'border-primary-300 bg-primary-50 ring-1 ring-primary-200'
                                : 'border-slate-200 bg-white hover:border-primary-200 hover:bg-primary-50/40'"
                            @click="emit('open-chat', item.id)"
                        >
                            <span
                                class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-[10px] font-black uppercase"
                                :class="item.chat_status === 'closed' ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-800'"
                            >
                                {{ item.chat_status === 'closed' ? '✓' : '●' }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-black text-slate-900">{{ item.subject }}</span>
                                <span class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-slate-600">{{ item.category }}</span>
                                    <span
                                        class="rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase"
                                        :class="item.chat_status === 'closed' ? 'bg-slate-200 text-slate-700' : 'bg-emerald-50 text-emerald-800'"
                                    >{{ item.chat_status }}</span>
                                </span>
                                <span class="mt-1 block text-[10px] font-semibold text-slate-400">
                                    {{ formatChatWhen(item) }}
                                </span>
                            </span>
                            <span class="shrink-0 text-[10px] font-black uppercase text-primary-700">Open</span>
                        </button>
                    </li>
                </ul>
            </section>

            <section v-if="context.kyc_cases?.length">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">KYC review cases</p>
                <ul class="mt-2 space-y-2">
                    <li v-for="item in context.kyc_cases" :key="item.id">
                        <a :href="item.url" target="_blank" rel="noopener" class="block rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5 hover:border-primary-200 hover:bg-primary-50/60">
                            <span class="font-bold text-slate-900">Tier {{ item.target_tier }} · {{ item.verification_type }}</span>
                            <span class="mt-0.5 block text-xs font-semibold text-slate-500">{{ item.status }} · {{ item.priority }} →</span>
                        </a>
                    </li>
                </ul>
            </section>

            <section v-if="context.verifications?.length">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Verifications</p>
                <ul class="mt-2 space-y-1">
                    <li v-for="item in context.verifications" :key="item.id">
                        <a :href="item.url" target="_blank" rel="noopener" class="flex justify-between rounded-xl border border-slate-100 px-3 py-2 hover:bg-slate-50">
                            <span class="font-semibold text-slate-800">{{ item.type }}</span>
                            <span class="text-xs font-bold text-slate-500">{{ item.status }}</span>
                        </a>
                    </li>
                </ul>
            </section>

            <section v-if="context.active_quests?.length">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Active quests</p>
                <ul class="mt-2 space-y-2">
                    <li v-for="item in context.active_quests" :key="item.id">
                        <a :href="item.url" target="_blank" rel="noopener" class="block rounded-xl border border-slate-100 px-3 py-2 hover:border-primary-200 hover:bg-primary-50/50">
                            <span class="font-bold text-slate-900">{{ item.reference_code }}</span>
                            <span class="mt-0.5 block truncate text-xs text-slate-600">{{ item.title }}</span>
                        </a>
                    </li>
                </ul>
            </section>

            <section v-if="context.proposals?.length">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Proposals</p>
                <ul class="mt-2 space-y-2">
                    <li v-for="item in context.proposals" :key="item.id">
                        <a :href="item.url" target="_blank" rel="noopener" class="block rounded-xl border border-slate-100 px-3 py-2 hover:border-primary-200 hover:bg-primary-50/50">
                            <span class="font-bold text-slate-900">{{ item.reference_code || 'Proposal' }}</span>
                            <span class="mt-0.5 block truncate text-xs text-slate-600">{{ item.quest }}</span>
                        </a>
                    </li>
                </ul>
            </section>

            <section v-if="context.contracts?.length">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Contracts</p>
                <ul class="mt-2 space-y-2">
                    <li v-for="item in context.contracts" :key="item.id">
                        <div class="rounded-xl border border-slate-100 px-3 py-2">
                            <a :href="item.url" target="_blank" rel="noopener" class="font-bold text-primary-800 hover:underline">{{ item.reference_code }}</a>
                            <p class="truncate text-xs text-slate-600">{{ item.title }}</p>
                            <a :href="item.ledger_url" target="_blank" rel="noopener" class="mt-1 inline-block text-[10px] font-black uppercase text-primary-700 hover:underline">Escrow ledger</a>
                        </div>
                    </li>
                </ul>
            </section>

            <section v-if="context.disputes?.length">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Disputes</p>
                <ul class="mt-2 space-y-2">
                    <li v-for="item in context.disputes" :key="item.id">
                        <a :href="item.url" target="_blank" rel="noopener" class="block rounded-xl border border-rose-100 bg-rose-50/50 px-3 py-2">
                            <span class="font-bold text-rose-950">{{ item.reference_code || `#${item.id}` }}</span>
                            <span class="text-xs font-semibold text-rose-800">{{ item.status }}</span>
                        </a>
                    </li>
                </ul>
            </section>

            <section v-if="context.payments?.length">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Payments & ledger</p>
                <ul class="mt-2 space-y-1">
                    <li v-for="item in context.payments" :key="item.id">
                        <a :href="item.url" target="_blank" rel="noopener" class="flex justify-between rounded-xl border border-slate-100 px-3 py-2 hover:bg-slate-50">
                            <span class="font-semibold text-slate-800">{{ item.type }}</span>
                            <span class="text-xs font-bold text-slate-500">{{ item.status }}</span>
                        </a>
                    </li>
                </ul>
            </section>
        </div>
        <p v-else class="py-12 text-center text-sm font-semibold text-slate-500">Select a chat to load profile context.</p>
    </AdminSlideOver>
</template>

<script setup>
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';

defineProps({
    open: { type: Boolean, default: false },
    context: { type: Object, default: null },
    currentTicketId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'open-chat']);

function initials(name) {
    return (name || '?').split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
}

function formatChatWhen(item) {
    const iso = item.closed_at || item.created_at;
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    const label = item.closed_at ? 'Closed' : 'Opened';

    return `${label} ${d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })}`;
}
</script>
