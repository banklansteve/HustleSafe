/**
 * Match a nav item to the current path without false positives from sibling prefixes
 * (e.g. /operations/onboarding must not match /operations/onboarding-quality).
 */
export function matchPathPrefix(path, prefix, { exclude = [] } = {}) {
    const base = (path || '').split('?')[0];

    if (exclude.some((segment) => base.startsWith(segment))) {
        return false;
    }

    return base === prefix || base.startsWith(`${prefix}/`);
}
