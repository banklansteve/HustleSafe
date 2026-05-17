import { ref, watch } from 'vue';

const STORAGE_KEY = 'hustlesafe-admin-sidebar-collapsed';

function readCollapsed() {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(STORAGE_KEY) === '1';
}

export function useAdminSidebar() {
    const collapsed = ref(readCollapsed());

    watch(collapsed, (value) => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
        }
    });

    function toggleCollapsed() {
        collapsed.value = !collapsed.value;
    }

    return { collapsed, toggleCollapsed };
}
