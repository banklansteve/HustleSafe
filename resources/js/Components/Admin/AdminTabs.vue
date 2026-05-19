<template>
    <div class="relative border-b" :class="borderClass">
        <div class="admin-tabs-scroll flex gap-6 overflow-x-auto" role="tablist" :aria-label="ariaLabel">
            <button
                v-for="tab in tabs"
                :id="`${idPrefix}-${tab.key}`"
                :key="tab.key"
                type="button"
                role="tab"
                class="inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-1 pb-3 pt-2 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                :class="modelValue === tab.key ? activeClass : inactiveClass"
                :aria-selected="modelValue === tab.key"
                :aria-controls="`${idPrefix}-${tab.key}-panel`"
                @click="emit('update:modelValue', tab.key)"
            >
                <component v-if="tab.icon" :is="tab.icon" class="h-4 w-4" aria-hidden="true" />
                <span class="whitespace-nowrap">{{ tab.label }}</span>
            </button>
        </div>
        <div class="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l" :class="fadeClass" aria-hidden="true"></div>
    </div>
</template>

<script setup>
// All admin tab navigation must use AdminTabs + AdminTabPanel. This component is pure UI: no router calls, no URL writes, no server requests.
import { computed } from 'vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';

defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
    ariaLabel: { type: String, default: 'Admin tabs' },
    idPrefix: { type: String, default: 'admin-tab' },
});

const emit = defineEmits(['update:modelValue']);
const { isDark } = useInjectedAdminTheme();

const activeClass = 'border-primary-600 text-primary-700 font-semibold dark:border-primary-400 dark:text-primary-300';
const inactiveClass = 'border-transparent text-slate-500 font-medium opacity-80 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100';
const borderClass = computed(() => (isDark.value ? 'border-slate-700' : 'border-slate-200'));
const fadeClass = computed(() => (isDark.value ? 'from-slate-900 to-transparent' : 'from-slate-100 to-transparent'));
</script>

<style scoped>
.admin-tabs-scroll {
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}

.admin-tabs-scroll::-webkit-scrollbar {
    display: none;
}
</style>
