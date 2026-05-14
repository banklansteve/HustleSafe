/**
 * Plain text from HTML (for validation, previews, counts).
 * @param {string|null|undefined} html
 * @returns {string}
 */
export function htmlToPlainText(html) {
    if (html == null || html === '') {
        return '';
    }
    const s = String(html);
    if (typeof document !== 'undefined') {
        const el = document.createElement('div');
        el.innerHTML = s;

        return (el.textContent || el.innerText || '').replace(/\u00a0/g, ' ').trim();
    }

    return s
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}
