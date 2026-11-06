<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DataTable from '@/components/common/DataTable.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import { api } from '@/services/api';
import { useAuthStore } from '@/stores/auth';

interface AdminUser {
    id: number;
    username: string;
    name: string;
    mname: string;
    lname: string;
    department: number;
    status: string;
    acl_id: number | null;
    deleted_at: string | null;
}

interface Page {
    data: AdminUser[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

const auth = useAuthStore();

const rows = ref<AdminUser[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const search = ref('');
const aclFilter = ref('all');
const statusFilter = ref('all');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const pageSize = ref(40);

const sortKey = ref('name');
const sortDir = ref<'asc' | 'desc'>('asc');

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: 70, resizable: true },
    { key: 'username', label: 'User', sortable: true, resizable: true },
    { key: 'department', label: 'Dept', sortable: true, width: 70, resizable: true },
    { key: 'acl_id', label: 'Access', sortable: true, width: 150, resizable: true },
    { key: 'actions', label: 'Actions', sortable: false, width: 220, resizable: true },
];

const resetOpen = ref(false);
const resetTarget = ref<AdminUser | null>(null);
const resetPassword = ref('');
const resetConfirm = ref('');
const resetSaving = ref(false);
const resetError = ref<string | null>(null);

const disableOpen = ref(false);
const disableTarget = ref<AdminUser | null>(null);

const canResetPassword = computed(() => auth.user?.acl === 9);

const aclLabel = (acl: number | null): string => {
    if (acl === 9) return 'Admin';
    if (acl === 3) return 'Helpdesk';
    return 'End user';
};

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({ page: String(page.value), per_page: String(pageSize.value), sort: sortKey.value, dir: sortDir.value });
        if (search.value.trim() !== '') params.set('search', search.value.trim());
        if (aclFilter.value !== 'all') params.set('acl', aclFilter.value);
        if (statusFilter.value !== 'all') params.set('status', statusFilter.value);

        const res = await api<Page>(`/admin/users?${params.toString()}`);
        rows.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load users.';
    } finally {
        loading.value = false;
    }
};

const applyFilters = async (): Promise<void> => {
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

const updateAccess = async (user: AdminUser, acl: number): Promise<void> => {
    const previous = user.acl_id;
    user.acl_id = acl;
    try {
        await api(`/admin/users/${user.id}/access`, {
            method: 'PATCH',
            body: JSON.stringify({ acl_id: acl }),
        });
    } catch (e) {
        user.acl_id = previous;
        error.value = (e as { message?: string }).message ?? 'Failed to update access.';
    }
};

const openReset = (user: AdminUser): void => {
    resetTarget.value = user;
    resetPassword.value = '';
    resetConfirm.value = '';
    resetError.value = null;
    resetOpen.value = true;
};

const submitReset = async (): Promise<void> => {
    if (resetTarget.value === null) return;
    resetSaving.value = true;
    resetError.value = null;
    try {
        await api(`/admin/users/${resetTarget.value.id}/reset-password`, {
            method: 'POST',
            body: JSON.stringify({
                password: resetPassword.value,
                confirm: resetConfirm.value,
            }),
        });
        resetOpen.value = false;
    } catch (e) {
        resetError.value = (e as { message?: string }).message ?? 'Failed to reset password.';
    } finally {
        resetSaving.value = false;
    }
};

const requestDisable = (user: AdminUser): void => {
    disableTarget.value = user;
    disableOpen.value = true;
};

const confirmDisable = async (): Promise<void> => {
    if (disableTarget.value === null) return;
    try {
        await api(`/admin/users/${disableTarget.value.id}`, { method: 'DELETE' });
        disableOpen.value = false;
        disableTarget.value = null;
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to disable user.';
        disableOpen.value = false;
    }
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="Users" description="Manage access, supervisors, and passwords." />

        <Card>
            <CardHeader>
                <CardTitle>Directory</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
                    <Input v-model="search" placeholder="Search username or name" />
                    <select v-model="aclFilter" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                        <option value="all">All access</option>
                        <option value="1">End user</option>
                        <option value="3">Helpdesk</option>
                        <option value="9">Admin</option>
                    </select>
                    <select v-model="statusFilter" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                        <option value="all">All status</option>
                        <option value="A">Active</option>
                        <option value="I">Inactive</option>
                    </select>
                    <Button type="submit">Apply</Button>
                </form>

                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                <DataTable
                    table-id="admin-users"
                    :columns="columns"
                    :rows="rows"
                    :loading="loading"
                    empty-message="No users found."
                    pagination="server"
                    :page-size="pageSize"
                    :total="total"
                    :page="page"
                    :sort-key="sortKey"
                    :sort-dir="sortDir"
                    @sort="onSort"
                    @page-change="onPageChange"
                    @page-size-change="onPageSizeChange"
                >
                    <template #cell-username="{ row }">
                        <div class="font-medium">{{ (row as unknown as AdminUser).username }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ [ (row as unknown as AdminUser).name, (row as unknown as AdminUser).mname, (row as unknown as AdminUser).lname ].filter(Boolean).join(' ').trim() || '—' }}
                        </div>
                    </template>
                    <template #cell-acl_id="{ row }">
                        <div class="flex items-center gap-2">
                            <select
                                :value="(row as unknown as AdminUser).acl_id ?? 1"
                                class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                                @change="updateAccess(row as unknown as AdminUser, Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option :value="1">End user</option>
                                <option :value="3">Helpdesk</option>
                                <option :value="9">Admin</option>
                            </select>
                            <Badge variant="outline" class="whitespace-nowrap">
                                {{ aclLabel((row as unknown as AdminUser).acl_id) }}
                            </Badge>
                        </div>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex gap-2">
                            <Button
                                v-if="canResetPassword"
                                size="sm"
                                variant="outline"
                                @click="openReset(row as unknown as AdminUser)"
                            >
                                Reset password
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                :disabled="(row as unknown as AdminUser).deleted_at !== null"
                                @click="requestDisable(row as unknown as AdminUser)"
                            >
                                {{ (row as unknown as AdminUser).deleted_at !== null ? 'Disabled' : 'Disable' }}
                            </Button>
                        </div>
                    </template>
                </DataTable>

                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ total }} users</span>
                    <span>Page {{ page }} of {{ lastPage }}</span>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="resetOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reset password</DialogTitle>
                    <DialogDescription>
                        Set a new password for {{ resetTarget?.username }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-3">
                    <div class="space-y-2">
                        <Label for="reset-password">New password</Label>
                        <Input id="reset-password" v-model="resetPassword" type="password" />
                    </div>
                    <div class="space-y-2">
                        <Label for="reset-confirm">Confirm password</Label>
                        <Input id="reset-confirm" v-model="resetConfirm" type="password" />
                    </div>
                    <p v-if="resetError" class="text-sm text-destructive">{{ resetError }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="resetOpen = false">Cancel</Button>
                    <Button :disabled="resetSaving" @click="submitReset">
                        {{ resetSaving ? 'Saving…' : 'Reset password' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="disableOpen"
            title="Disable user"
            :message="`Disable ${disableTarget?.username ?? ''}? Their tickets are preserved.`"
            confirm-label="Disable"
            :destructive="true"
            @update:open="disableOpen = $event"
            @confirm="confirmDisable"
            @cancel="disableOpen = false"
        />
    </div>
</template>