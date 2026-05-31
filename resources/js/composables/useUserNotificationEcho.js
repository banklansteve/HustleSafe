import { ensureEcho, safeEchoLeave } from '@/utils/ensureEcho';
import { onBeforeUnmount, unref, watch } from 'vue';

/**
 * Subscribe to real-time inbox updates for the authenticated user.
 */
export function useUserNotificationEcho(userId, broadcastConfig, onUpdate) {
    let channelName = null;
    let stopWatch = null;

    function subscribe(id, config) {
        if (channelName) {
            safeEchoLeave(channelName);
            channelName = null;
        }

        if (!id || typeof onUpdate !== 'function') {
            return;
        }

        const echo = ensureEcho(unref(config) ?? null);
        if (!echo) {
            return;
        }

        channelName = `App.Models.User.${id}`;
        echo.private(channelName).listen('.inbox.notification.created', () => {
            onUpdate();
            window.dispatchEvent(new CustomEvent('app:notifications-changed'));
        });
    }

    stopWatch = watch(
        () => [unref(userId), unref(broadcastConfig)],
        ([id, config]) => subscribe(id, config),
        { immediate: true },
    );

    onBeforeUnmount(() => {
        stopWatch?.();
        if (channelName) {
            safeEchoLeave(channelName);
        }
    });
}
