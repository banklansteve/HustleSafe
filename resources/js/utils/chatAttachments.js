export function isGifAttachment(att) {
    if (!att) return false;
    return att.type === 'gif' || att.remote === true || String(att.mime || '').includes('gif');
}

export function isImageAttachment(att) {
    if (!att || isGifAttachment(att)) return false;
    return String(att.mime || '').startsWith('image/') || /\.(jpe?g|png|webp)$/i.test(att?.name || att?.url || '');
}

export function attachmentUrl(att) {
    if (!att?.url) return '';
    const url = String(att.url);
    if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//')) {
        return url;
    }
    const path = url.startsWith('/') ? url : `/${url.replace(/^\/+/, '')}`;
    if (typeof window !== 'undefined' && window.location?.origin) {
        return `${window.location.origin}${path}`;
    }

    return path;
}
