import { onBeforeUnmount, ref } from 'vue';
import { ensureEcho } from '@/utils/ensureEcho';

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
 * Live support delivery via Reverb only — no HTTP polling for new messages.
 * Callers must load initial history over HTTP before subscribe().
 */
export function useSupportChatRealtime(options) {
    const {
        reverbConfig,
        onMessage,
        onSessionUpdated,
        onTyping,
        normalizeMessage = (m) => m,
        getMessageCutoff = () => null,
    } = options;

    const wsConnected = ref(false);
    const wsFailed = ref(false);
    const deliveryMode = ref('reverb');

    let channel = null;
    let channelName = null;
    let lastMessageId = 0;
    let activeTicketId = null;
    let channelRetryTimer = null;
    let subscribeWatchdogTimer = null;

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
    }

    function markChannelOffline() {
        wsConnected.value = false;
        wsFailed.value = true;
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
            clearTimeout(channelRetryTimer);
            channelRetryTimer = setTimeout(() => {
                if (activeTicketId === ticketId) {
                    bindWsChannel(ticketId);
                }
            }, 800);

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
        if (channel && channelName && channelName !== nextChannelName) {
            echo.leave(channelName);
            channel = null;
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
                bindWsChannel(ticketId);
            }
        }, 6000);
    }

    function subscribe(ticketId, initialMessages = []) {
        teardown();
        activeTicketId = ticketId;
        lastMessageId = 0;
        syncLastMessageId(initialMessages);
        wsConnected.value = false;
        wsFailed.value = false;
        bindWsChannel(ticketId);
    }

    function teardown() {
        clearTimeout(channelRetryTimer);
        channelRetryTimer = null;
        clearTimeout(subscribeWatchdogTimer);
        subscribeWatchdogTimer = null;

        if (window.Echo && channelName) {
            window.Echo.leave(channelName);
        }

        channel = null;
        channelName = null;
        activeTicketId = null;
        wsConnected.value = false;
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
        syncLastMessageId,
        setLastMessageId,
        getLastMessageId: () => lastMessageId,
    };
}
