import { ref } from 'vue';
import { useIntersectionObserver } from '@vueuse/core';

export function useScrollReveal(options = {}) {
    const target = ref(null);
    const isVisible = ref(false);

    useIntersectionObserver(
        target,
        ([entry]) => {
            if (entry?.isIntersecting) {
                isVisible.value = true;
            }
        },
        {
            threshold: options.threshold ?? 0.12,
            rootMargin: options.rootMargin ?? '0px 0px -8% 0px',
        },
    );

    return { target, isVisible };
}
