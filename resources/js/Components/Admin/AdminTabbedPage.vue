<template>
    <div :class="rootClass">
        <AdminTabs v-model="activeTab" :tabs="tabs" :id-prefix="idPrefix" :aria-label="ariaLabel" />
        <slot :active-tab="activeTab" />
    </div>
</template>

<script setup>
import AdminTabs from '@/Components/Admin/AdminTabs.vue';

defineProps({
    tabs: { type: Array, required: true },
    idPrefix: { type: String, required: true },
    ariaLabel: { type: String, default: 'Admin tabs' },
    rootClass: { type: String, default: 'space-y-5' },
});

const activeTab = defineModel({ type: String, required: true });
</script>
