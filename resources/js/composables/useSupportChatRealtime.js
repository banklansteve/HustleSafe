import { onBeforeUnmount, ref } from 'vue';
import { ensureEcho, safeEchoLeave } from '@/utils/ensureEcho';

const WS_WATCHDOG_MS = 5_000;
const POLL_BACKUP_MS = 1000;

function messageIdKey(msg) {
    const id = Number(msg?.id);

    return Number.isFinite(id) && id > 0 ? id : null;
}

function bindPusherConnectionOnce(echo, handlers) {
    const pusher = echo?.connector?.pusher;
    if (!pusher?.connection) {
        return false;
    }

    if (echo.__supportChatPusherBound) {
        return true;
    }

    echo.__supportChatPusherBound = true;
    pusher.connection.bind('connected', handlers.onConnected);
    pusher.connection.bind('disconnected', handlers.onDisconnected);
    pusher.connection.bind('unavailable', handlers.onDisconnected);
    pusher.connection.bind('failed', handlers.onDisconnected);

    if (pusher.connection.state === 'connected') {
        handlers.onConnected();
    }

    return true;
}

/**
 * WebSocket for instant delivery + HTTP poll backup (always on while chat is open).
 */
export function useSupportChatRealtime(options) {
    const {
        reverbConfig,
        onMessage,
        onSessionUpdated,
        onTyping,
        normalizeMessage = (m) => m,
        getMessageCutoff = () => null,
        /** @type {null | ((afterMessageId: number) => Promise<object[]>)} */
        pollMessages = null,
        pollVisibleMs = 500,
        pollHiddenMs = 2000,
        getPausePolling = () => false,
    } = options;

    const wsConnected = ref(false);
    const wsFailed = ref(false);
    const deliveryMode = ref(pollMessages ? 'poll' : 'reverb');

    let channel = null;
    let channelName = null;
    let lastMessageId = 0;
    let activeTicketId = null;
    let channelRetryTimer = null;
    let subscribeWatchdogTimer = null;
    let pollTimer = null;
    let pollInFlight = false;
    let visibilityBound = false;
    let pollingPaused = false;

    function resolvePollMs() {
        if (typeof document !== 'undefined' && document.hidden) {
            return pollHiddenMs;
        }

        if (wsConnected.value) {
            return POLL_BACKUP_MS;
        }

        return pollVisibleMs;
    }

    function stopPoll() {
        clearTimeout(pollTimer);
        pollTimer = null;
    }

    function syncLastMessageId(messages) {
        if (Array.isArray(messages)) {
            lastMessageId = messages.reduce((max, m) => {
                const id = messageIdKey(m);

                return id !== null ? Math.max(max, id) : max;
            }, lastMessageId);
        }
    }

    function setLastMessageId(id) {
        const n = Number(id);
        if (Number.isFinite(n) && n > 0) {
            lastMessageId = Math.max(lastMessageId, n);
        }
    }

    function ingestMessage(raw, { viaWs = false } = {}) {
        const msg = normalizeMessage(raw);
        const id = messageIdKey(msg);
        if (id === null) {
            return false;
        }
        const cutoff = getMessageCutoff();
        if (cutoff && id > cutoff) {
            return false;
        }
        const accepted = onMessage({ ...msg, id });
        if (accepted !== false) {
            setLastMessageId(id);
            if (viaWs) {
                wsFailed.value = false;
                deliveryMode.value = 'reverb';
            }
        }

        return accepted !== false;
    }

    function handleWsPayload(payload) {
        if (payload?.message) {
            ingestMessage(payload.message, { viaWs: true });
        }
    }

    function markChannelLive() {
        wsConnected.value = true;
        wsFailed.value = false;
        deliveryMode.value = 'reverb';
        schedulePoll();
    }

    function markChannelOffline() {
        wsConnected.value = false;
        wsFailed.value = true;
        schedulePoll();
        void runPoll();
    }

    function pausePolling() {
        pollingPaused = true;
        stopPoll();
    }

    function resumePolling() {
        pollingPaused = false;
        if (activeTicketId) {
            schedulePoll();
            void runPoll();
        }
    }

    async function runPoll() {
        if (!activeTicketId || !pollMessages || pollInFlight || pollingPaused || getPausePolling()) {
            return;
        }

        if (!lastMessageId) {
            return;
        }

        pollInFlight = true;
        try {
            const items = await pollMessages(lastMessageId);
            if (!Array.isArray(items) || activeTicketId === null) {
                return;
            }

            let delivered = false;
            for (const raw of items) {
                if (ingestMessage(raw, { viaWs: false })) {
                    delivered = true;
                }
            }

            if (delivered && !wsConnected.value) {
                deliveryMode.value = 'poll';
            }
        } catch {
            /* retry next tick */
        } finally {
            pollInFlight = false;
        }
    }

    function schedulePoll() {
        stopPoll();

        if (!activeTicketId || !pollMessages || pollingPaused) {
            return;
        }

        pollTimer = window.setTimeout(async () => {
            await runPoll();
            if (activeTicketId) {
                schedulePoll();
            }
        }, resolvePollMs());
    }

    function onVisibilityChange() {
        if (!activeTicketId) {
            return;
        }

        void runPoll();
        schedulePoll();
    }

    function bindVisibilityListener() {
        if (visibilityBound || typeof document === 'undefined') {
            return;
        }

        visibilityBound = true;
        document.addEventListener('visibilitychange', onVisibilityChange);
    }

    function unbindVisibilityListener() {
        if (!visibilityBound || typeof document === 'undefined') {
            return;
        }

        visibilityBound = false;
        document.removeEventListener('visibilitychange', onVisibilityChange);
    }

    function attachChannelListeners(ch) {
        const handleTyping = (payload) => {
            if (onTyping && payload) {
                onTyping(payload);
            }
        };

        ch.listen('.message.sent', handleWsPayload);
        ch.listen('message.sent', handleWsPayload);

        if (onSessionUpdated) {
            const handleSession = (e) => {
                if (e.ticket) {
                    onSessionUpdated(e.ticket);
                }
            };
            ch.listen('.session.updated', handleSession);
            ch.listen('session.updated', handleSession);
        }

        if (onTyping) {
            ch.listen('.typing', handleTyping);
            ch.listen('typing', handleTyping);
        }

        if (typeof ch.subscribed === 'function') {
            ch.subscribed(() => {
                markChannelLive();
            });
        }

        if (typeof ch.error === 'function') {
            ch.error(() => {
                markChannelOffline();
            });
        }
    }

    function bindWsChannel(ticketId) {
        const echo = ensureEcho(typeof reverbConfig === 'function' ? reverbConfig() : reverbConfig);
        if (!echo || !ticketId || activeTicketId !== ticketId) {
            wsConnected.value = false;
            wsFailed.value = true;
            schedulePoll();
            void runPoll();

            return;
        }

        bindPusherConnectionOnce(echo, {
            onConnected: () => {
                wsFailed.value = false;
            },
            onDisconnected: () => {
                if (activeTicketId) {
                    markChannelOffline();
                }
            },
        });

        clearTimeout(channelRetryTimer);
        clearTimeout(subscribeWatchdogTimer);

        const nextChannelName = `customer-support.${ticketId}`;
        if (channelName && channelName !== nextChannelName) {
            safeEchoLeave(channelName);
            channel = null;
            channelName = null;
        }

        if (channel && channelName === nextChannelName) {
            schedulePoll();

            return;
        }

        channelName = nextChannelName;
        channel = echo.private(channelName);
        attachChannelListeners(channel);

        subscribeWatchdogTimer = setTimeout(() => {
            if (activeTicketId !== ticketId) {
                return;
            }
            if (!wsConnected.value) {
                wsFailed.value = true;
                void runPoll();
            }
        }, WS_WATCHDOG_MS);
    }

    function subscribe(ticketId, initialMessages = []) {
        const sameTicket = activeTicketId === ticketId && channelName === `customer-support.${ticketId}`;

        if (!sameTicket) {
            teardown({ keepVisibility: true });
            activeTicketId = ticketId;
            lastMessageId = 0;
            wsConnected.value = false;
            wsFailed.value = false;
            deliveryMode.value = pollMessages ? 'poll' : 'reverb';
            bindVisibilityListener();
            bindWsChannel(ticketId);
        }

        syncLastMessageId(initialMessages);
        schedulePoll();
        void runPoll();
    }

    function teardown({ keepVisibility = false } = {}) {
        clearTimeout(channelRetryTimer);
        channelRetryTimer = null;
        clearTimeout(subscribeWatchdogTimer);
        subscribeWatchdogTimer = null;
        stopPoll();

        if (channelName) {
            safeEchoLeave(channelName);
        }

        channel = null;
        channelName = null;
        activeTicketId = null;
        wsConnected.value = false;

        if (!keepVisibility) {
            unbindVisibilityListener();
        }
    }

    function isSubscribedTo(ticketId) {
        return activeTicketId === ticketId && channelName === `customer-support.${ticketId}`;
    }

    onBeforeUnmount(() => {
        teardown();
    });

    return {
        wsConnected,
        wsFailed,
        deliveryMode,
        subscribe,
        teardown,
        isSubscribedTo,
        syncLastMessageId,
        setLastMessageId,
        getLastMessageId: () => lastMessageId,
        runPoll,
        pausePolling,
        resumePolling,
    };
}
