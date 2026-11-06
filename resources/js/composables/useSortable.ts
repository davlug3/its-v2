import { ref } from 'vue';

export type SortDir = 'asc' | 'desc';

export function useSortable<T extends Record<string, unknown>>(
    defaultKey: string,
    defaultDir: SortDir = 'asc',
) {
    const sortKey = ref(defaultKey);
    const sortDir = ref<SortDir>(defaultDir);

    const sorted = (items: T[]): T[] => {
        const arr = [...items];
        arr.sort((a, b) => {
            const av = a[sortKey.value];
            const bv = b[sortKey.value];
            if (av === bv) return 0;
            const cmp = (av as number | string) < (bv as number | string) ? -1 : 1;
            return sortDir.value === 'asc' ? cmp : -cmp;
        });
        return arr;
    };

    const toggle = (key: string): void => {
        if (sortKey.value === key) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey.value = key;
            sortDir.value = 'asc';
        }
    };

    const indicator = (key: string): string => {
        if (sortKey.value !== key) return '';
        return sortDir.value === 'asc' ? ' ▲' : ' ▼';
    };

    return { sortKey, sortDir, sorted, toggle, indicator };
}
