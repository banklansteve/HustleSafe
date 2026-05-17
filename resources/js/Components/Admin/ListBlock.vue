<template>
    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/60">
        <h4 class="font-black text-slate-900 dark:text-white">{{ title }}</h4>
        <div v-if="items.length" class="mt-3 space-y-3">
            <article v-for="(item, index) in items" :key="item.id ?? index" class="rounded-2xl border border-slate-100 p-3 text-sm dark:border-white/10">
                <p v-if="primaryLine(item)" class="font-black text-slate-900 dark:text-white">{{ primaryLine(item) }}</p>
                <p v-if="secondaryLine(item)" class="mt-1 whitespace-pre-line text-xs font-semibold text-slate-500 dark:text-slate-400">{{ secondaryLine(item) }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="chip in chips(item)"
                        :key="chip"
                        class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-slate-600 dark:bg-white/10 dark:text-slate-300"
                    >
                        {{ chip }}
                    </span>
                </div>
            </article>
        </div>
        <p v-else class="mt-3 rounded-2xl bg-slate-50 p-4 text-sm font-semibold text-slate-500 dark:bg-white/5 dark:text-slate-400">
            {{ empty }}
        </p>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    items: { type: Array, default: () => [] },
    empty: { type: String, default: 'No records yet.' },
});

function primaryLine(item) {
    return item.title || item.quest || item.type || item.category || item.subject || item.contract || item.body;
}

function secondaryLine(item) {
    return item.summary || item.content || item.notes || item.body || item.rejection_reason || item.amount || item.value || '';
}

function chips(item) {
    return Object.entries(item)
        .filter(([key, value]) => value !== null && value !== undefined && value !== '' && !['id', 'title', 'summary', 'content', 'notes', 'body'].includes(key))
        .slice(0, 8)
        .map(([key, value]) => `${key.replace(/_/g, ' ')}: ${Array.isArray(value) || typeof value === 'object' ? JSON.stringify(value) : value}`);
}
</script>
