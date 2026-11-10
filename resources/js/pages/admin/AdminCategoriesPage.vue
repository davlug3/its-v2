<script setup lang="ts">
import { onMounted, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataTable from '@/components/common/DataTable.vue';
import { api, newIdempotencyKey } from '@/services/api';
import { categoryNameSchema, type CategoryNameInput } from '@/schemas/categoryName';

interface Category {
    id: number;
    category: string;
    status: number;
}

const categories = ref<Category[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: 80, resizable: true },
    { key: 'category', label: 'Name', sortable: true, resizable: true },
    { key: 'actions', label: 'Actions', sortable: false, width: 180, resizable: true },
];

const newName = ref('');
const creating = ref(false);

const editingId = ref<number | null>(null);
const editingName = ref('');
const savingId = ref<number | null>(null);

const deleteOpen = ref(false);
const deleteTarget = ref<Category | null>(null);

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        categories.value = await api<Category[]>('/admin/categories');
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load categories.';
    } finally {
        loading.value = false;
    }
};

const create = async (): Promise<void> => {
    creating.value = true;
    error.value = null;
    const input: CategoryNameInput = { name: newName.value };
    try {
        await categoryNameSchema.validate(input, { abortEarly: false });
    } catch (e) {
        const err = e as { errors?: string[] };
        error.value = err.errors?.join(' ') ?? 'Please fix the errors below.';
        creating.value = false;
        return;
    }
    try {
        await api(
            '/admin/categories',
            { method: 'POST', body: JSON.stringify({ name: input.name.trim() }) },
            newIdempotencyKey(),
        );
        newName.value = '';
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to create category.';
    } finally {
        creating.value = false;
    }
};

const startEdit = (category: Category): void => {
    editingId.value = category.id;
    editingName.value = category.category;
};

const saveEdit = async (): Promise<void> => {
    if (editingId.value === null) return;
    savingId.value = editingId.value;
    error.value = null;
    const input: CategoryNameInput = { name: editingName.value };
    try {
        await categoryNameSchema.validate(input, { abortEarly: false });
    } catch (e) {
        const err = e as { errors?: string[] };
        error.value = err.errors?.join(' ') ?? 'Please fix the errors below.';
        savingId.value = null;
        return;
    }
    try {
        await api(
            `/admin/categories/${editingId.value}`,
            { method: 'PUT', body: JSON.stringify({ name: input.name.trim() }) },
            newIdempotencyKey(),
        );
        editingId.value = null;
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to update category.';
    } finally {
        savingId.value = null;
    }
};

const requestDelete = (category: Category): void => {
    deleteTarget.value = category;
    deleteOpen.value = true;
};

const confirmDelete = async (): Promise<void> => {
    if (deleteTarget.value === null) return;
    error.value = null;
    try {
        await api(`/admin/categories/${deleteTarget.value.id}`, { method: 'DELETE' }, newIdempotencyKey());
        deleteOpen.value = false;
        deleteTarget.value = null;
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to delete category.';
        deleteOpen.value = false;
    }
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="Categories" description="Create, rename, or remove ticket categories." />

        <Card>
            <CardHeader>
                <CardTitle>Add category</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="flex items-end gap-2" @submit.prevent="create">
                    <div class="flex-1 space-y-2">
                        <Label for="new-category">Name</Label>
                        <Input id="new-category" v-model="newName" maxlength="100" />
                    </div>
                    <Button type="submit" :disabled="creating">
                        {{ creating ? 'Adding…' : 'Add' }}
                    </Button>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Existing categories</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                <DataTable
                    table-id="admin-categories"
                    :columns="columns"
                    :rows="categories as unknown as Record<string, unknown>[]"
                    :loading="loading"
                    empty-message="No categories."
                    pagination="client"
                    :page-size="20"
                >
                    <template #cell-id="{ value }">
                        <span class="font-mono text-sm">{{ value }}</span>
                    </template>
                    <template #cell-category="{ row }">
                        <div @click.stop>
                            <Input
                                v-if="editingId === (row as unknown as Category).id"
                                v-model="editingName"
                                maxlength="100"
                            />
                            <span v-else>{{ (row as unknown as Category).category }}</span>
                        </div>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex gap-2" @click.stop>
                            <template v-if="editingId === (row as unknown as Category).id">
                                <Button size="sm" :disabled="savingId === (row as unknown as Category).id" @click="saveEdit">
                                    {{ savingId === (row as unknown as Category).id ? 'Saving…' : 'Save' }}
                                </Button>
                                <Button size="sm" variant="outline" @click="editingId = null">Cancel</Button>
                            </template>
                            <template v-else>
                                <Button size="sm" variant="outline" @click="startEdit(row as unknown as Category)">Edit</Button>
                                <Button size="sm" variant="destructive" @click="requestDelete(row as unknown as Category)">Delete</Button>
                            </template>
                        </div>
                    </template>
                </DataTable>
            </CardContent>
        </Card>

        <ConfirmDialog
            :open="deleteOpen"
            title="Delete category"
            :message="'Delete category ' + (deleteTarget?.category ?? '') + '?'"
            confirm-label="Delete"
            :destructive="true"
            @update:open="deleteOpen = $event"
            @confirm="confirmDelete"
            @cancel="deleteOpen = false"
        />
    </div>
</template>
