<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DataTable from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { api } from '@/services/api';

interface Ticket {
    id: number;
    subject: string;
    state_id: number;
    created_date: number;
    created_name?: string | null;
    category_name?: string | null;
}

const router = useRouter();

const tickets = ref<Ticket[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = ref(20);
const loading = ref(true);
const error = ref<string | null>(null);
const sortKey = ref('id');
const sortDir = ref<'asc' | 'desc'>('desc');

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: 80, resizable: true },
    { key: 'subject', label: 'Subject', sortable: true, resizable: true },
    { key: 'created_name', label: 'Requester', sortable: false, width: 160, resizable: true },
    { key: 'category_name', label: 'Category', sortable: false, width: 140, resizable: true },
    { key: 'created_date', label: 'Created', sortable: true, width: 140, resizable: true },
];

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({
            scope: 'assigned_to_me',
            page: String(page.value),
            per_page: String(pageSize.value),
            sort: sortKey.value,
            dir: sortDir.value,
        });
        const res = await api<{ tickets: Ticket[]; total: number; page?: number }>('/tickets?' + params.toString());
        tickets.value = res.tickets ?? [];
        total.value = res.total ?? 0;
        page.value = res.page ?? page.value;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load tickets.';
    } finally {
        loading.value = false;
    }
};

const onSort = (key: string, dir: 'asc' | 'desc'): void => {
    sortKey.value = key;
    sortDir.value = dir;
    page.value = 1;
    void load();
};

const onPageChange = (p: number): void => {
    page.value = p;
    void load();
};

const onPageSizeChange = (size: number): void => {
    pageSize.value = size;
    page.value = 1;
    void load();
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="Assigned to you" description="Open tickets assigned to you." />

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <Card>
            <CardHeader>
                <CardTitle>Open tickets</CardTitle>
            </CardHeader>
            <CardContent>
                <DataTable
                    table-id="assigned-to-me"
                    :columns="columns"
                    :rows="tickets as unknown as Record<string, unknown>[]"
                    :loading="loading"
                    empty-message="No open tickets assigned to you."
                    pagination="server"
                    :page-size="pageSize"
                    :total="total"
                    :page="page"
                    :sort-key="sortKey"
                    :sort-dir="sortDir"
                    @sort="onSort"
                    @page-change="onPageChange"
                    @page-size-change="onPageSizeChange"
                    @row-click="(row) => router.push(`/tickets/${(row as unknown as Ticket).id}`)"
                >
                    <template #cell-created_name="{ row }">
                        <span class="text-sm">{{ (row as unknown as Ticket).created_name ?? '—' }}</span>
                    </template>
                    <template #cell-category_name="{ row }">
                        <span class="text-sm">{{ (row as unknown as Ticket).category_name ?? '—' }}</span>
                    </template>
                    <template #cell-created_date="{ value }">
                        <span class="text-sm text-muted-foreground">{{ formatDate(value as number) }}</span>
                    </template>
                </DataTable>
            </CardContent>
        </Card>
    </div>
</template>
