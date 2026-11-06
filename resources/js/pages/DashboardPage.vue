<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
import StatCard from '@/components/common/StatCard.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { api } from '@/services/api';
import { useAuthStore } from '@/stores/auth';

interface Ticket {
    id: number;
    subject: string;
    state_id: number;
    created_date: number;
}

const auth = useAuthStore();

const tickets = ref<Ticket[]>([]);
const total = ref(0);
const loading = ref(true);
const error = ref<string | null>(null);

const counts = computed(() => ({
    open: tickets.value.filter((t) => t.state_id === 1).length,
    closed: tickets.value.filter((t) => t.state_id === 2).length,
}));

const recent = computed(() =>
    [...tickets.value].sort((a, b) => b.created_date - a.created_date).slice(0, 5),
);

const stateLabel = (id: number): string => (id === 1 ? 'Open' : id === 2 ? 'Closed' : `State ${id}`);

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

onMounted(async () => {
    try {
        const res = await api<{ tickets: Ticket[]; total: number }>('/tickets?page=1&per_page=100');
        tickets.value = res.tickets;
        total.value = res.total;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load dashboard.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="Dashboard" :description="`Signed in as ${auth.user?.username ?? ''} (${auth.user?.fullname || '—'}).`">
            <template #actions>
                <Button @click="$router.push('/tickets/new')">New ticket</Button>
            </template>
        </PageHeader>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label="Open" :value="counts.open" :loading="loading" />
            <StatCard label="Closed" :value="counts.closed" :loading="loading" />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Recent tickets</CardTitle>
                <CardDescription>Your latest activity.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="loading" class="space-y-2">
                    <Skeleton class="h-6 w-full" />
                    <Skeleton class="h-6 w-5/6" />
                    <Skeleton class="h-6 w-4/6" />
                </div>
                <p v-else-if="recent.length === 0" class="text-sm text-muted-foreground">
                    No tickets yet. <RouterLink to="/tickets/new" class="underline">Create one</RouterLink>.
                </p>
                <ul v-else class="divide-y divide-border">
                    <li v-for="t in recent" :key="t.id" class="flex items-center justify-between py-2">
                        <RouterLink :to="`/tickets/${t.id}`" class="min-w-0 truncate text-sm hover:underline">
                            #{{ t.id }} {{ t.subject }}
                        </RouterLink>
                        <div class="ml-3 flex shrink-0 items-center gap-3">
                            <span class="text-xs text-muted-foreground">{{ formatDate(t.created_date) }}</span>
                            <Badge :variant="t.state_id === 2 ? 'secondary' : 'outline'">
                                {{ stateLabel(t.state_id) }}
                            </Badge>
                        </div>
                    </li>
                </ul>
                <p class="mt-3 text-xs text-muted-foreground">{{ total }} tickets involve you.</p>
            </CardContent>
        </Card>
    </div>
</template>