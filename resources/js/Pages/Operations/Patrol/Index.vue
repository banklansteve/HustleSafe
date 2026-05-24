<template>
    <OperationsShell title="Content quality patrol" subtitle="Sample live Quests and proposals for proactive review.">
        <section class="mb-4 rounded-2xl border border-primary-100 bg-primary-50/50 p-4">
            <p class="text-xs font-black uppercase text-primary-800">Start patrol session</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <select v-model="sessionForm.content_type" class="form-input"><option value="quests">Quests</option><option value="proposals">Proposals</option></select>
                <select v-model="sessionForm.category_id" class="form-input"><option value="">All categories</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                <input v-model="sessionForm.date_from" type="date" class="form-input" />
                <input v-model="sessionForm.date_to" type="date" class="form-input" />
                <input v-model.number="sessionForm.sample_size" type="number" min="5" max="100" class="form-input" placeholder="Sample size" />
            </div>
            <button type="button" class="mt-3 rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white" :disabled="busy.start" @click="startSession">Start session</button>
        </section>

        <div v-if="activeSession" class="mb-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-black text-slate-900">Progress {{ activeSession.progress_percent }}%</p>
                <span class="text-xs font-semibold text-slate-500">{{ activeSession.session.reviewed_count }}/{{ activeSession.session.sample_size }}</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-primary-600 transition-all" :style="{ width: `${activeSession.progress_percent}%` }" /></div>
        </div>

        <article v-if="currentItem" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
            <p class="text-[10px] font-black uppercase text-slate-500">{{ currentItem.reviewable_type }}</p>
            <h3 class="mt-1 font-display text-xl font-black text-slate-950">{{ currentItem.title }}</h3>
            <p class="mt-2 text-sm font-semibold text-slate-700">{{ currentItem.excerpt }}</p>
            <div v-if="currentItem.risk_signals?.length" class="mt-3 flex flex-wrap gap-2">
                <span v-for="sig in currentItem.risk_signals" :key="sig" class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-black uppercase text-amber-900">{{ sig }}</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button v-for="d in decisions" :key="d.key" type="button" class="rounded-xl py-3 text-xs font-black uppercase" :class="d.class" :disabled="busy.decide" @click="decide(d.key)">{{ d.label }}</button>
            </div>
        </article>
        <p v-else-if="activeSession" class="text-center text-sm font-bold text-emerald-700">Patrol session complete.</p>
    </OperationsShell>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const categories = ref([]);
const sessionForm = reactive({ content_type: 'quests', category_id: '', date_from: '', date_to: '', sample_size: 25 });
const activeSession = ref(null);
const currentItem = ref(null);
const { busy, runAction } = useOperationsAction();
const decisions = [
    { key: 'clear', label: 'Clear', class: 'bg-emerald-600 text-white' },
    { key: 'flag', label: 'Flag', class: 'bg-amber-500 text-white' },
    { key: 'contact', label: 'Contact', class: 'bg-primary-700 text-white' },
    { key: 'escalate', label: 'Escalate', class: 'bg-rose-700 text-white' },
];

onMounted(async () => {
    const { data } = await window.axios.get(route('operations.api.patrol.categories'));
    categories.value = data.categories ?? [];
});

async function startSession() {
    await runAction('start', async () => {
        const res = await window.axios.post(route('operations.api.patrol.sessions.start'), sessionForm);
        await loadSession(res.data.session_id);
        return res;
    }, 'Patrol started.');
}

async function loadSession(id) {
    const { data } = await window.axios.get(route('operations.api.patrol.sessions.detail', id));
    activeSession.value = data;
    currentItem.value = data.current_item;
}

async function decide(decision) {
    if (!currentItem.value) return;
    await runAction('decide', async () => {
        const res = await window.axios.post(route('operations.api.patrol.items.decide', currentItem.value.id), { decision, notes: '' });
        await loadSession(activeSession.value.session.id);
        return res;
    }, 'Saved.');
}
</script>
