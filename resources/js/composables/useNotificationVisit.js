import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Mark notification read via JSON and navigate with Inertia (avoids full document round-trips).
 */
export function useNotificationVisit() {
    const busyId = ref(null);

    async function visit(id, alsoCsv = null) {
        busyId.value = id;
        try {
            const suffix =
                typeof alsoCsv === 'string' && alsoCsv.length > 0
                    ? `?also=${encodeURIComponent(alsoCsv)}`
                    : '';
            const { data } = await axios.get(`${route('notifications.read', id)}${suffix}`, {
                headers: { Accept: 'application/json' },
            });
            const target = typeof data?.redirect === 'string' && data.redirect !== '' ? data.redirect : route('dashboard');
            await router.visit(target, { preserveScroll: true });
        } finally {
            busyId.value = null;
        }
    }

    return { busyId, visit };
}
