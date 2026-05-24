/**
 * Normalize an Inertia href or page URL to pathname + sorted query string for comparisons.
 */
export function normalizeInertiaNavTarget(href) {
    try {
        const url = new URL(href, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
        let path = url.pathname;
        if (path.length > 1 && path.endsWith('/')) {
            path = path.slice(0, -1);
        }

        const params = new URLSearchParams(url.search);
        const sorted = new URLSearchParams();
        [...params.keys()]
            .sort()
            .forEach((key) => {
                const value = params.get(key);
                if (value !== null) {
                    sorted.set(key, value);
                }
            });

        const search = sorted.toString();

        return path + (search ? `?${search}` : '');
    } catch {
        const raw = String(href || '');
        const [path, query = ''] = raw.split('?');

        return path + (query ? `?${query}` : '');
    }
}
