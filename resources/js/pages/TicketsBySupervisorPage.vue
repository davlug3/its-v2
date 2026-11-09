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
    created_date: number;
    resolved_date?: number;
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

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: 80, resizable: true },
    { key: 'subject', label: 'Subject', sortable: true, resizable: true },
    { key: 'category_name', label: 'Category', sortable: false, width: 140, resizable: true },
    { key: 'resolved_date', label: 'Resolved', sortable: true, width: 140, resizable: true },
];

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({ scope: 'tickets_by_supervisor', page: String(page.value), per_page: String(pageSize.value) });
        const res = await api<{ tickets: Ticket[]; total: number; page?: number }>('/tickets?' + params.toString());
        tickets.value = res.tickets ?? [];
        total.value = res.total ?? 0;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load tickets.';
    } finally {
        loading.value = false;
    }
};

const onPageChange = (p: number): void => { page.value = p; void load(); };
const onPageSizeChange = (size: number): void => { pageSize.value = size; page.value = 1; void load(); };

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="Tickets by supervisor" description="Closed self-assigned tickets created by your supervisor." />
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <Card>
            <CardHeader><CardTitle>Tickets</CardTitle></CardHeader>
            <CardContent>
                <DataTable table-id="tickets-by-supervisor" :columns="columns" :rows="tickets as unknown as Record<string, unknown>[]" :loading="loading" empty-message="No tickets." pagination="server" :page-size="pageSize" :total="total" :page="page" @page-change="onPageChange" @page-size-change="onPageSizeChange" @row-click="(row) => router.push(`/tickets/${(row as unknown as Ticket).id}`)">
                    <template #cell-category_name="{ row }"><span class="text-sm">{{ (row as unknown as Ticket).category_name ?? '—' }}</span></template>
                    <template #cell-resolved_date="{ value }"><span class="text-sm text-muted-foreground">{{ formatDate(value as number) }}</span></template>
                </DataTable>
            </CardContent>
        </Card>
    </div>
</template>
