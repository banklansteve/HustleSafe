<template>
    <AdminShell
        title="Engagement & auto-complete"
        subtitle="Support and ops readout — mirrors automation in QuestEngagementLifecycleService and the hourly quests:process-lifecycle schedule."
    >
        <div class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-white/10 bg-slate-900/40 p-4 ring-1 ring-white/5">
            <a
                :href="route('admin.engagement-policy.export')"
                class="inline-flex rounded-xl bg-teal-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 shadow-lg shadow-teal-900/30 transition hover:bg-teal-400"
            >
                Download Markdown
            </a>
            <span class="inline-flex items-center rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-slate-600 line-through opacity-60" title="Policy is versioned in config, not imported via CSV">
                Import
            </span>
        </div>

        <div class="space-y-6">
            <section v-for="(block, key) in sections" :key="key" class="rounded-2xl border border-white/10 bg-slate-900/50 p-5 ring-1 ring-white/5">
                <h2 class="font-display text-lg font-bold capitalize text-white">
                    {{ humanKey(String(key)) }}
                </h2>
                <div class="mt-4 space-y-3 text-sm font-semibold leading-relaxed text-slate-300">
                    <template v-if="isStringRecord(block)">
                        <p v-for="(txt, k) in block" :key="k" class="rounded-xl bg-slate-950/60 px-3 py-2 ring-1 ring-white/5">
                            <span class="text-xs font-black uppercase tracking-wide text-teal-400/90">{{ humanKey(String(k)) }}</span>
                            <span class="mt-1 block text-slate-200">{{ txt }}</span>
                        </p>
                    </template>
                    <template v-else-if="Array.isArray(block)">
                        <ul class="list-disc space-y-2 pl-5">
                            <li v-for="(item, idx) in block" :key="idx">{{ item }}</li>
                        </ul>
                    </template>
                    <template v-else>
                        <pre class="overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-300 ring-1 ring-white/10">{{ JSON.stringify(block, null, 2) }}</pre>
                    </template>
                </div>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';

const props = defineProps({
    policy: { type: Object, required: true },
});

const sections = props.policy;

function humanKey(k) {
    return k.split('_').join(' ');
}

function isStringRecord(obj) {
    if (!obj || typeof obj !== 'object' || Array.isArray(obj)) {
        return false;
    }
    return Object.values(obj).every((v) => typeof v === 'string');
}
</script>
