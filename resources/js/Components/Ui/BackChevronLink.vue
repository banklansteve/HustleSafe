<template>
    <Link
        :href="href"
        class="group inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary-600 text-white shadow-md shadow-primary-900/20 ring-1 ring-white/15 transition duration-200 ease-out hover:bg-primary-700 hover:shadow-lg hover:ring-primary-500/30 active:scale-[0.97] focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:ring-offset-2"
        :aria-label="ariaLabel"
    >
        <ChevronLeftIcon class="h-5 w-5 transition-transform duration-200 ease-out group-hover:-translate-x-0.5" aria-hidden="true" />
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeftIcon } from '@heroicons/vue/24/solid';

defineProps({
    href: {
        type: String,
        required: true,
    },
    ariaLabel: {
        type: String,
        default: 'Go back',
    },
});
</script>
