<template>
    <component
        :is="iconComponent"
        class="shrink-0 text-current"
        :class="sizeClass"
        aria-hidden="true"
    />
</template>

<script setup>
import {
    BanknotesIcon,
    BoltIcon,
    BriefcaseIcon,
    CheckCircleIcon,
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    InboxStackIcon,
    MagnifyingGlassIcon,
    PlusCircleIcon,
    ShieldCheckIcon,
    SparklesIcon,
    Squares2X2Icon,
    StarIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    name: {
        type: String,
        default: 'sparkles',
    },
    size: {
        type: String,
        default: 'md',
    },
});

const map = {
    briefcase: BriefcaseIcon,
    check: CheckCircleIcon,
    banknotes: BanknotesIcon,
    bolt: BoltIcon,
    clipboard: ClipboardDocumentListIcon,
    search: MagnifyingGlassIcon,
    shield: ShieldCheckIcon,
    squares: Squares2X2Icon,
    star: StarIcon,
    plus: PlusCircleIcon,
    inbox: InboxStackIcon,
    cog: Cog6ToothIcon,
    sparkles: SparklesIcon,
};

const iconComponent = computed(() => map[props.name] ?? SparklesIcon);

const sizeClass = computed(() => (props.size === 'sm' ? 'h-4 w-4' : props.size === 'lg' ? 'h-6 w-6' : 'h-5 w-5'));
</script>
