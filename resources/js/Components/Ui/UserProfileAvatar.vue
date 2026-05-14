<template>
    <Link
        v-if="href"
        :href="href"
        prefetch="false"
        preserve-scroll
        class="inline-flex shrink-0 overflow-hidden bg-gradient-to-br from-primary-600 to-teal-700 font-black text-white ring-2 ring-slate-100 transition hover:ring-primary-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
        :class="[frameClass, radiusClass]"
        :title="title || 'View profile'"
    >
        <img v-if="src" :src="src" :alt="alt || ''" class="h-full w-full object-cover" />
        <span v-else class="flex h-full w-full items-center justify-center leading-none" :class="initialsTextClass">{{ initialsText }}</span>
    </Link>
    <span
        v-else
        class="inline-flex shrink-0 overflow-hidden bg-gradient-to-br from-primary-600 to-teal-700 font-black text-white ring-2 ring-slate-100"
        :class="[frameClass, radiusClass]"
    >
        <img v-if="src" :src="src" :alt="alt || ''" class="h-full w-full object-cover" />
        <span v-else class="flex h-full w-full items-center justify-center leading-none" :class="initialsTextClass">{{ initialsText }}</span>
    </span>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    href: { type: String, default: null },
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    title: { type: String, default: '' },
    name: { type: String, default: '' },
    frameClass: { type: String, default: 'h-10 w-10' },
    radiusClass: { type: String, default: 'rounded-full' },
    initialsTextClass: { type: String, default: 'text-sm sm:text-base' },
});

const initialsText = computed(() => {
    const n = (props.name || 'H').trim().split(/\s+/);

    return ((n[0]?.[0] || 'H') + (n[1]?.[0] || '')).toUpperCase();
});
</script>
