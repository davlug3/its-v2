<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
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
const lastUpdated = ref<string>('');

const sortKey = ref('created_date');
const sortDir = ref<'asc' | 'desc'>('desc');

const sortedRows = computed(() => {
    const arr = [...rows.value];
    arr.sort((a, b) => {
        const av = (a as Record<string, unknown>)[sortKey.value];
        const bv = (b as Record<string, unknown>)[sortKey.value];
        if (av === bv) return 0;
        const cmp = av! < bv! ? -1 : 1;
        return sortDir.value === 'asc' ? cmp : -cmp;
    });
    return arr;
});

const toggleSort = (key: string): void => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
};

const sortIndicator = (key: string): string => {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? ' ▲' : ' ▼';
};

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
                <div v-if="loading" class="space-y-2">
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-8 w-full" />
                </div>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-20 cursor-pointer select-none" @click="toggleSort('id')">Ticket #{{ sortIndicator('id') }}</TableHead>
                            <TableHead class="w-40 cursor-pointer select-none" @click="toggleSort('created_date')">Date/Time{{ sortIndicator('created_date') }}</TableHead>
                            <TableHead class="cursor-pointer select-none" @click="toggleSort('subject')">Subject{{ sortIndicator('subject') }}</TableHead>
                            <TableHead class="w-32 cursor-pointer select-none" @click="toggleSort('location')">Location{{ sortIndicator('location') }}</TableHead>
                            <TableHead class="w-32 cursor-pointer select-none" @click="toggleSort('mail')">Mail{{ sortIndicator('mail') }}</TableHead>
                            <TableHead class="w-32 cursor-pointer select-none" @click="toggleSort('category')">Category{{ sortIndicator('category') }}</TableHead>
                            <TableHead class="w-32 cursor-pointer select-none" @click="toggleSort('assigned')">Assigned{{ sortIndicator('assigned') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="r in sortedRows"
                            :key="r.id"
                            class="cursor-pointer"
                            @click="router.push(`/tickets/${r.id}`)"
                        >
                            <TableCell class="font-mono text-sm">{{ r.id }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{ formatDate(r.created_date) }}</TableCell>
                            <TableCell>{{ r.subject }}</TableCell>
                            <TableCell class="text-sm">{{ r.location }}</TableCell>
                            <TableCell class="text-sm">{{ r.mail }}</TableCell>
                            <TableCell class="text-sm">{{ r.category }}</TableCell>
                            <TableCell class="text-sm">{{ r.assigned || '—' }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <p class="text-xs text-muted-foreground">{{ rows.length }} open tickets.</p>
            </CardContent>
        </Card>
    </div>
</template>