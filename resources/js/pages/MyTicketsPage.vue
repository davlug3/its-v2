<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
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
    location_id: number;
    created_uid: number;
    created_date: number;
    assigned_uid: number;
    preview: string;
}

const router = useRouter();
const tickets = ref<Ticket[]>([]);
const total = ref(0);
const loading = ref(true);
const error = ref<string | null>(null);

const stateLabel = (id: number): string => {
    if (id === 1) return 'Open';
    if (id === 2) return 'Closed';
    return `State ${id}`;
};

const stateVariant = (id: number): 'default' | 'secondary' | 'outline' => {
    if (id === 3) return 'secondary';
    if (id === 2) return 'default';
    return 'outline';
};

const formatDate = (ts: number): string => {
    if (!ts) return '—';
    return new Date(ts * 1000).toLocaleString();
};

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api<{ tickets: Ticket[]; total: number }>(
            '/tickets?page=1&per_page=50',
        );
        tickets.value = res.tickets ?? [];
        total.value = res.total ?? 0;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load tickets.';
    } finally {
        loading.value = false;
    }
};

onMounted(load);

const openTicket = (id: number): void => {
    void router.push(`/tickets/${id}`);
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-subheading font-semibold text-foreground">My tickets</h1>
            <Button @click="router.push('/tickets/new')">New ticket</Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Tickets involving you</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="loading" class="space-y-2">
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-8 w-full" />
                </div>
                <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
                <p v-else-if="tickets.length === 0" class="text-sm text-muted-foreground">
                    No tickets yet.
                </p>
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-20">ID</TableHead>
                            <TableHead>Subject</TableHead>
                            <TableHead class="w-32">State</TableHead>
                            <TableHead class="w-44">Created</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="t in tickets"
                            :key="t.id"
                            class="cursor-pointer"
                            @click="openTicket(t.id)"
                        >
                            <TableCell class="font-mono text-sm">{{ t.id }}</TableCell>
                            <TableCell>{{ t.subject }}</TableCell>
                            <TableCell>
                                <Badge :variant="stateVariant(t.state_id)">{{ stateLabel(t.state_id) }}</Badge>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ formatDate(t.created_date) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <p v-if="!loading && total > 0" class="mt-3 text-xs text-muted-foreground">
                    Showing {{ tickets.length }} of {{ total }}.
                </p>
            </CardContent>
        </Card>
    </div>
</template>