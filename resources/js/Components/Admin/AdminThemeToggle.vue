<template>
    <button
        type="button"
        class="inline-flex items-center justify-center rounded-xl border text-xs font-bold transition"
        :class="[compact ? 'h-10 w-10 p-0' : 'gap-2 px-3 py-2', shell.btnGhost]"
        :aria-pressed="isDark"
        :title="compact ? toggleLabel : undefined"
        @click="toggleTheme"
    >
        <SunIcon v-if="isDark" class="h-5 w-5 shrink-0" aria-hidden="true" />
        <MoonIcon v-else class="h-5 w-5 shrink-0" aria-hidden="true" />
        <span v-if="!compact" class="hidden sm:inline">{{ toggleLabel }}</span>
    </button>
</template>

<script setup>
import { MoonIcon, SunIcon } from '@heroicons/vue/24/outline';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { computed } from 'vue';

defineProps({
    compact: { type: Boolean, default: false },
});

const { isDark, toggleTheme, shell } = useInjectedAdminTheme();

const toggleLabel = computed(() => (isDark.value ? 'Light mode' : 'Dark mode'));
</script>
