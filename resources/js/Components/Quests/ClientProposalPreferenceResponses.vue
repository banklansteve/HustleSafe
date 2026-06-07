<template>
    <section
        v-if="responses?.length"
        class="space-y-4 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6"
    >
        <div>
            <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                Freelancer's response to your preferences
            </h2>
            <p class="mt-1 text-xs font-semibold text-slate-500">
                How this freelancer addressed the preferences you set on the quest.
            </p>
        </div>

        <ul class="space-y-4">
            <li
                v-for="row in responses"
                :key="row.key"
                class="rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-3 ring-1 ring-slate-100"
            >
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                    Your preference
                </p>
                <p class="mt-1 text-sm font-bold text-slate-900">
                    {{ row.label }}: {{ row.client_value }}
                </p>
                <p class="mt-3 text-[10px] font-black uppercase tracking-wide text-slate-500">
                    Freelancer's response
                </p>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm font-black" :class="badgeClass(row.response_type)">
                    <span>{{ badgeIcon(row.response_type) }}</span>
                    <span>{{ row.response_label }}</span>
                </p>
                <p v-if="row.response_text" class="mt-2 whitespace-pre-wrap text-sm font-medium leading-relaxed text-slate-700">
                    {{ row.response_text }}
                </p>
            </li>
        </ul>
    </section>
</template>

<script setup>
defineProps({
    responses: { type: Array, default: () => [] },
});

function badgeIcon(type) {
    return {
        accept: '✓',
        propose_alternative: '⚠',
        clarify: '?',
        custom: '○',
    }[type] || '○';
}

function badgeClass(type) {
    return {
        accept: 'text-emerald-700',
        propose_alternative: 'text-amber-700',
        clarify: 'text-sky-700',
        custom: 'text-slate-600',
    }[type] || 'text-slate-500';
}
</script>
