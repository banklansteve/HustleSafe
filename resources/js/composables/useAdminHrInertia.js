import { useOperationsToast } from '@/composables/useOperationsToast';

function firstValidationMessage(errors) {
    const first = Object.values(errors || {})[0];

    return Array.isArray(first) ? first[0] : (first || '');
}

export function useAdminHrInertia() {
    const { toast } = useOperationsToast();

    function inertiaOptions(messages = {}, callbacks = {}) {
        return {
            ...callbacks,
            onSuccess: (page) => {
                const flashMessage = page?.props?.flash?.success ?? page?.props?.flash?.status;
                const message = flashMessage || messages.success;
                if (message) {
                    toast(message);
                }
                callbacks.onSuccess?.(page);
            },
            onError: (errors) => {
                toast(firstValidationMessage(errors) || messages.error || 'Action failed.', 'error');
                callbacks.onError?.(errors);
            },
        };
    }

    return { toast, inertiaOptions };
}
