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
    mail: string | number; // requester (legacy field name)
    category: string | number;
    assigned: string | number;
}

const POLL_MS = 5000; // D51: 5-second cadence (configurable)

const router = useRouter();
const rows = ref<Row[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const lastUpdated = ref('');
let inFlight = false;

const columns = [
    { key: 'id', label: 'Ticket #', sortable: true, width: 90, resizable: true },
    { key: 'created_date', label: 'Date/Time', sortable: true, width: 170, resizable: true },
    { key: 'subject', label: 'Subject', sortable: true, resizable: true },
    { key: 'location', label: 'Location', sortable: true, width: 140, resizable: true },
    { key: 'mail', label: 'Requester', sortable: true, width: 160, resizable: true },
    { key: 'category', label: 'Category', sortable: true, width: 140, resizable: true },
    { key: 'assigned', label: 'Assigned Personnel', sortable: true, width: 160, resizable: true },
];

let timer: ReturnType<typeof setInterval> | null = null;

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleString() : '—');

const load = async (quiet = false): Promise<void> => {
    if (inFlight) return;
    inFlight = true;
    if (!quiet) loading.value = rows.value.length === 0;
    try {
        const next = await api<Row[]>('/monitoring/tickets');
        // D51: refresh data in place; keep the grid instance.
        rows.value = next;
        lastUpdated.value = new Date().toLocaleTimeString();
        error.value = null;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load.';
    } finally {
        loading.value = false;
        inFlight = false;
    }
};

const stopPolling = (): void => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
};

const startPolling = (): void => {
    if (document.visibilityState !== 'visible') return;
    if (!timer) timer = setInterval(() => void load(true), POLL_MS);
};

const onVisibility = (): void => {
    if (document.visibilityState === 'visible') {
        void load(true);
        startPolling();
    } else {
        stopPolling();
    }
};

const onFocus = (): void => {
    if (document.visibilityState === 'visible') {
        void load(true);
        startPolling();
    }
};

onMounted(() => {
    void load(false);
    startPolling();
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('focus', onFocus);
});

onUnmounted(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', onVisibility);
    window.removeEventListener('focus', onFocus);
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
