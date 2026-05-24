<template>
    <section class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
        <div class="flex items-center justify-between gap-2">
            <h4 class="text-sm font-black text-slate-950">{{ title }}</h4>
            <span v-if="items.length" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-600">{{ items.length }}</span>
        </div>

        <div v-if="items.length" class="mt-3 space-y-3">
            <article
                v-for="(item, index) in items"
                :key="item.id ?? index"
                class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 text-sm"
            >
                <component
                    :is="item.href ? Link : 'div'"
                    :href="item.href || undefined"
                    class="block"
                    :class="item.href ? 'transition hover:opacity-90' : ''"
                >
                    <p v-if="primaryLine(item)" class="font-bold text-slate-950">{{ primaryLine(item) }}</p>
                    <p v-if="secondaryLine(item)" class="mt-1 whitespace-pre-line text-xs font-semibold text-slate-600">{{ secondaryLine(item) }}</p>
                </component>
                <div v-if="chips(item).length" class="mt-2 flex flex-wrap gap-1.5">
                    <span
                        v-for="chip in chips(item)"
                        :key="chip"
                        class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-600 ring-1 ring-slate-200"
                    >
                        {{ chip }}
                    </span>
                </div>
            </article>
        </div>

        <p v-else class="mt-3 rounded-xl bg-slate-50 px-4 py-6 text-center text-sm font-semibold text-slate-500">
            {{ empty }}
        </p>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    items: { type: Array, default: () => [] },
    empty: { type: String, default: 'No records yet.' },
});

function primaryLine(item) {
    return item.title || item.quest || item.contract || item.body || item.type || item.category;
}

function secondaryLine(item) {
    return item.summary || item.content || item.notes || item.amount || item.value || '';
}

function chips(item) {
    const skip = new Set(['id', 'title', 'summary', 'content', 'notes', 'body', 'href', 'uuid']);
    const preferred = ['status', 'phase', 'direction', 'rating', 'admin', 'client', 'freelancer', 'category', 'occurred_at', 'created_at', 'type'];

    const chipsOut = [];
    for (const key of preferred) {
        const value = item[key];
        if (value !== null && value !== undefined && value !== '' && !skip.has(key)) {
            chipsOut.push(formatChip(key, value));
        }
    }

    return chipsOut.slice(0, 6);
}

function formatChip(key, value) {
    if (key.includes('_at')) {
        try {
            return `${key.replace(/_/g, ' ')}: ${new Date(value).toLocaleString()}`;
        } catch {
            return `${key.replace(/_/g, ' ')}: ${value}`;
        }
    }

    return `${key.replace(/_/g, ' ')}: ${value}`;
}
</script>
