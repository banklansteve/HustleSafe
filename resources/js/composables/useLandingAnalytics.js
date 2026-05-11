/**
 * Dispatches browser events for CTAs and hero interactions (GTM/analytics can subscribe).
 */
export function trackLanding(event, payload = {}) {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(
        new CustomEvent('landing:analytics', {
            detail: {
                event,
                path: window.location.pathname,
                ts: Date.now(),
                ...payload,
            },
        }),
    );
}
