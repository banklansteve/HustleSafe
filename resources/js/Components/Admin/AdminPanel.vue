<template>
    <section class="rounded-2xl border p-4 sm:p-5" :class="shell.card">
        <header v-if="title || $slots.actions" class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div v-if="title">
                <p v-if="eyebrow" class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">
                    {{ eyebrow }}
                </p>
                <h2 class="font-display text-lg font-bold" :class="shell.cardTitle">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm font-semibold" :class="shell.cardMuted">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </header>
        <slot />
    </section>
</template>

<script setup>
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';

defineProps({
    title: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    description: { type: String, default: '' },
});

const { shell } = useInjectedAdminTheme();
</script>
