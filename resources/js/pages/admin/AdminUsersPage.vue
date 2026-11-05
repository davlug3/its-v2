<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    group_id: number | null;
    supervisor_id: number | null;
    deleted_at: string | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

const auth = useAuthStore();
const rows = ref<AdminUser[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const search = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);

const resetOpen = ref(false);
const resetTarget = ref<AdminUser | null>(null);
const resetPassword = ref('');
const resetConfirm = ref('');
const resetSaving = ref(false);
const resetError = ref<string | null>(null);

const canResetPassword = computed(() => auth.user?.acl === 9);

const fullName = (u: AdminUser): string =>
    [u.name, u.mname, u.lname].filter(Boolean).join(' ').trim() || '—';

const aclLabel = (acl: number | null): string => {
    if (acl === 9) return 'Admin';
    if (acl === 3) return 'Helpdesk';
    return 'End user';
};

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({ page: String(page.value) });
        if (search.value.trim() !== '') params.set('search', search.value.trim());
        const res = await api<Paginated<AdminUser>>(`/admin/users?${params.toString()}`);
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

const disableUser = async (user: AdminUser): Promise<void> => {
    if (!window.confirm(`Disable ${user.username}? This keeps their tickets.`)) return;
    try {
        await api(`/admin/users/${user.id}`, { method: 'DELETE' });
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to disable user.';
    }
};

const go = async (target: number): Promise<void> => {
    page.value = Math.min(Math.max(1, target), lastPage.value);
    await load();
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-subheading font-semibold text-foreground">Users</h1>

        <Card>
            <CardHeader>
                <CardTitle>Directory</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <form class="flex gap-2" @submit.prevent="go(1)">
                    <Input v-model="search" placeholder="Search username or name" />
                    <Button type="submit">Search</Button>
                </form>

                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>
                <p v-else-if="rows.length === 0" class="text-sm text-muted-foreground">
                    No users found.
                </p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-16">ID</TableHead>
                            <TableHead>User</TableHead>
                            <TableHead class="w-20">Dept</TableHead>
                            <TableHead class="w-36">Access</TableHead>
                            <TableHead class="w-64">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="u in rows" :key="u.id">
                            <TableCell class="font-mono text-sm">{{ u.id }}</TableCell>
                            <TableCell>
                                <div class="font-medium">{{ u.username }}</div>
                                <div class="text-xs text-muted-foreground">{{ fullName(u) }}</div>
                            </TableCell>
                            <TableCell>{{ u.department }}</TableCell>
                            <TableCell>
                                <select
                                    :value="u.acl_id ?? 1"
                                    class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                                    @change="updateAccess(u, Number(($event.target as HTMLSelectElement).value))"
                                >
                                    <option :value="1">End user</option>
                                    <option :value="3">Helpdesk</option>
                                    <option :value="9">Admin</option>
                                </select>
                                <Badge variant="outline" class="mt-1">{{ aclLabel(u.acl_id) }}</Badge>
                            </TableCell>
                            <TableCell class="space-x-2">
                                <Button
                                    v-if="canResetPassword"
                                    size="sm"
                                    variant="outline"
                                    @click="openReset(u)"
                                >
                                    Reset password
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    :disabled="u.deleted_at !== null"
                                    @click="disableUser(u)"
                                >
                                    {{ u.deleted_at !== null ? 'Disabled' : 'Disable' }}
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ total }} users</span>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" :disabled="page <= 1" @click="go(page - 1)">
                            Previous
                        </Button>
                        <span class="py-1">Page {{ page }} of {{ lastPage }}</span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="page >= lastPage"
                            @click="go(page + 1)"
                        >
                            Next
                        </Button>
                    </div>
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
    </div>
</template>