const DATE_TIME_SUFFIXES = ['_at', '_on'];
const DATE_ONLY_COLUMNS = new Set([
    'date_of_birth',
    'scheduled_start_date',
    'estimated_delivery_date',
    'delivery_deadline',
    'proposed_completion_date',
    'planned_start_date',
    'planned_finish_date',
    'expires_on',
]);

export function isAdminDateColumn(column) {
    if (DATE_ONLY_COLUMNS.has(column)) {
        return true;
    }

    return DATE_TIME_SUFFIXES.some((suffix) => column.endsWith(suffix));
}

export function formatAdminDateTime(value, column = '') {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'string' && /^\d{2}\/\d{2}\/\d{4}$/.test(value)) {
        return value;
    }

    if (typeof value === 'string' && /^\d{2}-\d{2}-\d{4} \d{1,2}:\d{2}(am|pm)$/i.test(value)) {
        return value;
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    if (DATE_ONLY_COLUMNS.has(column)) {
        return `${day}/${month}/${year}`;
    }

    let hours = date.getHours();
    const ampm = hours >= 12 ? 'pm' : 'am';
    hours = hours % 12 || 12;
    const mins = String(date.getMinutes()).padStart(2, '0');

    return `${day}-${month}-${year} ${String(hours).padStart(2, '0')}:${mins}${ampm}`;
}

export function formatDatePickerDisplay(ymd) {
    if (!ymd || typeof ymd !== 'string') {
        return '';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd.trim());
    if (!match) {
        return ymd;
    }

    return `${match[3]}/${match[2]}/${match[1]}`;
}
