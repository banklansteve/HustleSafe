import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Open staff/admin alert via JSON and navigate with Inertia (no full document reload).
 */
export function useStaffNotificationVisit(openRouteName) {
    const busyId = ref(null);

    async function visit(notificationId) {
        if (!notificationId) {
            return;
        }
        busyId.value = notificationId;
        try {
            const { data } = await axios.get(route(openRouteName, notificationId), {
                headers: { Accept: 'application/json' },
            });
            const target = typeof data?.redirect === 'string' && data.redirect !== ''
                ? data.redirect
                : route('admin.alerts.index');
            await router.visit(target, { preserveScroll: true, preserveState: true });
            window.dispatchEvent(new CustomEvent('admin:notifications-changed'));
            window.dispatchEvent(new CustomEvent('operations:notifications-changed'));
        } finally {
            busyId.value = null;
        }
    }

    return { busyId, visit };
}
