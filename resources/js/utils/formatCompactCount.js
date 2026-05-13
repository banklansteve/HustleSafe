/**
 * Human-friendly counts for favourites, likes, etc.
 */
export function formatCompactCount(n) {
    const x = Number(n);
    if (!Number.isFinite(x) || x <= 0) {
        return '0';
    }
    const v = Math.floor(x);
    if (v < 1000) {
        return String(v);
    }
    if (v < 10000) {
        const k = v / 1000;

        return `${k % 1 === 0 ? k : k.toFixed(1)}k`.replace('.0k', 'k');
    }
    if (v < 1_000_000) {
        return `${Math.round(v / 100) / 10}k`.replace('.0k', 'k');
    }
    const m = v / 1_000_000;

    return `${m % 1 === 0 ? m : m.toFixed(1)}M`.replace('.0M', 'M');
}
