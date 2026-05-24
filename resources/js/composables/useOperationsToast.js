import { ref } from 'vue';

const toasts = ref([]);

let nextId = 1;

export function useOperationsToast() {
    function toast(message, type = 'success') {
        const id = nextId++;
        toasts.value = [...toasts.value, { id, message, type }];
        window.setTimeout(() => dismiss(id), 4500);
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
