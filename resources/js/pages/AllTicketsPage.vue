<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { api } from '@/services/api';

interface Ticket {
    id: number;
    subject: string;
    state_id: number;
    category_id: number;
    created_uid: number;
    created_date: number;
    assigned_uid: number;
    preview: string;
}

const router = useRouter();

const tickets = ref<Ticket[]>([]);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const error = ref<string | null>(null);

const search = ref('');
const stateFilter = ref('');
const assignedFilter = ref('');

const sortKey = ref('created');
const sortDir = ref<'asc' | 'desc'>('desc');

const toggleSort = (key: string): void => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
    page.value = 1;
    void load();
};

const sortIndicator = (key: string): string => {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? ' ▲' : ' ▼';
};

const stateLabel = (id: number): string => (id === 1 ? 'Open' : id === 2 ? 'Closed' : `State ${id}`);

const stateVariant = (id: number): 'default' | 'secondary' | 'outline' => (id === 3 ? 'secondary' : id === 2 ? 'default' : 'outline');

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({ scope: 'all', page: String(page.value), per_page: '40' });
        if (search.value.trim() !== '') params.set('search', search.value.trim());
        if (stateFilter.value !== '') params.set('state_id', stateFilter.value);
        if (assignedFilter.value.trim() !== '') params.set('assigned_uid', assignedFilter.value.trim());
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

const go = async (target: number): Promise<void> => {
    page.value = Math.min(Math.max(1, target), lastPage.value);
    await load();
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-subheading font-semibold text-foreground">All tickets</h1>

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
                        <select
                            id="state"
                            v-model="stateFilter"
                            class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                        >
                            <option value="">All</option>
                            <option value="1">Open</option>
                            <option value="2">Closed</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="assigned">Assigned user ID</Label>
                        <Input id="assigned" v-model="assignedFilter" />
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
                <div v-if="loading" class="space-y-2">
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-8 w-full" />
                </div>
                <p v-else-if="tickets.length === 0" class="text-sm text-muted-foreground">No tickets.</p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-20 cursor-pointer select-none" @click="toggleSort('id')">ID{{ sortIndicator('id') }}</TableHead>
                            <TableHead class="cursor-pointer select-none" @click="toggleSort('subject')">Subject{{ sortIndicator('subject') }}</TableHead>
                            <TableHead class="w-32 cursor-pointer select-none" @click="toggleSort('state')">State{{ sortIndicator('state') }}</TableHead>
                            <TableHead class="w-24 cursor-pointer select-none" @click="toggleSort('creator')">Created by{{ sortIndicator('creator') }}</TableHead>
                            <TableHead class="w-24 cursor-pointer select-none" @click="toggleSort('assigned')">Assigned{{ sortIndicator('assigned') }}</TableHead>
                            <TableHead class="w-28 cursor-pointer select-none" @click="toggleSort('created')">Created{{ sortIndicator('created') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="t in tickets"
                            :key="t.id"
                            class="cursor-pointer"
                            @click="router.push(`/tickets/${t.id}`)"
                        >
                            <TableCell class="font-mono text-sm">{{ t.id }}</TableCell>
                            <TableCell>{{ t.subject }}</TableCell>
                            <TableCell>
                                <Badge :variant="stateVariant(t.state_id)">{{ stateLabel(t.state_id) }}</Badge>
                            </TableCell>
                            <TableCell class="text-sm">{{ t.created_uid }}</TableCell>
                            <TableCell class="text-sm">{{ t.assigned_uid || '—' }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ formatDate(t.created_date) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ total }} tickets</span>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" :disabled="page <= 1" @click="go(page - 1)">
                            Previous
                        </Button>
                        <span class="py-1">Page {{ page }} of {{ lastPage }}</span>
                        <Button variant="outline" size="sm" :disabled="page >= lastPage" @click="go(page + 1)">
                            Next
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>