import {
    BriefcaseIcon,
    DocumentTextIcon,
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePathname } from '@/composables/usePathname';

/**
 * Shared freelancer shortcuts for AppShell header and mobile account menu.
 */
export function useFreelancerShellNav() {
    const page = usePage();
    const pathname = usePathname(page);

    const links = computed(() => [
        {
            key: 'proposals',
            href: '/my-proposals',
            label: 'My proposals',
            icon: DocumentTextIcon,
            isActive: (path) => path === '/my-proposals' || path.startsWith('/my-proposals/'),
        },
        {
            key: 'explore',
            href: route('quests.explore'),
            label: 'Browse quests',
            icon: MagnifyingGlassIcon,
            isActive: (path) => path.startsWith('/quests/explore'),
        },
        {
            key: 'portfolio',
            href: route('portfolio.manage'),
            label: 'Portfolio',
            icon: BriefcaseIcon,
            isActive: (path) =>
                path.startsWith('/portfolio/manage')
                || path.startsWith('/portfolio/create')
                || /\/portfolio\/\d+\/edit$/.test(path),
        },
    ]);

    function isLinkActive(link) {
        return link.isActive(pathname.value);
    }

    return {
        links,
        isLinkActive,
    };
}
