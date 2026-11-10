<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { api } from '@/services/api';
import { useAuthStore } from '@/stores/auth';

interface Ticket {
    id: number;
    subject: string;
    state_id: number;
    created_date: number;
}

interface TicketCount {
    total: number;
    open: number;
    closed: number;
}

interface DashboardSummary {
    assigned_to_you: number;
    assigned_to_group: number;
    closed_by_you_today: number;
    closed_by_you_ytd: number;
    closed_by_group_today: number;
    your_avg_resolution_seconds: number | null;
    system_avg_resolution_seconds: number | null;
    all_open_system: number;
    recent: Ticket[];
    group_id?: number;
    department_id?: number;
    department_name?: string | null;
    by_department?: TicketCount;
    by_me?: TicketCount;
}

const auth = useAuthStore();

const summary = ref<DashboardSummary>({
    assigned_to_you: 0,
    assigned_to_group: 0,
    closed_by_you_today: 0,
    closed_by_you_ytd: 0,
    closed_by_group_today: 0,
    your_avg_resolution_seconds: null,
    system_avg_resolution_seconds: null,
    all_open_system: 0,
    recent: [],
});
const loading = ref(true);
const error = ref<string | null>(null);

const recent = computed(() => summary.value.recent ?? []);
const isRegularUser = computed(() => (auth.user?.acl ?? 1) === 1);

const stateLabel = (id: number): string => (id === 1 ? 'Open' : id === 2 ? 'Closed' : `State ${id}`);

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

const formatDuration = (seconds: number | null): string => {
    if (seconds === null || !Number.isFinite(seconds) || seconds < 0) return '—';
    const s = Math.floor(seconds);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return `${h}h ${m}m ${sec}s`;
};

const staffCards = computed(() => [
    { title: 'Assigned', subtitle: 'To you (open)', value: String(summary.value.assigned_to_you) },
    { title: 'Assigned', subtitle: 'To group (open)', value: String(summary.value.assigned_to_group) },
    { title: 'Closed by you', subtitle: 'Today', value: String(summary.value.closed_by_you_today) },
    { title: 'Closed by you', subtitle: 'This year (YTD)', value: String(summary.value.closed_by_you_ytd) },
    { title: 'Closed by', subtitle: 'Group today', value: String(summary.value.closed_by_group_today) },
    { title: 'Your average', subtitle: 'Resolution time', value: formatDuration(summary.value.your_avg_resolution_seconds) },
    { title: 'Average close time', subtitle: 'Entire system', value: formatDuration(summary.value.system_avg_resolution_seconds) },
    { title: 'All open tickets', subtitle: 'Entire system', value: String(summary.value.all_open_system) },
]);

const userCards = computed(() => {
    const dept: TicketCount = summary.value.by_department ?? { total: 0, open: 0, closed: 0 };
    const me: TicketCount = summary.value.by_me ?? { total: 0, open: 0, closed: 0 };
    return [
        { title: 'Your department', subtitle: 'Tickets', value: String(dept.total), meta: `Open: ${dept.open} · Closed: ${dept.closed}` },
        { title: 'Your department', subtitle: 'Open', value: String(dept.open), meta: `Closed: ${dept.closed} · Total: ${dept.total}` },
        { title: 'By you', subtitle: 'Tickets', value: String(me.total), meta: `Open: ${me.open} · Closed: ${me.closed}` },
        { title: 'By you', subtitle: 'Open', value: String(me.open), meta: `Closed: ${me.closed} · Total: ${me.total}` },
    ];
});

onMounted(async () => {
    try {
        summary.value = await api<DashboardSummary>('/tickets/summary');
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load dashboard.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <PageHeader
            title="Dashboard"
            :description="`Signed in as ${auth.user?.username ?? ''} (${auth.user?.fullname || '—'}).` + (isRegularUser && summary.department_name ? ` · Department: ${summary.department_name}` : '')"
        >
            <template #actions>
                <Button @click="$router.push('/tickets/new')">New ticket</Button>
            </template>
        </PageHeader>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <template v-if="loading">
                <Skeleton v-for="i in 4" :key="i" class="h-24 w-full" />
            </template>
            <template v-else-if="isRegularUser">
                <Card v-for="card in userCards" :key="`${card.title}-${card.subtitle}`">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs">{{ card.title }}</CardDescription>
                        <CardTitle class="text-2xl">{{ card.value }}</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <p class="text-xs text-muted-foreground">{{ card.subtitle }}</p>
                        <p class="text-xs text-muted-foreground">{{ card.meta }}</p>
                    </CardContent>
                </Card>
            </template>
            <template v-else>
                <Card v-for="card in staffCards" :key="`${card.title}-${card.subtitle}`">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs">{{ card.title }}</CardDescription>
                        <CardTitle class="text-2xl">{{ card.value }}</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <p class="text-xs text-muted-foreground">{{ card.subtitle }}</p>
                    </CardContent>
                </Card>
            </template>
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
                <p class="mt-3 text-xs text-muted-foreground">Recent tickets involving you.</p>
            </CardContent>
        </Card>
    </div>
</template>
