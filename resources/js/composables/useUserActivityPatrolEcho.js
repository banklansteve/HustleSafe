import { ensureEcho, safeEchoLeave } from '@/utils/ensureEcho';
import { onBeforeUnmount, unref, watch } from 'vue';

/**
 * Real-time user activity patrol updates for staff/super admin (WebSocket only).
 */
export function useUserActivityPatrolEcho(broadcastConfig, onPatrolChanged) {
    let channelName = null;
    let stopWatch = null;

    function subscribe(config) {
        if (channelName) {
            safeEchoLeave(channelName);
            channelName = null;
        }

        if (typeof onPatrolChanged !== 'function') {
            return;
        }

        const echo = ensureEcho(unref(config) ?? null);
        if (!echo) {
            return;
        }

        channelName = 'user-activity-patrol.staff';
        echo.private(channelName).listen('.patrol.changed', (event) => {
            onPatrolChanged(event);
        });
    }

    stopWatch = watch(
        () => unref(broadcastConfig),
        (config) => subscribe(config),
        { immediate: true },
    );

    onBeforeUnmount(() => {
        stopWatch?.();
        if (channelName) {
            safeEchoLeave(channelName);
        }
    });
}
