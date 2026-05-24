/**
 * Human-friendly relative or absolute date/time for staff UI.
 */
export function formatHumanDateTime(value) {
    if (value == null || value === '') {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);

    if (diffSec < 45) {
        return 'Just now';
    }
    if (diffSec < 90) {
        return '1 min ago';
    }
    if (diffSec < 3600) {
        const mins = Math.floor(diffSec / 60);

        return `${mins} min ago`;
    }
    if (diffSec < 86400) {
        const hours = Math.floor(diffSec / 3600);

        return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
    }
    if (diffSec < 172800) {
        return 'Yesterday';
    }
    if (diffSec < 604800) {
        const days = Math.floor(diffSec / 86400);

        return days === 1 ? '1 day ago' : `${days} days ago`;
    }

    const sameYear = date.getFullYear() === now.getFullYear();

    return date.toLocaleString('en-NG', {
        day: 'numeric',
        month: 'short',
        year: sameYear ? undefined : 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Africa/Lagos',
    });
}

export function humanizeSlug(value) {
    return String(value || '')
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase())
        .trim();
}
