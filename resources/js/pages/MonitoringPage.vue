<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
        <div class="flex items-center justify-between">
            <h1 class="text-subheading font-semibold text-foreground">Monitoring</h1>
            <span class="text-xs text-muted-foreground">Last update: {{ lastUpdated || '—' }}</span>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Open tickets (live)</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-20">Ticket #</TableHead>
                            <TableHead class="w-40">Date/Time</TableHead>
                            <TableHead>Subject</TableHead>
                            <TableHead class="w-32">Location</TableHead>
                            <TableHead class="w-32">Mail</TableHead>
                            <TableHead class="w-32">Category</TableHead>
                            <TableHead class="w-32">Assigned</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="r in rows"
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