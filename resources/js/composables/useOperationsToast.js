import { FLASH_TOAST_MS } from '@/composables/useFlashToast';
import { ref } from 'vue';

const toasts = ref([]);

let nextId = 1;
let hideTimer = null;

export function useOperationsToast() {
    function toast(message, type = 'success') {
        if (!message) {
            return;
        }

        const id = nextId++;
        toasts.value = [{ id, message, type }];
        window.clearTimeout(hideTimer);
        hideTimer = window.setTimeout(() => dismiss(id), FLASH_TOAST_MS);
    }

    function dismiss(id) {
        toasts.value = toasts.value.filter((item) => item.id !== id);
    }

    return {
        toasts,
        toast,
        dismiss,
    };
}
