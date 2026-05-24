/**
 * Keeps server-side "viewing this thread" presence warm while a chat surface is open.
 */
export function useMessagingViewPresence(markReadFn) {
    let heartbeatTimer = null;

    function markNow() {
        if (typeof markReadFn === 'function') {
            void markReadFn();
        }
    }

    function start() {
        stop();
        markNow();
        heartbeatTimer = setInterval(markNow, 45000);
    }

    function stop() {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    }

    return { start, stop, markNow };
}
