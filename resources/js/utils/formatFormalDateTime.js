/**
 * Formal datetime e.g. "25th July, 2026. 10:00am" (Africa/Lagos).
 */
export function formatFormalDateTime(value) {
    if (value == null || value === '') {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Africa/Lagos',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).formatToParts(date);

    const pick = (type) => parts.find((p) => p.type === type)?.value ?? '';
    const day = Number(pick('day'));
    const suffix = day >= 11 && day <= 13 ? 'th' : { 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th';
    const hour = pick('hour');
    const minute = pick('minute');
    const dayPeriod = pick('dayPeriod').toLowerCase();

    return `${day}${suffix} ${pick('month')}, ${pick('year')}. ${hour}:${minute}${dayPeriod}`;
}
