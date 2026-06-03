import { computed } from 'vue';

/**
 * Lightweight admin layout tokens for pages that want consistent styling.
 * (Some admin pages were referencing this helper but it did not exist.)
 */
export function useAdminShell() {
    const shell = computed(() => ({
        card: 'border-slate-200/80 bg-white text-slate-900 shadow-sm ring-1 ring-slate-100 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50 dark:ring-slate-900/50',
        title: 'text-slate-600 dark:text-slate-300',
        input: 'border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 focus:border-primary-400 focus:ring-primary-400/30 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50',
        muted: 'text-slate-600 dark:text-slate-300',
    }));

    return { shell };
}

