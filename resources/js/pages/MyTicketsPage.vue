<script setup lang="ts">
import { onMounted, ref , shallowRef } from 'vue';
import { useRouter } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DataTable from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import TicketListFilters from '@/components/common/TicketListFilters.vue';
import { api } from '@/services/api';

interface Ticket {
    id: number;
    subject: string;
    state_id: number;
    created_date: number;
}

const router = useRouter();

const tickets = ref<Ticket[]>([]);
const total = shallowRef(0);
const loading = shallowRef(true);
const error = shallowRef<string | null>(null);
const searchQuery = shallowRef('');

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: 80, resizable: true },
    { key: 'subject', label: 'Subject', sortable: true, resizable: true },
    { key: 'state_id', label: 'State', sortable: true, width: 120, resizable: true },
    { key: 'created_date', label: 'Created', sortable: true, width: 140, resizable: true },
];

const onSearch = (query: string): void => {
    searchQuery.value = query;
    void load();
};

const onReset = (): void => {
    searchQuery.value = '';
    void load();
};

const stateLabel = (id: number): string =>
    id === 1 ? 'Open' : id === 2 ? 'Closed' : `State ${id}`;

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({ page: '1', per_page: '50' });
        if (searchQuery.value.trim()) params.set('q', searchQuery.value.trim());
        const res = await api<{ tickets: Ticket[]; total: number }>(`/tickets?${params.toString()}`);
        tickets.value = res.tickets ?? [];
        total.value = res.total ?? 0;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load tickets.';
    } finally {
        loading.value = false;
    }
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="My tickets">
            <template #actions>
                <Button @click="router.push('/tickets/new')">New ticket</Button>
            </template>
        </PageHeader>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <Card>
            <CardContent class="space-y-4 pt-6">
                <TicketListFilters
                    search-placeholder="Search my tickets…"
                    @search="onSearch"
                    @reset="onReset"
                />
                <DataTable
                    table-id="my-tickets"
                    :columns="columns"
                    :rows="tickets"
                    :loading="loading"
                    empty-message="No tickets yet."
                    pagination="client"
                    :page-size="10"
                    @row-click="(row) => router.push(`/tickets/${row.id}`)"
                >
                    <template #cell-state_id="{ value }">
                        <Badge :variant="value === 2 ? 'secondary' : 'outline'">{{
                            stateLabel(value as number)
                        }}</Badge>
                    </template>
                    <template #cell-created_date="{ value }">
                        <span class="text-sm text-muted-foreground">{{
                            formatDate(value as number)
                        }}</span>
                    </template>
                </DataTable>
                <p v-if="!loading && total > 0" class="mt-3 text-xs text-muted-foreground">
                    Showing {{ tickets.length }} of {{ total }}.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
