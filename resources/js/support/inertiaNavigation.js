import { router } from '@inertiajs/vue3';

/**
 * Serialize Inertia visits so rapid navigation always lands on the latest target.
 * Ignores stale finish callbacks from cancelled visits.
 */
let latestVisitToken = 0;

const originalVisit = router.visit.bind(router);

router.visit = (url, options = {}) => {
    const token = ++latestVisitToken;

    if (typeof router.cancel === 'function') {
        router.cancel();
    }

    return originalVisit(url, {
        ...options,
        onFinish: (...args) => {
            if (token === latestVisitToken) {
                options.onFinish?.(...args);
            }
        },
        onCancel: (...args) => {
            options.onCancel?.(...args);
        },
    });
};
