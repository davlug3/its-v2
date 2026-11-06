<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import EmptyState from '@/components/common/EmptyState.vue';

export interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    width?: number;
    resizable?: boolean;
    align?: 'left' | 'center' | 'right';
}

const props = withDefaults(
    defineProps<{
        columns: Column[];
        rows: Record<string, unknown>[];
        tableId: string;
        loading?: boolean;
        emptyMessage?: string;
        pagination?: 'client' | 'server' | 'none';
        pageSize?: number;
        pageSizeOptions?: number[];
        total?: number;
        sortKey?: string;
        sortDir?: 'asc' | 'desc';
        page?: number;
    }>(),
    {
        loading: false,
        emptyMessage: 'No data.',
        pagination: 'none',
        pageSize: 20,
        pageSizeOptions: () => [10, 20, 50, 100],
        total: 0,
        sortKey: '',
        sortDir: 'asc',
        page: 1,
    },
);

const emit = defineEmits<{
    (e: 'update:sortKey', value: string): void;
    (e: 'update:sortDir', value: 'asc' | 'desc'): void;
    (e: 'update:page', value: number): void;
    (e: 'update:pageSize', value: number): void;
    (e: 'sort', key: string, dir: 'asc' | 'desc'): void;
    (e: 'page-change', page: number): void;
    (e: 'page-size-change', size: number): void;
    (e: 'row-click', row: Record<string, unknown>): void;
}>();

// Controlled vs uncontrolled sort state
const internalSortKey = ref(props.sortKey || props.columns.find((c) => c.sortable)?.key || '');
const internalSortDir = ref<'asc' | 'desc'>(props.sortDir || 'asc');

const activeSortKey = computed(() => (props.sortKey !== undefined && props.sortKey !== '' ? props.sortKey : internalSortKey.value));
const activeSortDir = computed(() => (props.sortKey !== undefined && props.sortKey !== '' ? props.sortDir || 'asc' : internalSortDir.value));

const toggleSort = (key: string): void => {
    const col = props.columns.find((c) => c.key === key);
    if (!col?.sortable) return;
    let newDir: 'asc' | 'desc' = 'asc';
    if (activeSortKey.value === key) {
        newDir = activeSortDir.value === 'asc' ? 'desc' : 'asc';
    }
    if (props.sortKey !== undefined && props.sortKey !== '') {
        emit('update:sortKey', key);
        emit('update:sortDir', newDir);
    } else {
        internalSortKey.value = key;
        internalSortDir.value = newDir;
    }
    emit('sort', key, newDir);
};

const sortIndicator = (key: string): string => {
    if (activeSortKey.value !== key) return '';
    return activeSortDir.value === 'asc' ? ' ▲' : ' ▼';
};

// Column widths with persistence
const storageKey = `datatable:${props.tableId}:widths`;

const widths = ref<Record<string, number>>({});

const loadWidths = (): void => {
    try {
        const raw = localStorage.getItem(storageKey);
        if (raw) widths.value = JSON.parse(raw);
    } catch {
        widths.value = {};
    }
};

const saveWidths = (): void => {
    try {
        localStorage.setItem(storageKey, JSON.stringify(widths.value));
    } catch {
        // ignore
    }
};

const colWidth = (col: Column): number => widths.value[col.key] ?? col.width ?? 120;

// Resizing
const resizing = ref<{ key: string; startX: number; startWidth: number } | null>(null);

const startResize = (e: MouseEvent, col: Column): void => {
    e.preventDefault();
    e.stopPropagation();
    resizing.value = { key: col.key, startX: e.clientX, startWidth: colWidth(col) };
    document.addEventListener('mousemove', onResizeMove);
    document.addEventListener('mouseup', onResizeEnd);
};

const onResizeMove = (e: MouseEvent): void => {
    if (!resizing.value) return;
    const delta = e.clientX - resizing.value.startX;
    const next = Math.max(60, resizing.value.startWidth + delta);
    widths.value = { ...widths.value, [resizing.value.key]: next };
};

const onResizeEnd = (): void => {
    if (resizing.value) saveWidths();
    resizing.value = null;
    document.removeEventListener('mousemove', onResizeMove);
    document.removeEventListener('mouseup', onResizeEnd);
};

onMounted(loadWidths);

// Client-side sorting + pagination
const sortedRows = computed(() => {
    const arr = [...props.rows];
    if (props.pagination === 'client' || props.pagination === 'none') {
        const key = activeSortKey.value;
        if (key) {
            arr.sort((a, b) => {
                const av = a[key];
                const bv = b[key];
                if (av === bv) return 0;
                const cmp = (av as number | string) < (bv as number | string) ? -1 : 1;
                return activeSortDir.value === 'asc' ? cmp : -cmp;
            });
        }
    }
    return arr;
});

// Global page-size preference persisted across tables.
const GLOBAL_PAGE_SIZE_KEY = 'datatable:global-page-size';

const internalPageSize = ref<number>(props.pageSize || 20);

const loadPageSize = (): void => {
    try {
        const raw = localStorage.getItem(GLOBAL_PAGE_SIZE_KEY);
        if (raw) {
            const parsed = parseInt(raw, 10);
            if (!isNaN(parsed) && parsed > 0) internalPageSize.value = parsed;
        }
    } catch {
        // ignore
    }
};

const activePageSize = computed(() => internalPageSize.value);

const setPageSize = (size: number): void => {
    internalPageSize.value = size;
    try {
        localStorage.setItem(GLOBAL_PAGE_SIZE_KEY, String(size));
    } catch {
        // ignore
    }
    emit('update:pageSize', size);
    emit('page-size-change', size);
    // reset to first page
    goPage(1);
};

onMounted(() => {
    loadWidths();
    loadPageSize();
});

const clientTotalPages = computed(() =>
    props.pagination === 'client' ? Math.max(1, Math.ceil(sortedRows.value.length / activePageSize.value)) : 1,
);

const displayedRows = computed(() => {
    if (props.pagination === 'client') {
        const start = (currentPage.value - 1) * activePageSize.value;
        return sortedRows.value.slice(start, start + activePageSize.value);
    }
    return sortedRows.value;
});

const currentPage = computed(() => (props.page !== undefined ? props.page : 1));

const totalPages = computed(() =>
    props.pagination === 'server' ? Math.max(1, Math.ceil(props.total / activePageSize.value)) : clientTotalPages.value,
);

const totalRows = computed(() =>
    props.pagination === 'server' ? props.total : sortedRows.value.length,
);

const goPage = (p: number): void => {
    const next = Math.min(Math.max(1, p), totalPages.value);
    if (props.page !== undefined) {
        emit('update:page', next);
    }
    emit('page-change', next);
};

watch(() => props.tableId, loadWidths);
</script>

<template>
    <div class="space-y-3">
        <div class="overflow-x-auto rounded-md border border-border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="col in columns"
                            :key="col.key"
                            class="relative select-none border-r border-border last:border-r-0"
                            :style="{ width: colWidth(col) + 'px' }"
                        >
                            <span
                                v-if="col.sortable"
                                class="cursor-pointer"
                                @click="toggleSort(col.key)"
                            >
                                {{ col.label }}{{ sortIndicator(col.key) }}
                            </span>
                            <span v-else>{{ col.label }}</span>
                            <span
                                v-if="col.resizable !== false"
                                class="absolute right-0 top-0 z-10 h-full w-2 cursor-col-resize bg-border/40 transition-colors hover:bg-cobalt-core"
                                @mousedown="startResize($event, col)"
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="loading">
                        <TableRow v-for="i in 5" :key="i">
                            <TableCell v-for="col in columns" :key="col.key">
                                <Skeleton class="h-4 w-full" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else-if="displayedRows.length === 0">
                        <TableRow>
                            <TableCell :colspan="columns.length" class="p-0">
                                <EmptyState :message="emptyMessage" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow
                            v-for="(row, idx) in displayedRows"
                            :key="(row.id as string | number | undefined) ?? idx"
                            class="cursor-pointer hover:bg-haze/50"
                            @click="emit('row-click', row)"
                        >
                            <TableCell v-for="col in columns" :key="col.key" class="border-r border-border last:border-r-0" :style="{ width: colWidth(col) + 'px' }">
                                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                    {{ row[col.key] }}
                                </slot>
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>

        <div v-if="pagination !== 'none'" class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
            <span>{{ totalRows }} rows</span>
            <div class="flex flex-wrap items-center gap-2">
                <label class="flex items-center gap-1">
                    <span>Per page</span>
                    <select
                        class="h-8 rounded-md border border-input bg-background px-2 text-sm"
                        :value="activePageSize"
                        @change="setPageSize(Number(($event.target as HTMLSelectElement).value))"
                    >
                        <option v-for="opt in pageSizeOptions" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                </label>
                <button
                    type="button"
                    class="rounded-md border border-border px-2 py-1 hover:bg-haze disabled:opacity-50"
                    :disabled="currentPage <= 1"
                    @click="goPage(currentPage - 1)"
                >
                    Prev
                </button>
                <span>Page {{ currentPage }} of {{ totalPages }}</span>
                <button
                    type="button"
                    class="rounded-md border border-border px-2 py-1 hover:bg-haze disabled:opacity-50"
                    :disabled="currentPage >= totalPages"
                    @click="goPage(currentPage + 1)"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>