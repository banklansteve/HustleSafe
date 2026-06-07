/**
 * Strip HTML / markdown-ish markup for admin-readable copy.
 */
export function plainText(value, limit = 0) {
    if (value == null || value === '') {
        return '';
    }

    let text = String(value);
    text = text.replace(/<[^>]*>/g, ' ');
    text = text.replace(/!\[([^\]]*)\]\([^)]+\)/g, '$1');
    text = text.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
    text = text.replace(/`{1,3}([^`]+)`{1,3}/g, '$1');
    text = text.replace(/^#{1,6}\s+/gm, '');
    text = text.replace(/[*_~]+/g, '');
    text = text.replace(/\s+/g, ' ').trim();

    if (limit > 0 && text.length > limit) {
        return `${text.slice(0, Math.max(0, limit - 1)).trim()}…`;
    }

    return text;
}
