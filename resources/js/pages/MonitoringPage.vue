<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
import DataTable from '@/components/common/DataTable.vue';
import { api } from '@/services/api';

interface Row {
    id: number;
    created_date: number;
    subject: string;
    location: string | number;
    mail: string | number;
    category: string | number;
    assigned: string | number;
}

const router = useRouter();
const rows = ref<Row[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const lastUpdated = ref('');

const columns = [
    { key: 'id', label: 'Ticket #', sortable: true, width: 90, resizable: true },
    { key: 'created_date', label: 'Date/Time', sortable: true, width: 170, resizable: true },
    { key: 'subject', label: 'Subject', sortable: true, resizable: true },
    { key: 'location', label: 'Location', sortable: true, width: 140, resizable: true },
    { key: 'mail', label: 'Mail', sortable: true, width: 140, resizable: true },
    { key: 'category', label: 'Category', sortable: true, width: 140, resizable: true },
    { key: 'assigned', label: 'Assigned', sortable: true, width: 140, resizable: true },
];

let timer: ReturnType<typeof setInterval> | null = null;

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleString() : '—');

const load = async (): Promise<void> => {
    try {
        rows.value = await api<Row[]>('/monitoring/tickets');
        lastUpdated.value = new Date().toLocaleTimeString();
        error.value = null;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load.';
    } finally {
        loading.value = false;
    }
};

const onVisibility = (): void => {
    if (document.visibilityState === 'visible') {
        void load();
        if (!timer) timer = setInterval(() => void load(), 5000);
    } else {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }
};

onMounted(() => {
    void load();
    timer = setInterval(() => void load(), 5000);
    document.addEventListener('visibilitychange', onVisibility);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisibility);
});
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="Monitoring" description="Live open-ticket grid.">
            <template #actions>
                <span class="text-xs text-muted-foreground">Last update: {{ lastUpdated || '—' }}</span>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <CardTitle>Open tickets (live)</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                <DataTable
                    table-id="monitoring-tickets"
                    :columns="columns"
                    :rows="rows as unknown as Record<string, unknown>[]"
                    :loading="loading"
                    empty-message="No open tickets."
                    pagination="client"
                    :page-size="20"
                    @row-click="(row) => router.push(`/tickets/${row.id}`)"
                >
                    <template #cell-id="{ value }">
                        <span class="font-mono text-sm">{{ value }}</span>
                    </template>
                    <template #cell-created_date="{ value }">
                        <span class="text-sm text-muted-foreground">{{ formatDate(value as number) }}</span>
                    </template>
                    <template #cell-assigned="{ value }">
                        <span class="text-sm">{{ (value as string | number) || '—' }}</span>
                    </template>
                </DataTable>

                <p class="text-xs text-muted-foreground">{{ rows.length }} open tickets.</p>
            </CardContent>
        </Card>
    </div>
</template>
