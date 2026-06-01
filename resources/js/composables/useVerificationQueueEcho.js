import { ensureEcho, safeEchoLeave } from '@/utils/ensureEcho';
import { onBeforeUnmount, unref, watch } from 'vue';

/**
 * Real-time verification queue updates for staff admins.
 */
export function useVerificationQueueEcho(broadcastConfig, onQueueChanged) {
    let channelName = null;
    let stopWatch = null;

    function subscribe(config) {
        if (channelName) {
            safeEchoLeave(channelName);
            channelName = null;
        }

        if (typeof onQueueChanged !== 'function') {
            return;
        }

        const echo = ensureEcho(unref(config) ?? null);
        if (!echo) {
            return;
        }

        channelName = 'verification.staff';
        echo.private(channelName).listen('.queue.changed', (event) => {
            onQueueChanged(event);
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
