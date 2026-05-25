import { onMounted, onBeforeUnmount, watch } from 'vue';

const ICON_LIGHT = '/images/logo/v7b_icon_light.svg';
const ICON_DARK = '/images/logo/v7b_icon_dark.svg';

/**
 * Keep the browser tab icon on the lone H mark; swap light/dark with theme.
 *
 * @param {import('vue').Ref<string>|import('vue').ComputedRef<string>} themeRef 'light' | 'dark'
 */
export function useBrandFavicon(themeRef) {
    let stop = null;

    function apply(theme) {
        if (typeof document === 'undefined') {
            return;
        }

        const href = theme === 'dark' ? ICON_DARK : ICON_LIGHT;
        document.querySelectorAll('link[data-brand-favicon]').forEach((node) => {
            node.setAttribute('href', href);
        });
    }

    onMounted(() => {
        stop = watch(themeRef, (theme) => apply(theme), { immediate: true });
    });

    onBeforeUnmount(() => {
        stop?.();
    });
}
