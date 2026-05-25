import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, watch } from 'vue';

/** Shared flash-toast duration across AppShell, AdminShell, and operations toasts. */
export const FLASH_TOAST_MS = 8000;

/**
 * Show a session flash message at most once per Inertia flash token.
 *
 * @param {(message: string) => void} show
 */
export function useFlashToastWatcher(show) {
    const page = usePage();
    let lastToken = null;

    watch(
        () => page.props.flash?.token,
        (token) => {
            if (!token || token === lastToken) {
                return;
            }

            lastToken = token;

            const message = page.props.flash?.success ?? page.props.flash?.status;
            if (message) {
                show(String(message));
            }
        },
        { flush: 'post' },
    );
}

/**
 * @param {import('vue').Ref<boolean>} visible
 * @param {import('vue').Ref<string>} message
 */
export function useToastAutoHide(visible, message) {
    let hideTimer = null;

    function dismiss() {
        visible.value = false;
        if (hideTimer) {
            window.clearTimeout(hideTimer);
            hideTimer = null;
        }
    }

    function present(text) {
        if (!text) {
            return;
        }

        if (visible.value && message.value === text) {
            window.clearTimeout(hideTimer);
            hideTimer = window.setTimeout(dismiss, FLASH_TOAST_MS);

            return;
        }

        message.value = text;
        visible.value = true;
        window.clearTimeout(hideTimer);
        hideTimer = window.setTimeout(dismiss, FLASH_TOAST_MS);
    }

    onBeforeUnmount(() => {
        if (hideTimer) {
            window.clearTimeout(hideTimer);
        }
    });

    return { present, dismiss };
}
