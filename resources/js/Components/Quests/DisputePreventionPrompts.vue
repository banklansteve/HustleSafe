<template>
    <div v-if="prompts.length" class="space-y-2">
        <article
            v-for="prompt in prompts"
            :key="prompt.key"
            class="rounded-xl border px-3 py-3 text-sm leading-6"
            :class="toneClass(prompt.tone)"
        >
            <p class="font-black text-slate-900">{{ prompt.title }}</p>
            <p class="mt-1 font-semibold">{{ prompt.body }}</p>
        </article>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    prompts: { type: Array, default: () => [] },
});

const prompts = computed(() => props.prompts ?? []);

function toneClass(tone) {
    if (tone === 'amber') return 'border-amber-200 bg-amber-50 text-amber-950';
    if (tone === 'sky') return 'border-sky-200 bg-sky-50 text-sky-950';
    return 'border-emerald-200 bg-emerald-50 text-emerald-950';
}
</script>
