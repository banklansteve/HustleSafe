import { computed, ref, watch } from 'vue';

/**
 * Client-side search, sort, and pagination for operations listing tables.
 */
export function useClientQueue(sourceItems, options = {}) {
    const search = ref('');
    const sortKey = ref(options.defaultSortKey ?? 'id');
    const sortDir = ref(options.defaultSortDir ?? 'desc');
    const page = ref(1);
    const perPage = ref(options.perPage ?? 25);

    const normalizedItems = computed(() => {
        const raw = typeof sourceItems === 'function' ? sourceItems() : sourceItems?.value ?? sourceItems ?? [];

        return Array.isArray(raw) ? raw : [];
    });

    const filteredItems = computed(() => {
        const q = search.value.trim().toLowerCase();
        if (!q) {
            return normalizedItems.value;
        }

        const fields = options.searchFields ?? ['id', 'title', 'name', 'email', 'reference_code', 'status', 'admin_status'];

        return normalizedItems.value.filter((row) =>
            fields.some((field) => {
                const value = resolvePath(row, field);
                return value !== null && value !== undefined && String(value).toLowerCase().includes(q);
            }),
        );
    });

    const sortedItems = computed(() => {
        const key = sortKey.value;
        const dir = sortDir.value === 'asc' ? 1 : -1;
        const list = [...filteredItems.value];

        list.sort((a, b) => {
            const av = resolvePath(a, key);
            const bv = resolvePath(b, key);

            if (av === bv) {
                return 0;
            }
            if (av === null || av === undefined) {
                return 1;
            }
            if (bv === null || bv === undefined) {
                return -1;
            }

            if (typeof av === 'number' && typeof bv === 'number') {
                return (av - bv) * dir;
            }

            return String(av).localeCompare(String(bv), undefined, { numeric: true, sensitivity: 'base' }) * dir;
        });

        return list;
    });

    const total = computed(() => sortedItems.value.length);
    const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));

    const pageItems = computed(() => {
        const current = Math.min(page.value, totalPages.value);
        const start = (current - 1) * perPage.value;

        return sortedItems.value.slice(start, start + perPage.value);
    });

    watch([search, sortKey, sortDir, perPage], () => {
        page.value = 1;
    });

    watch(totalPages, (max) => {
        if (page.value > max) {
            page.value = max;
        }
    });

    function setSort(key) {
        if (sortKey.value === key) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
            return;
        }

        sortKey.value = key;
        sortDir.value = 'desc';
    }

    function nextPage() {
        page.value = Math.min(totalPages.value, page.value + 1);
    }

    function prevPage() {
        page.value = Math.max(1, page.value - 1);
    }

    return {
        search,
        sortKey,
        sortDir,
        page,
        perPage,
        total,
        totalPages,
        pageItems,
        setSort,
        nextPage,
        prevPage,
    };
}

function resolvePath(object, path) {
    if (!object || !path) {
        return null;
    }

    if (!path.includes('.')) {
        return object[path];
    }

    return path.split('.').reduce((carry, segment) => (carry == null ? null : carry[segment]), object);
}
