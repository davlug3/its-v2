<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
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
    issue?: string;
    state_id: number;
    category_id: number;
    category_name?: string | null;
    location_id?: number;
    location_name?: string | null;
    created_uid: number;
    created_name?: string | null;
    eu_uid?: number;
    eu_name?: string | null;
    created_date: number;
    assigned_uid: number;
    assigned_name?: string | null;
    resolved_uid?: number;
    resolved_name?: string | null;
}

const router = useRouter();

const tickets = ref<Ticket[]>([]);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const pageSize = ref(20);
const loading = ref(true);
const error = ref<string | null>(null);

const ticketId = ref('');
const euUid = ref<string | number | null>('');
const locationId = ref<string | number | null>('');
const categoryId = ref<string | number | null>('');
const subject = ref('');
const issue = ref('');
const closedBy = ref<string | number | null>('');
const stateFilter = ref<string | number | null>('');
const assignedFilter = ref<string | number | null>('');

const categories = ref<Array<{ id: number; category: string }>>([]);

const stateOptions: SelectOption[] = [
    { value: '', label: 'All' },
    { value: '1', label: 'Open' },
    { value: '2', label: 'Closed' },
];

const categoryOptions = computed<SelectOption[]>(() =>
    ([{ value: '', label: 'All categories' }] as SelectOption[]).concat(
        categories.value.map((c) => ({ value: c.id, label: c.category })),
    ),
);

const hasSearchFilters = computed(() => {
    const vals = [ticketId.value, subject.value, issue.value, euUid.value, locationId.value, categoryId.value, closedBy.value];
    return vals.some((v) => v !== null && v !== '' && String(v).toLowerCase() !== 'null');
});

const fetchUsers = async (q: string): Promise<SelectOption[]> => {
    const params = new URLSearchParams({ q, limit: '30' });
    const res = await api<Array<{ id: number; label: string }>>(`/admin/users/options?${params.toString()}`);
    return [{ value: '', label: 'All' } as SelectOption].concat(
        (res ?? []).map((u) => ({ value: u.id, label: u.label })),
    );
};

const fetchAssignees = async (q: string): Promise<SelectOption[]> => {
    const params = new URLSearchParams({ assignable: '1', q, limit: '30' });
    const res = await api<Array<{ id: number; label: string }>>(`/admin/users/options?${params.toString()}`);
    return [{ value: '', label: 'All' } as SelectOption].concat(
        (res ?? []).map((u) => ({ value: u.id, label: u.label })),
    );
};

const fetchLocations = async (q: string): Promise<SelectOption[]> => {
    const params = new URLSearchParams({ q, limit: '30' });
    const res = await api<Array<{ id: number; label: string }>>(`/locations/options?${params.toString()}`);
    return [{ value: '', label: 'All locations' } as SelectOption].concat(
        (res ?? []).map((l) => ({ value: l.id, label: l.label })),
    );
};

const sortKey = ref('created');
const sortDir = ref<'asc' | 'desc'>('desc');

const columns = computed(() => [
    { key: 'id', label: 'ID', sortable: true, width: 80, resizable: true },
    { key: 'subject', label: 'Subject', sortable: true, resizable: true },
    { key: 'state_id', label: 'State', sortable: true, width: 100, resizable: true },
    { key: 'created_name', label: 'Requester', sortable: false, width: 160, resizable: true },
    { key: 'location_name', label: 'Location', sortable: false, width: 160, resizable: true },
    { key: 'category_name', label: 'Category', sortable: false, width: 140, resizable: true },
    { key: 'assigned_name', label: 'Assigned', sortable: false, width: 150, resizable: true },
    { key: 'created_date', label: 'Created', sortable: true, width: 120, resizable: true },
]);

const stateLabel = (id: number): string => (id === 1 ? 'Open' : id === 2 ? 'Closed' : `State ${id}`);
const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

const setParam = (params: URLSearchParams, key: string, value: string | number | null): void => {
    if (value === null || value === '' || String(value).toLowerCase() === 'null') return;
    params.set(key, String(value));
};

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({ scope: 'all', page: String(page.value), per_page: String(pageSize.value) });
        setParam(params, 'ticket_id', ticketId.value.trim());
        setParam(params, 'subject', subject.value.trim());
        setParam(params, 'issue', issue.value.trim());
        setParam(params, 'state_id', stateFilter.value);
        setParam(params, 'assigned_uid', assignedFilter.value);
        setParam(params, 'eu_uid', euUid.value);
        setParam(params, 'location_id', locationId.value);
        setParam(params, 'category_id', categoryId.value);
        setParam(params, 'resolved_uid', closedBy.value);
        params.set('sort', sortKey.value);
        params.set('dir', sortDir.value);

        const res = await api<{ tickets: Ticket[]; total: number; last_page?: number; current_page?: number }>(
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

const reset = async (): Promise<void> => {
    ticketId.value = '';
    euUid.value = '';
    locationId.value = '';
    categoryId.value = '';
    subject.value = '';
    issue.value = '';
    closedBy.value = '';
    stateFilter.value = '';
    assignedFilter.value = '';
    page.value = 1;
    await load();
};

const printResults = (): void => {
    window.print();
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

const loadCategories = async (): Promise<void> => {
    try {
        categories.value = await api<Array<{ id: number; category: string }>>('/categories');
    } catch {
        categories.value = [];
    }
};

onMounted(() => {
    void loadCategories();
    void load();
});
</script>

<template>
    <div class="space-y-4">
        <PageHeader :title="hasSearchFilters ? 'Search Results' : 'All tickets'" description="Search and filter tickets.">
            <template #actions>
                <Button variant="outline" @click="printResults">Print results</Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-3" @submit.prevent="apply">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-1">
                            <Label for="ticket_id">Ticket ID</Label>
                            <Input id="ticket_id" v-model="ticketId" placeholder="ID" />
                        </div>
                        <div class="space-y-1">
                            <Label>End User</Label>
                            <SearchableSelect v-model="euUid" :options="[{ value: '', label: 'All' }]" :fetch-options="fetchUsers" placeholder="All" />
                        </div>
                        <div class="space-y-1">
                            <Label>Location</Label>
                            <SearchableSelect v-model="locationId" :options="[{ value: '', label: 'All locations' }]" :fetch-options="fetchLocations" placeholder="All locations" />
                        </div>
                        <div class="space-y-1">
                            <Label>Category</Label>
                            <SearchableSelect v-model="categoryId" :options="categoryOptions" placeholder="All categories" />
                        </div>
                        <div class="space-y-1">
                            <Label for="subject">Subject</Label>
                            <Input id="subject" v-model="subject" placeholder="Subject contains…" />
                        </div>
                        <div class="space-y-1">
                            <Label for="issue">Issue</Label>
                            <Input id="issue" v-model="issue" placeholder="Issue contains…" />
                        </div>
                        <div class="space-y-1">
                            <Label>Closed By</Label>
                            <SearchableSelect v-model="closedBy" :options="[{ value: '', label: 'All' }]" :fetch-options="fetchAssignees" placeholder="All" />
                        </div>
                        <div class="space-y-1">
                            <Label>State</Label>
                            <SearchableSelect v-model="stateFilter" :options="stateOptions" placeholder="All" />
                        </div>
                        <div class="space-y-1">
                            <Label>Assigned to</Label>
                            <SearchableSelect v-model="assignedFilter" :options="[{ value: '', label: 'All' }]" :fetch-options="fetchAssignees" placeholder="All" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit">Search</Button>
                        <Button type="button" variant="outline" @click="reset">Reset</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>{{ hasSearchFilters ? 'Search Results' : 'Results' }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                <DataTable
                    table-id="all-tickets"
                    :columns="columns"
                    :rows="tickets as unknown as Record<string, unknown>[]"
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
                    @row-click="(row) => router.push(`/tickets/${(row as unknown as Ticket).id}`)"
                >
                    <template #cell-state_id="{ value }">
                        <Badge :variant="value === 2 ? 'secondary' : 'outline'">{{ stateLabel(value as number) }}</Badge>
                    </template>
                    <template #cell-created_name="{ row }">
                        <span class="text-sm">{{ (row as unknown as Ticket).created_name ?? (row as unknown as Ticket).eu_name ?? ((row as unknown as Ticket).created_uid ? 'Deleted user' : '—') }}</span>
                    </template>
                    <template #cell-location_name="{ row }">
                        <span class="text-sm">{{ (row as unknown as Ticket).location_name ?? ((row as unknown as Ticket).location_id ? 'Unknown location' : '—') }}</span>
                    </template>
                    <template #cell-category_name="{ row }">
                        <span class="text-sm">{{ (row as unknown as Ticket).category_name ?? ((row as unknown as Ticket).category_id ? 'Unknown category' : '—') }}</span>
                    </template>
                    <template #cell-assigned_name="{ row }">
                        <span class="text-sm">{{ (row as unknown as Ticket).assigned_name ?? ((row as unknown as Ticket).assigned_uid ? 'Deleted user' : '—') }}</span>
                    </template>
                    <template #cell-created_date="{ value }">
                        <span class="text-sm text-muted-foreground">{{ formatDate(value as number) }}</span>
                    </template>
                </DataTable>
            </CardContent>
        </Card>
    </div>
</template>
