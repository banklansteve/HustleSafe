<template>
    <component :is="tag" :class="rootClass">
        <img
            :src="src"
            :alt="alt"
            class="h-full w-full object-contain object-left"
            :class="imgClass"
            decoding="async"
        />
    </component>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'lockup',
        validator: (value) => ['icon', 'lockup', 'banner', 'svg'].includes(value),
    },
    theme: {
        type: String,
        default: 'light',
        validator: (value) => ['light', 'dark'].includes(value),
    },
    tag: { type: String, default: 'span' },
    alt: { type: String, default: 'HustleSafe' },
    iconClass: { type: String, default: 'h-10 w-10' },
    lockupClass: { type: String, default: 'h-9 w-auto max-w-[10.5rem]' },
    bannerClass: { type: String, default: 'h-10 w-auto max-w-[12rem]' },
    svgClass: { type: String, default: 'h-9 w-auto max-w-[11rem]' },
});

const src = computed(() => {
    const isDark = props.theme === 'dark';

    if (props.variant === 'svg') {
        return isDark ? '/images/logo/v7b_lockup_dark.svg' : '/images/logo/v7b_lockup_light.svg';
    }
    if (props.variant === 'banner') {
        return isDark ? '/images/logo/v7b_banner_dark.png' : '/images/logo/v7b_banner_light.png';
    }
    if (props.variant === 'icon') {
        return isDark ? '/images/logo/v7b_icon_dark.svg' : '/images/logo/v7b_icon_light.svg';
    }

    return isDark ? '/images/logo/v7b_lockup_dark.svg' : '/images/logo/v7b_lockup_light.svg';
});

const imgClass = computed(() => {
    if (props.variant === 'icon') {
        return props.iconClass;
    }
    if (props.variant === 'banner') {
        return props.bannerClass;
    }
    if (props.variant === 'svg') {
        return props.svgClass;
    }

    return props.lockupClass;
});

const rootClass = computed(() => {
    if (props.variant === 'icon') {
        return `inline-flex shrink-0 ${props.iconClass}`;
    }

    return 'inline-flex shrink-0 max-w-full';
});
</script>
