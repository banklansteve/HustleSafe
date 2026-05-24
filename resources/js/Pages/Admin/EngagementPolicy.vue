<template>
    <AdminShell
        title="Engagement & auto-complete"
        subtitle="Support and ops readout — mirrors automation in QuestEngagementLifecycleService and the hourly quests:process-lifecycle schedule."
    >
        <div class="mb-6 flex flex-wrap gap-2 rounded-2xl border p-4" :class="shell.card">
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
            <section v-for="(block, key) in sections" :key="key" class="rounded-2xl border p-5" :class="shell.card">
                <h2 class="font-display text-lg font-bold capitalize" :class="shell.cardTitle">
                    {{ humanKey(String(key)) }}
                </h2>
                <div class="mt-4 space-y-3 text-sm font-semibold leading-relaxed" :class="shell.cardBody">
                    <template v-if="isStringRecord(block)">
                        <p v-for="(txt, k) in block" :key="k" class="rounded-xl border px-3 py-2" :class="shell.card">
                            <span class="text-xs font-black uppercase tracking-wide text-primary-700 dark:text-teal-400/90">{{ humanKey(String(k)) }}</span>
                            <span class="mt-1 block" :class="shell.tableRow">{{ txt }}</span>
                        </p>
                    </template>
                    <template v-else-if="Array.isArray(block)">
                        <ul class="list-disc space-y-2 pl-5">
                            <li v-for="(item, idx) in block" :key="idx">{{ item }}</li>
                        </ul>
                    </template>
                    <template v-else>
                        <pre class="overflow-x-auto rounded-xl border p-4 text-xs" :class="[shell.card, shell.cardMuted]">{{ JSON.stringify(block, null, 2) }}</pre>
                    </template>
                </div>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';

const { shell } = useInjectedAdminTheme();

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
