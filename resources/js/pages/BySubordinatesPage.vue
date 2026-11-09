<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    created_uid: number;
    created_name?: string | null;
    created_date: number;
    assigned_uid: number;
    assigned_name?: string | null;
    approved_by_supervisor: number;
}

interface Page {
    tickets: Ticket[];
    total: number;
    page: number;
    per_page: number;
}

const router = useRouter();

const tickets = ref<Ticket[]>([]);
const total = ref(0);

const sortKey = ref('created_date');
const sortDir = ref<'asc' | 'desc'>('desc');

const sortedTickets = computed(() => {
    const arr = [...tickets.value];
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
const page = ref(1);
const lastPage = ref(1);
const loading = ref(true);
const error = ref<string | null>(null);

const indirect = ref(false);
const hideOld = ref(true);

const selected = ref<Set<number>>(new Set());
const selectedCount = computed(() => selected.value.size);

const remarksOpen = ref(false);
const remarksMode = ref<'single' | 'batch'>('single');
const remarksTarget = ref<number | null>(null);
const remarksText = ref('');
const remarksSaving = ref(false);
const remarksError = ref<string | null>(null);

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    selected.value = new Set();
    try {
        const params = new URLSearchParams({
            indirect: String(indirect.value),
            hide_old: String(hideOld.value),
            page: String(page.value),
            per_page: '40',
        });
        const res = await api<Page>(`/tickets/by-subordinates?${params.toString()}`);
        tickets.value = res.tickets ?? [];
        total.value = res.total ?? 0;
        page.value = res.page ?? page.value;
        lastPage.value = Math.max(1, Math.ceil(total.value / (res.per_page || 40)));
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load.';
    } finally {
        loading.value = false;
    }
};

const toggleSelect = (id: number): void => {
    const next = new Set(selected.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selected.value = next;
};

const selectAll = (): void => {
    selected.value = new Set(tickets.value.map((t) => t.id));
};

const clearSelection = (): void => {
    selected.value = new Set();
};

const approveOne = async (id: number): Promise<void> => {
    try {
        await api(`/tickets/${id}/approve`, { method: 'POST', body: JSON.stringify({}) });
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed.';
    }
};

const undoOne = async (id: number): Promise<void> => {
    try {
        await api(`/tickets/${id}/undo-approve`, { method: 'POST' });
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed.';
    }
};

const openDisapprove = (id: number | null): void => {
    remarksMode.value = id === null ? 'batch' : 'single';
    remarksTarget.value = id;
    remarksText.value = '';
    remarksError.value = null;
    remarksOpen.value = true;
};

const submitDisapprove = async (): Promise<void> => {
    if (remarksText.value.trim() === '') {
        remarksError.value = 'Remarks are required.';
        return;
    }
    remarksSaving.value = true;
    remarksError.value = null;
    try {
        if (remarksMode.value === 'single' && remarksTarget.value !== null) {
            await api(`/tickets/${remarksTarget.value}/disapprove`, {
                method: 'POST',
                body: JSON.stringify({ remarks: remarksText.value.trim() }),
            });
        } else {
            const res = await api<{ success: number; message: string }>(
                '/tickets/batch-disapprove',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        ids: Array.from(selected.value),
                        remarks: remarksText.value.trim(),
                    }),
                },
            );
            if (res.success === 0) {
                remarksError.value = res.message;
                remarksSaving.value = false;
                return;
            }
        }
        remarksOpen.value = false;
        await load();
    } catch (e) {
        remarksError.value = (e as { message?: string }).message ?? 'Failed.';
    } finally {
        remarksSaving.value = false;
    }
};

const batchApproveOpen = ref(false);

const requestBatchApprove = (): void => {
    if (selected.value.size === 0) return;
    batchApproveOpen.value = true;
};

const confirmBatchApprove = async (): Promise<void> => {
    batchApproveOpen.value = false;
    try {
        const res = await api<{ success: number; message: string }>('/tickets/batch-approve', {
            method: 'POST',
            body: JSON.stringify({ ids: Array.from(selected.value), remarks: '' }),
        });
        if (res.success > 0) clearSelection();
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed.';
    }
};

const go = async (target: number): Promise<void> => {
    page.value = Math.min(Math.max(1, target), lastPage.value);
    await load();
};

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="Tickets by subordinates" description="Review and approve subordinate tickets." />

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap items-end gap-4">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="indirect" @change="load" />
                    Include indirect subordinates
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="hideOld" @change="load" />
                    Hide old tickets
                </label>
                <span class="text-sm text-muted-foreground">{{ total }} pending</span>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Results</CardTitle>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" @click="selectAll">Check all</Button>
                        <Button size="sm" variant="outline" @click="clearSelection">Clear</Button>
                        <Button size="sm" :disabled="selectedCount === 0" @click="requestBatchApprove">
                            Approve selected ({{ selectedCount }})
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            :disabled="selectedCount === 0"
                            @click="openDisapprove(null)"
                        >
                            Disapprove selected
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>
                <p v-else-if="tickets.length === 0" class="text-sm text-muted-foreground">
                    No pending tickets.
                </p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-10"></TableHead>
                            <TableHead class="w-20 cursor-pointer select-none" @click="toggleSort('id')">ID{{ sortIndicator('id') }}</TableHead>
                            <TableHead class="cursor-pointer select-none" @click="toggleSort('subject')">Subject{{ sortIndicator('subject') }}</TableHead>
                            <TableHead class="w-24 cursor-pointer select-none" @click="toggleSort('created_uid')">Created by{{ sortIndicator('created_uid') }}</TableHead>
                            <TableHead class="w-28 cursor-pointer select-none" @click="toggleSort('created_date')">Created{{ sortIndicator('created_date') }}</TableHead>
                            <TableHead class="w-48">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="t in sortedTickets" :key="t.id">
                            <TableCell>
                                <input
                                    type="checkbox"
                                    :checked="selected.has(t.id)"
                                    @change="toggleSelect(t.id)"
                                />
                            </TableCell>
                            <TableCell class="font-mono text-sm">{{ t.id }}</TableCell>
                            <TableCell>
                                <a class="hover:underline" @click.prevent="router.push(`/tickets/${t.id}`)">
                                    {{ t.subject }}
                                </a>
                            </TableCell>
                            <TableCell class="text-sm">{{ t.created_name ?? t.created_uid }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{ formatDate(t.created_date) }}</TableCell>
                            <TableCell class="space-x-2">
                                <Button size="sm" @click="approveOne(t.id)">Approve</Button>
                                <Button size="sm" variant="outline" @click="openDisapprove(t.id)">Disapprove</Button>
                                <Button
                                    v-if="t.approved_by_supervisor === 1"
                                    size="sm"
                                    variant="outline"
                                    @click="undoOne(t.id)"
                                >
                                    Undo
                                </Button>
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

        <Dialog v-model:open="remarksOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Disapprove ticket</DialogTitle>
                    <DialogDescription>Remarks are required.</DialogDescription>
                </DialogHeader>
                <div class="space-y-3">
                    <Label for="remarks">Remarks</Label>
                    <Textarea id="remarks" v-model="remarksText" rows="4" />
                    <p v-if="remarksError" class="text-sm text-destructive">{{ remarksError }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="remarksOpen = false">Cancel</Button>
                    <Button :disabled="remarksSaving" @click="submitDisapprove">
                        {{ remarksSaving ? 'Saving…' : 'Disapprove' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="batchApproveOpen"
            title="Approve selected"
            :message="`Approve ${selectedCount} selected ticket(s)?`"
            confirm-label="Approve"
            @update:open="batchApproveOpen = $event"
            @confirm="confirmBatchApprove"
            @cancel="batchApproveOpen = false"
        />
    </div>
</template>