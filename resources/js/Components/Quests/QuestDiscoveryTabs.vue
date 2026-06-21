<template>
    <nav
        class="inline-flex w-full max-w-md rounded-2xl border border-white/20 bg-white/10 p-1 backdrop-blur-sm sm:w-auto"
        aria-label="Quest discovery"
    >
        <Link
            :href="route('quests.explore')"
            prefetch="false"
            preserve-scroll
            class="flex-1 rounded-xl px-4 py-2.5 text-center text-xs font-black uppercase tracking-wide transition sm:flex-none sm:px-5 sm:text-sm"
            :class="matchedActive ? 'bg-white text-primary-900 shadow-sm' : 'text-white/85 hover:bg-white/10 hover:text-white'"
        >
            Matched quests
        </Link>
        <Link
            :href="route('quests.browse')"
            prefetch="false"
            preserve-scroll
            class="flex-1 rounded-xl px-4 py-2.5 text-center text-xs font-black uppercase tracking-wide transition sm:flex-none sm:px-5 sm:text-sm"
            :class="browseActive ? 'bg-white text-primary-900 shadow-sm' : 'text-white/85 hover:bg-white/10 hover:text-white'"
        >
            Browse quests
        </Link>
    </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePathname } from '@/composables/usePathname';

const page = usePage();
const pathname = usePathname(page);

const browseActive = computed(() => pathname.value.startsWith('/quests/browse'));
const matchedActive = computed(() => pathname.value.startsWith('/quests/explore'));
</script>
