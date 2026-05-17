<template>
    <div class="flex flex-wrap items-center gap-2">
        <a
            v-for="action in exportActions"
            :key="action.href"
            :href="action.href"
            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-black uppercase tracking-wide transition"
            :class="shell.btnPrimary"
        >
            {{ action.label }}
        </a>
        <button
            v-for="action in buttonActions"
            :key="action.key"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-black uppercase tracking-wide transition disabled:opacity-40"
            :class="action.variant === 'primary' ? shell.btnPrimary : shell.btnGhost"
            :disabled="action.disabled"
            @click="action.onClick?.()"
        >
            {{ action.label }}
        </button>
        <label
            v-if="importRoute"
            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-black uppercase tracking-wide transition"
            :class="shell.btnGhost"
        >
            <span>{{ importLabel }}</span>
            <input type="file" accept=".csv,.txt" class="sr-only" @change="onImportFile" />
        </label>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';

const props = defineProps({
    exportActions: { type: Array, default: () => [] },
    buttonActions: { type: Array, default: () => [] },
    importRoute: { type: String, default: '' },
    importLabel: { type: String, default: 'Import CSV' },
});

const { shell } = useInjectedAdminTheme();

function onImportFile(e) {
    const file = e.target.files?.[0];
    if (!file || !props.importRoute) {
        return;
    }
    router.post(props.importRoute, { file }, { forceFormData: true, preserveScroll: true });
    e.target.value = '';
}
</script>
