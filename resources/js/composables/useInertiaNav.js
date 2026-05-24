import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { normalizeInertiaNavTarget } from '@/support/inertiaNavTarget';

const navPending = ref(false);

/**
 * Cancel-safe Inertia navigation for admin/operations shells.
 * Prevents stacked visits when links are clicked in quick succession.
 */
export function useInertiaNav() {
    const page = usePage();

    function visit(href, options = {}) {
        const nextTarget = normalizeInertiaNavTarget(href);
        const currentTarget = typeof window !== 'undefined'
            ? normalizeInertiaNavTarget(window.location.href)
            : normalizeInertiaNavTarget(page.url || '/');

        if (nextTarget === currentTarget) {
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
