import { createColumnHelper } from '@tanstack/vue-table';

const helper = createColumnHelper();

/**
 * @param {Array<{ id?: string, accessorKey?: string, header: string, accessorFn?: Function, enableSorting?: boolean }>} defs
 */
export function buildAdminColumns(defs) {
    return defs.map((def) => {
        if (def.accessorFn) {
            return helper.accessor(def.accessorFn, {
                id: def.id ?? def.header,
                header: def.header,
                enableSorting: def.enableSorting !== false,
            });
        }

        return helper.accessor(def.accessorKey ?? def.id, {
            header: def.header,
            enableSorting: def.enableSorting !== false,
        });
    });
}

export function adminActionColumn() {
    return helper.display({
        id: '_actions',
        header: '',
        enableSorting: false,
    });
}
