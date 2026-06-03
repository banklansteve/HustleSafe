import { ensureEcho, safeEchoLeave } from '@/utils/ensureEcho';
import { onBeforeUnmount, unref, watch } from 'vue';

/**
 * Real-time verification queue updates for staff admins.
 */
export function useVerificationQueueEcho(broadcastConfig, onQueueChanged) {
    let channelName = null;
    let stopWatch = null;
    let pollTimer = null;

    function stopPolling() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function startPolling() {
        if (pollTimer || typeof onQueueChanged !== 'function') {
            return;
        }

        // Fallback when WebSocket config/connection is unavailable.
        // Keeps queue UX near-real-time without manual refresh.
        pollTimer = window.setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }
            onQueueChanged({ source: 'poll' });
        }, 5000);
    }

    function subscribe(config) {
        if (channelName) {
            safeEchoLeave(channelName);
            channelName = null;
        }
        stopPolling();

        if (typeof onQueueChanged !== 'function') {
            return;
        }

        const echo = ensureEcho(unref(config) ?? null);
        if (!echo) {
            startPolling();
            return;
        }

        channelName = 'verification.staff';
        echo.private(channelName).listen('.queue.changed', (event) => {
            onQueueChanged(event);
        });

        // Keep a light fallback poll even with Echo. If socket drops silently,
        // staff queue still updates without requiring a full page refresh.
        startPolling();
    }

    stopWatch = watch(
        () => unref(broadcastConfig),
        (config) => subscribe(config),
        { immediate: true },
    );

    onBeforeUnmount(() => {
        stopWatch?.();
        stopPolling();
        if (channelName) {
            safeEchoLeave(channelName);
        }
    });
}
