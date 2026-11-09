<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import DataTable from '@/components/common/DataTable.vue';
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
import { api, newIdempotencyKey } from '@/services/api';

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
const page = ref(1);
const pageSize = ref(40);
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

const columns = [
    { key: 'select', label: '', sortable: false, width: 48, resizable: false },
    { key: 'id', label: 'ID', sortable: false, width: 80, resizable: true },
    { key: 'subject', label: 'Subject', sortable: false, resizable: true },
    { key: 'created_uid', label: 'Created by', sortable: false, width: 180, resizable: true },
    { key: 'created_date', label: 'Created', sortable: false, width: 130, resizable: true },
    { key: 'actions', label: 'Actions', sortable: false, width: 260, resizable: true },
];

const formatDate = (ts: number): string => (ts ? new Date(ts * 1000).toLocaleDateString() : '—');

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    selected.value = new Set();
    try {
        const params = new URLSearchParams({
            indirect: String(indirect.value),
            hide_old: String(hideOld.value),
            page: String(page.value),
            per_page: String(pageSize.value),
        });
        const res = await api<Page>(`/tickets/by-subordinates?${params.toString()}`);
        tickets.value = res.tickets ?? [];
        total.value = res.total ?? 0;
        page.value = res.page ?? page.value;
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
        await api(`/tickets/${id}/approve`, { method: 'POST', body: JSON.stringify({}) }, newIdempotencyKey());
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed.';
    }
};

const undoOne = async (id: number): Promise<void> => {
    try {
        await api(`/tickets/${id}/undo-approve`, { method: 'POST' }, newIdempotencyKey());
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
            await api(
                `/tickets/${remarksTarget.value}/disapprove`,
                {
                    method: 'POST',
                    body: JSON.stringify({ remarks: remarksText.value.trim() }),
                },
                newIdempotencyKey(),
            );
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
                newIdempotencyKey(),
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
        const res = await api<{ success: number; message: string }>(
            '/tickets/batch-approve',
            {
                method: 'POST',
                body: JSON.stringify({ ids: Array.from(selected.value), remarks: '' }),
            },
            newIdempotencyKey(),
        );
        if (res.success > 0) clearSelection();
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed.';
    }
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
                    <input type="checkbox" v-model="indirect" @change="() => { page = 1; void load(); }" />
                    Include indirect subordinates
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="hideOld" @change="() => { page = 1; void load(); }" />
                    Hide old tickets
                </label>
                <span class="text-sm text-muted-foreground">{{ total }} pending</span>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle>Results</CardTitle>
                    <div class="flex flex-wrap gap-2">
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

                <DataTable
                    table-id="by-subordinates"
                    :columns="columns"
                    :rows="tickets as unknown as Record<string, unknown>[]"
                    :loading="loading"
                    empty-message="No pending tickets."
                    pagination="server"
                    :page-size="pageSize"
                    :total="total"
                    :page="page"
                    @page-change="onPageChange"
                    @page-size-change="onPageSizeChange"
                    @row-click="(row) => router.push(`/tickets/${(row as unknown as Ticket).id}`)"
                >
                    <template #cell-select="{ row }">
                        <div @click.stop>
                            <input
                                type="checkbox"
                                :checked="selected.has((row as unknown as Ticket).id)"
                                @change="toggleSelect((row as unknown as Ticket).id)"
                            />
                        </div>
                    </template>
                    <template #cell-id="{ value }">
                        <span class="font-mono text-sm">{{ value }}</span>
                    </template>
                    <template #cell-subject="{ row }">
                        <span class="hover:underline">{{ (row as unknown as Ticket).subject }}</span>
                    </template>
                    <template #cell-created_uid="{ row }">
                        <span class="text-sm">{{ (row as unknown as Ticket).created_name ?? (row as unknown as Ticket).created_uid }}</span>
                    </template>
                    <template #cell-created_date="{ value }">
                        <span class="text-sm text-muted-foreground">{{ formatDate(value as number) }}</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex flex-wrap gap-1" @click.stop>
                            <Button size="sm" @click="approveOne((row as unknown as Ticket).id)">Approve</Button>
                            <Button size="sm" variant="outline" @click="openDisapprove((row as unknown as Ticket).id)">Disapprove</Button>
                            <Button
                                v-if="(row as unknown as Ticket).approved_by_supervisor === 1"
                                size="sm"
                                variant="outline"
                                @click="undoOne((row as unknown as Ticket).id)"
                            >
                                Undo
                            </Button>
                        </div>
                    </template>
                </DataTable>
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
