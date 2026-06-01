import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Navigation visibility by platform role.
 * Staff admins and super admins should not see marketplace shortcuts (wallet, quests, proposals, etc.).
 */
export function usePlatformRoleNav() {
    const page = usePage();

    const roleSlug = computed(() => page.props.auth?.user?.role?.slug ?? '');
    const isPlatformStaff = computed(() => ['admin', 'super_admin'].includes(roleSlug.value));
    const isFreelancer = computed(() => roleSlug.value === 'freelancer');
    const isClient = computed(() => roleSlug.value === 'client');
    const showClientTools = computed(() => isClient.value);
    const showMarketplaceNav = computed(() => !isPlatformStaff.value);

    return {
        roleSlug,
        isPlatformStaff,
        isFreelancer,
        isClient,
        showClientTools,
        showMarketplaceNav,
    };
}
