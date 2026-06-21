/**
 * Convert plain text (with optional bullet lines) to simple HTML for quest descriptions.
 * @param {string|null|undefined} text
 * @returns {string}
 */
export function plainTextToDescriptionHtml(text) {
    const lines = String(text || '').split(/\r?\n/);
    let html = '';
    let inList = false;

    const escapeHtml = (value) =>
        String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;');

    for (const raw of lines) {
        const line = raw.trim();
        if (line === '') {
            if (inList) {
                html += '</ul>';
                inList = false;
            }
            continue;
        }
        if (line.startsWith('- ') || line.startsWith('• ')) {
            if (!inList) {
                html += '<ul>';
                inList = true;
            }
            html += `<li>${escapeHtml(line.replace(/^[-•]\s+/, ''))}</li>`;
        } else {
            if (inList) {
                html += '</ul>';
                inList = false;
            }
            html += `<p>${escapeHtml(line)}</p>`;
        }
    }

    if (inList) {
        html += '</ul>';
    }

    const trimmed = String(text || '').trim();

    return html || (trimmed ? `<p>${escapeHtml(trimmed)}</p>` : '');
}

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
