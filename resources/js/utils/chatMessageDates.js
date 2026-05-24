function startOfLocalDay(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);

    return d;
}

function parseMessageDate(iso) {
    if (iso == null || iso === '') {
        return null;
    }
    const date = iso instanceof Date ? iso : new Date(iso);

    return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Calendar-day key for grouping messages (local timezone).
 */
export function dayKeyFromIso(iso) {
    const date = parseMessageDate(iso);
    if (!date) {
        return 'unknown';
    }

    return startOfLocalDay(date).toISOString();
}

/**
 * Section label above a day of messages. Today returns null (no divider).
 */
export function chatDayDividerLabel(iso) {
    const date = parseMessageDate(iso);
    if (!date) {
        return null;
    }

    const todayStart = startOfLocalDay(new Date());
    const messageStart = startOfLocalDay(date);
    const diffDays = Math.round((todayStart.getTime() - messageStart.getTime()) / 86400000);

    if (diffDays === 0) {
        return null;
    }
    if (diffDays === 1) {
        return 'Yesterday';
    }
    if (diffDays < 7) {
        return date.toLocaleDateString(undefined, { weekday: 'long' });
    }

    const now = new Date();
    const sameYear = date.getFullYear() === now.getFullYear();

    return date.toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
        year: sameYear ? undefined : 'numeric',
    });
}

/**
 * Time shown on each bubble — today and older days use time only (date is in the divider).
 */
export function formatChatMessageTime(iso) {
    const date = parseMessageDate(iso);
    if (!date) {
        return '';
    }

    return date.toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });
}

/**
 * @param {Array<{ created_at?: string|null }>} messages
 * @returns {Array<{ dayKey: string, label: string|null, messages: Array }>}
 */
export function groupMessagesByChatDay(messages) {
    if (!Array.isArray(messages) || messages.length === 0) {
        return [];
    }

    const groups = [];
    let current = null;

    for (const msg of messages) {
        const dayKey = dayKeyFromIso(msg.created_at);
        if (!current || current.dayKey !== dayKey) {
            current = {
                dayKey,
                label: chatDayDividerLabel(msg.created_at),
                messages: [],
            };
            groups.push(current);
        }
        current.messages.push(msg);
    }

    return groups;
}
