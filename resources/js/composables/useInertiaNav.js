import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const navPending = ref(false);

/**
 * Cancel-safe Inertia navigation for admin/operations shells.
 * Prevents stacked visits when links are clicked in quick succession.
 */
export function useInertiaNav() {
    const page = usePage();

    function normalizePath(href) {
        try {
            return new URL(href, window.location.origin).pathname;
        } catch {
            return String(href || '').split('?')[0];
        }
    }

    function visit(href, options = {}) {
        const nextPath = normalizePath(href);
        const currentPath = (page.url || '').split('?')[0] || '';

        if (nextPath === currentPath) {
            return;
        }

        if (typeof router.cancel === 'function') {
            router.cancel();
        }

        navPending.value = true;

        router.visit(href, {
            preserveScroll: options.preserveScroll ?? false,
            preserveState: options.preserveState ?? false,
            replace: options.replace ?? false,
            only: options.only,
            onFinish: () => {
                navPending.value = false;
                options.onFinish?.();
            },
            onCancel: () => {
                navPending.value = false;
                options.onCancel?.();
            },
        });
    }

    return {
        navPending,
        visit,
    };
}
