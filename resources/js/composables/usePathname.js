import { computed } from 'vue';

/**
 * Normalized pathname from Inertia page.url (no query, no trailing slash except root).
 */
export function usePathname(page) {
    return computed(() => {
        const raw = (page.url || '/').split('?')[0] || '/';
        if (raw === '/') {
            return '/';
        }
        return raw.replace(/\/$/, '');
    });
}

/**
 * @param {import('vue').ComputedRef<string>} pathname
 * @param {string} targetHref — absolute path or full URL (only path segment is used)
 * @param {{ prefix?: boolean }} [opts]
 */
export function pathMatches(pathname, targetHref, opts = {}) {
    try {
        const u = targetHref.includes('://') ? new URL(targetHref).pathname : targetHref;
        let t = u.split('?')[0] || '/';
        if (t !== '/') {
            t = t.replace(/\/$/, '');
        }
        const p = pathname.value;
        if (opts.prefix) {
            return p === t || p.startsWith(`${t}/`);
        }
        return p === t;
    } catch {
        return false;
    }
}
