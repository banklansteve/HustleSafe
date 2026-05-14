<template>
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-200/90 bg-white/90 p-4 shadow-sm shadow-slate-900/5 ring-1 ring-slate-100 backdrop-blur-sm sm:flex-row sm:items-center sm:gap-4 sm:p-4"
    >
        <div class="relative min-w-0 flex-1">
            <MagnifyingGlassIcon
                class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
            />
            <input
                :value="search"
                type="search"
                autocomplete="off"
                :placeholder="placeholder"
                class="w-full rounded-xl border border-slate-200 bg-slate-50/80 py-3 pl-11 pr-4 text-sm font-semibold text-slate-900 shadow-inner shadow-slate-900/5 transition placeholder:text-slate-400 focus:border-primary-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/25"
                @input="$emit('update:search', $event.target.value)"
            />
        </div>
        <div class="flex shrink-0 items-center gap-2 sm:min-w-[12rem]">
            <ArrowsUpDownIcon class="hidden h-4 w-4 shrink-0 text-slate-400 sm:block" aria-hidden="true" />
            <UiSelect
                class="w-full"
                :model-value="sort"
                :options="sortOptions"
                placeholder="Sort…"
                @update:model-value="$emit('update:sort', $event)"
            />
        </div>
    </div>
</template>

<script setup>
import UiSelect from '@/Components/Ui/UiSelect.vue';
import { ArrowsUpDownIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

defineProps({
    search: {
        type: String,
        default: '',
    },
    sort: {
        type: String,
        required: true,
    },
    sortOptions: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Search…',
    },
});

defineEmits(['update:search', 'update:sort']);
</script>
