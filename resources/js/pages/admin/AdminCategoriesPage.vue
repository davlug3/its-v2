<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

interface Category {
    id: number;
    category: string;
    status: number;
}

const categories = ref<Category[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const sortKey = ref('category');
const sortDir = ref<'asc' | 'desc'>('asc');

const sortedCategories = computed(() => {
    const arr = [...categories.value];
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
        sortDir.value = 'asc';
    }
};

const sortIndicator = (key: string): string => {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? ' ▲' : ' ▼';
};

const newName = ref('');
const creating = ref(false);

const editingId = ref<number | null>(null);
const editingName = ref('');
const savingId = ref<number | null>(null);

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
    if (newName.value.trim() === '') return;
    creating.value = true;
    error.value = null;
    try {
        await api('/admin/categories', {
            method: 'POST',
            body: JSON.stringify({ name: newName.value.trim() }),
        });
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
    try {
        await api(`/admin/categories/${editingId.value}`, {
            method: 'PUT',
            body: JSON.stringify({ name: editingName.value.trim() }),
        });
        editingId.value = null;
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to update category.';
    } finally {
        savingId.value = null;
    }
};

const remove = async (category: Category): Promise<void> => {
    if (!window.confirm(`Delete category "${category.category}"?`)) return;
    error.value = null;
    try {
        await api(`/admin/categories/${category.id}`, { method: 'DELETE' });
        await load();
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to delete category.';
    }
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-subheading font-semibold text-foreground">Categories</h1>

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
                <div v-if="loading" class="space-y-2">
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-8 w-full" />
                </div>
                <p v-else-if="categories.length === 0" class="text-sm text-muted-foreground">
                    No categories.
                </p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-16 cursor-pointer select-none" @click="toggleSort('id')">ID{{ sortIndicator('id') }}</TableHead>
                            <TableHead class="cursor-pointer select-none" @click="toggleSort('category')">Name{{ sortIndicator('category') }}</TableHead>
                            <TableHead class="w-48">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="c in sortedCategories" :key="c.id">
                            <TableCell class="font-mono text-sm">{{ c.id }}</TableCell>
                            <TableCell>
                                <Input
                                    v-if="editingId === c.id"
                                    v-model="editingName"
                                    maxlength="100"
                                />
                                <span v-else>{{ c.category }}</span>
                            </TableCell>
                            <TableCell class="space-x-2">
                                <template v-if="editingId === c.id">
                                    <Button size="sm" :disabled="savingId === c.id" @click="saveEdit">
                                        {{ savingId === c.id ? 'Saving…' : 'Save' }}
                                    </Button>
                                    <Button size="sm" variant="outline" @click="editingId = null">
                                        Cancel
                                    </Button>
                                </template>
                                <template v-else>
                                    <Button size="sm" variant="outline" @click="startEdit(c)">
                                        Edit
                                    </Button>
                                    <Button size="sm" variant="destructive" @click="remove(c)">
                                        Delete
                                    </Button>
                                </template>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>