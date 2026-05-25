/**
 * Keeps server-side "viewing this thread" presence warm while a chat surface is open.
 */
export function useMessagingViewPresence(markReadFn) {
    let heartbeatTimer = null;

    function markNow() {
        if (typeof markReadFn !== 'function') {
            return;
        }

        void Promise.resolve()
            .then(() => markReadFn())
            .catch(() => {
                /* read is best-effort */
            });
    }

    function start({ immediate = false } = {}) {
        stop();
        if (immediate) {
            markNow();
        }
        heartbeatTimer = setInterval(markNow, 60_000);
    }

    function stop() {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    }

    return { start, stop, markNow };
}
