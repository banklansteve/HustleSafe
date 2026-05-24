import { reactive } from 'vue';
import { useOperationsToast } from '@/composables/useOperationsToast';

export function useOperationsAction() {
    const { toast } = useOperationsToast();
    const busy = reactive({});

    async function runAction(key, request, successMessage, after) {
        busy[key] = true;
        try {
            const response = await request();
            toast(response?.data?.message || successMessage);
            if (after) {
                await after(response);
            }
        } catch (error) {
            toast(error?.response?.data?.message || 'Action failed.', 'error');
        } finally {
            busy[key] = false;
        }
    }

    return { busy, runAction, toast };
}
