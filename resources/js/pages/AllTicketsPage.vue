<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import type { SelectOption } from '@/components/common/SearchableSelect.vue';
import DataTable from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { api } from '@/services/api';

interface Ticket {
    id: number;
    subject: string;
    state_id: number;
    category_id: number;
    category_name?: string | null;
    created_uid: number;
    created_name?: string | null;
    created_date: number;
    assigned_uid: number;
    assigned_name?: string | null;
}

const router = useRouter();

const tickets = ref<Ticket[]>([]);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const pageSize = ref(20);
const loading = ref(true);
const error = ref<string | null>(null);

const search = ref('');
const stateFilter = ref<string | number | null>('');
const assignedFilter = ref<string | number | null>('');

const stateOptions: SelectOption[] = [
    { value: '', label: 'All' },
    { value: '1', label: 'Open' },
    { value: '2', label: 'Closed' },
];

const fetchAssignees = async (q: string): Promise<SelectOption[]> => {
    const params = new URLSearchParams({ assignable: '1', q, limit: '30' });
    const res = await api<Array<{ id: number; label: string }>>(`/admin/users/options?${params.toString()}`);
    return [{ value: '', label: 'All' } as SelectOption].concat(
        (res ?? []).map((u) => ({ value: String(u.id), label: u.label })),
    );
};



const sortKey = ref('created');
const sortDir = ref<'asc' | 'desc'>('desc');

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: 80, resizable: true },
    { key: 'subject', label: 'Subject', sortable: true, resizable: true },
    { key: 'state_id', label: 'State', sortable: true, width: 110, resizable: true },
    { key: 'created_uid', label: 'Created by', sortable: true, width: 100, resizable: true },
    { key: 'assigned_uid', label: 'Assigned', sortable: true, width: 100, resizable: true },
    { key: 'created_date', label: 'Created', sortable: true, width: 130, resizable: true },
];

const stateLabel = (id: number): string => (id === 1 ? 'Open' : id === 2 ? 'Closed' : `State ${id}`);

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({ scope: 'all', page: String(page.value), per_page: String(pageSize.value) });
        if (search.value.trim() !== '') params.set('search', search.value.trim());
        if (stateFilter.value !== null && stateFilter.value !== '') params.set('state_id', String(stateFilter.value));
        if (assignedFilter.value !== null && assignedFilter.value !== '') params.set('assigned_uid', String(assignedFilter.value));
        params.set('sort', sortKey.value);
        params.set('dir', sortDir.value);

        const res = await api<{ tickets: Ticket[]; total: number; last_page: number; current_page: number }>(
            `/tickets?${params.toString()}`,
        );
        tickets.value = res.tickets ?? [];
        total.value = res.total ?? 0;
        page.value = res.current_page ?? page.value;
        lastPage.value = res.last_page ?? lastPage.value;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load tickets.';
    } finally {
        loading.value = false;
    }
};

const apply = async (): Promise<void> => {
    page.value = 1;
    await load();
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

onMounted(() => {
    void load();
});
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="All tickets" description="Browse and filter every ticket." />

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid grid-cols-1 gap-3 sm:grid-cols-4" @submit.prevent="apply">
                    <div class="space-y-2">
                        <Label for="search">Subject</Label>
                        <Input id="search" v-model="search" placeholder="Search" />
                    </div>
                    <div class="space-y-2">
                        <Label for="state">State</Label>
                        <SearchableSelect id="state" v-model="stateFilter" :options="stateOptions" placeholder="All" />
                    </div>
                    <div class="space-y-2">
                        <Label for="assigned">Assigned to</Label>
                        <SearchableSelect
                            id="assigned"
                            v-model="assignedFilter"
                            :options="[{ value: '', label: 'All' }]"
                            :fetch-options="fetchAssignees"
                            placeholder="All"
                        />
                    </div>
                    <div class="flex items-end">
                        <Button type="submit">Apply</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Results</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                <DataTable
                    table-id="all-tickets"
                    :columns="columns"
                    :rows="tickets"
                    :loading="loading"
                    empty-message="No tickets."
                    pagination="server"
                    :page-size="pageSize"
                    :total="total"
                    :page="page"
                    :sort-key="sortKey"
                    :sort-dir="sortDir"
                    @sort="onSort"
                    @page-change="onPageChange"
                    @page-size-change="onPageSizeChange"
                    @row-click="(row) => router.push(`/tickets/${row.id}`)"
                >
                    <template #cell-state_id="{ value }">
                        <Badge :variant="value === 2 ? 'secondary' : 'outline'">{{ stateLabel(value as number) }}</Badge>
                    </template>
                    <template #cell-created_uid="{ row }">
                        <span class="text-sm">{{ (row as unknown as Ticket).created_name ?? (row as unknown as Ticket).created_uid }}</span>
                    </template>
                    <template #cell-assigned_uid="{ row }">
                        <span class="text-sm">{{ (row as unknown as Ticket).assigned_name ?? ((row as unknown as Ticket).assigned_uid || '—') }}</span>
                    </template>
                    <template #cell-created_date="{ value }">
                        <span class="text-sm text-muted-foreground">{{ formatDate(value as number) }}</span>
                    </template>
                </DataTable>
            </CardContent>
        </Card>
    </div>
</template>