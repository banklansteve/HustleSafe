<template>
    <AppShell>
        <Head title="Policy notices · HustleSafe" />

        <div class="mx-auto w-full max-w-2xl space-y-6 px-1 sm:px-0">
            <div>
                <BackChevronLink :href="route('dashboard')" aria-label="Back to dashboard" />
                <p class="mt-4 text-xs font-bold uppercase tracking-[0.2em] text-amber-700">
                    Trust &amp; safety
                </p>
                <h1 class="font-display mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    Policy notices
                </h1>
                <p class="mt-2 text-sm font-medium leading-relaxed text-slate-600">
                    Official warnings and flags from HustleSafe staff about messaging, conduct, or platform rules.
                </p>
            </div>

            <section v-if="pending.length" class="space-y-3">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-rose-700">
                    Needs your attention · {{ pending.length }}
                </p>
                <article
                    v-for="notice in pending"
                    :key="`${notice.source}-${notice.id}`"
                    class="rounded-2xl border border-amber-200/90 bg-gradient-to-br from-amber-50 via-white to-rose-50/40 p-5 shadow-sm ring-1 ring-amber-100"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wide text-amber-900">{{ notice.reason_label }}</p>
                            <h2 class="mt-1 font-display text-lg font-black text-slate-950">{{ notice.title }}</h2>
                        </div>
                        <span class="rounded-full bg-rose-100 px-2.5 py-1 text-[10px] font-black uppercase text-rose-800">New</span>
                    </div>
                    <p class="mt-3 whitespace-pre-wrap text-sm font-semibold leading-relaxed text-slate-800">{{ notice.body }}</p>
                    <p v-if="notice.quest_title" class="mt-2 text-xs font-bold text-slate-500">
                        Related quest: {{ notice.quest_title }}
                        <span v-if="notice.quest_reference"> · {{ notice.quest_reference }}</span>
                    </p>
                    <p class="mt-2 text-[11px] font-semibold text-slate-500">
                        {{ notice.issued_by ? `From ${notice.issued_by}` : 'From HustleSafe team' }}
                        · {{ formatWhen(notice.issued_at) }}
                    </p>
                    <button
                        type="button"
                        class="mt-4 inline-flex w-full items-center justify-center rounded-full bg-slate-900 px-5 py-3 text-xs font-black uppercase tracking-wide text-white hover:bg-slate-800 disabled:opacity-60 sm:w-auto"
                        :disabled="busyKey === noticeKey(notice)"
                        @click="acknowledge(notice)"
                    >
                        <ReLoader4Line v-if="busyKey === noticeKey(notice)" class="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                        Got it
                    </button>
                </article>
            </section>

            <p
                v-else
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-10 text-center text-sm font-semibold text-slate-600"
            >
                No open policy notices — keep all communication and payments on HustleSafe.
            </p>

            <section v-if="history.length" class="space-y-3">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Previously acknowledged</p>
                <article
                    v-for="notice in history"
                    :key="`history-${notice.source}-${notice.id}`"
                    class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-100"
                >
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ notice.reason_label }}</p>
                    <h3 class="mt-1 text-sm font-black text-slate-900">{{ notice.title }}</h3>
                    <p class="mt-2 line-clamp-3 text-sm font-semibold text-slate-600">{{ notice.body }}</p>
                    <p class="mt-2 text-[11px] font-semibold text-slate-400">
                        Acknowledged {{ formatWhen(notice.acknowledged_at) }}
                    </p>
                </article>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const props = defineProps({
    pending: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    pending_count: { type: Number, default: 0 },
});

const pending = ref([...(props.pending || [])]);
const history = ref([...(props.history || [])]);
const busyKey = ref('');

onMounted(() => {
    window.dispatchEvent(new CustomEvent('app:notifications-changed'));
});

function noticeKey(notice) {
    return `${notice.source}-${notice.id}`;
}

async function acknowledge(notice) {
    const key = noticeKey(notice);
    if (busyKey.value === key) {
        return;
    }
    busyKey.value = key;
    try {
        const { data } = await axios.post(route('account.policy-notices.acknowledge', {
            source: notice.source,
            id: notice.id,
        }));
        pending.value = data.pending || [];
        history.value = data.history || [];
        window.dispatchEvent(new CustomEvent('app:notifications-changed'));
    } finally {
        busyKey.value = '';
    }
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}
</script>
