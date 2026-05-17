<template>
    <AdminShell
        title="Environment snapshot"
        subtitle="Non-secret runtime flags for war-room debugging. Values are read-only here — change them in .env / deployment config."
    >
        <AdminPanel eyebrow="Exports" title="Data interchange">
            <template #actions>
                <a
                    :href="route('admin.settings.export')"
                    class="inline-flex rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide"
                    :class="shell.btnPrimary"
                >
                    Export CSV
                </a>
            </template>
            <p class="text-sm font-semibold" :class="shell.cardMuted">
                Import is disabled in this console — update environment variables in deployment config instead.
            </p>
        </AdminPanel>

        <div class="grid gap-2 lg:grid-cols-2">
            <AdminPanel eyebrow="Runtime" title="Application">
                <dl class="space-y-3 text-sm">
                    <div
                        v-for="(v, k) in app"
                        :key="k"
                        class="flex justify-between gap-4 border-b pb-2"
                        :class="shell.tableDivide"
                    >
                        <dt class="font-mono text-xs font-bold" :class="shell.cardMuted">{{ k }}</dt>
                        <dd class="text-right font-semibold break-all" :class="shell.cardTitle">{{ display(v) }}</dd>
                    </div>
                </dl>
            </AdminPanel>

            <AdminPanel eyebrow="Delivery" title="Mail">
                <dl class="space-y-3 text-sm">
                    <div
                        v-for="(v, k) in mail"
                        :key="k"
                        class="flex justify-between gap-4 border-b pb-2"
                        :class="shell.tableDivide"
                    >
                        <dt class="font-mono text-xs font-bold" :class="shell.cardMuted">{{ k }}</dt>
                        <dd class="text-right font-semibold break-all" :class="shell.cardTitle">{{ display(v) }}</dd>
                    </div>
                </dl>
            </AdminPanel>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';

defineProps({
    app: { type: Object, required: true },
    mail: { type: Object, required: true },
});

const { shell } = useInjectedAdminTheme();

function display(v) {
    if (typeof v === 'boolean') {
        return v ? 'true' : 'false';
    }
    if (v === null || v === undefined || v === '') {
        return '—';
    }

    return String(v);
}
</script>
