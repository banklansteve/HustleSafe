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

/**
 * Leave management date display (dd/mm/yyyy).
 */
export function formatLeaveDate(value) {
    if (value == null || value === '') {
        return '—';
    }

    const raw = String(value).trim();
    const ymdMatch = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
    if (ymdMatch) {
        return `${ymdMatch[3]}/${ymdMatch[2]}/${ymdMatch[1]}`;
    }

    const slashMatch = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(raw);
    if (slashMatch) {
        const first = Number(slashMatch[1]);
        const second = Number(slashMatch[2]);
        const year = slashMatch[3];

        if (first > 12 && second <= 12) {
            return `${String(first).padStart(2, '0')}/${String(second).padStart(2, '0')}/${year}`;
        }
        if (second > 12 && first <= 12) {
            return `${String(second).padStart(2, '0')}/${String(first).padStart(2, '0')}/${year}`;
        }
        if (first <= 12 && second <= 12) {
            return `${String(second).padStart(2, '0')}/${String(first).padStart(2, '0')}/${year}`;
        }
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return raw;
    }

    const parts = new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: 'Africa/Lagos',
    }).formatToParts(date);

    const day = parts.find((part) => part.type === 'day')?.value ?? '01';
    const month = parts.find((part) => part.type === 'month')?.value ?? '01';
    const year = parts.find((part) => part.type === 'year')?.value ?? '1970';

    return `${day}/${month}/${year}`;
}

/**
 * Leave management timestamp display (dd/mm/yyyy, HH:mm).
 */
export function formatLeaveDateTime(value) {
    if (value == null || value === '') {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const parts = new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: 'Africa/Lagos',
    }).formatToParts(date);

    const day = parts.find((part) => part.type === 'day')?.value ?? '01';
    const month = parts.find((part) => part.type === 'month')?.value ?? '01';
    const year = parts.find((part) => part.type === 'year')?.value ?? '1970';
    const hour = parts.find((part) => part.type === 'hour')?.value ?? '00';
    const minute = parts.find((part) => part.type === 'minute')?.value ?? '00';

    return `${day}/${month}/${year}, ${hour}:${minute}`;
}

export function formatLeaveDurationRequested(leave) {
    if (leave?.duration_type === 'hours') {
        const hours = Number(leave?.hours_requested || 0);

        return hours === 1 ? '1 hour' : `${hours} hours`;
    }

    const days = Number(leave?.days_requested || 1);

    if (leave?.duration_type === 'multiple_days') {
        return days === 1 ? '1 day' : `${days} days`;
    }

    return '1 day';
}

export function formatLeaveRange(leave) {
    const start = formatLeaveDate(leave?.start_date);
    const end = formatLeaveDate(leave?.end_date);

    if (leave?.duration_type === 'hours') {
        return start;
    }
    if (leave?.duration_type === 'multiple_days' && leave?.start_date !== leave?.end_date) {
        return `${start} to ${end}`;
    }

    return start;
}

export function formatLeaveCalendarDates(row) {
    const start = formatLeaveDate(row?.start_date);
    const end = formatLeaveDate(row?.end_date);

    if (row?.duration_type === 'hours' || row?.start_date === row?.end_date) {
        return start;
    }

    return `${start} – ${end}`;
}

/** @deprecated Use formatLeaveDate for leave management dates. */
export function formatHumanDate(value) {
    return formatLeaveDate(value);
}

export function humanizeSlug(value) {
    return String(value || '')
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase())
        .trim();
}
