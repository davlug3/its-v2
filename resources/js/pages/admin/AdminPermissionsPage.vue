<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

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

interface Acl {
    id: number;
    name: string;
}

const acls = ref<Acl[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const sortKey = ref('id');
const sortDir = ref<'asc' | 'desc'>('asc');

const sortedAcls = computed(() => {
    const arr = [...acls.value];
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

const load = async (): Promise<void> => {
    loading.value = true;
    error.value = null;
    try {
        acls.value = await api<Acl[]>('/admin/permissions');
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load permissions.';
    } finally {
        loading.value = false;
    }
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-subheading font-semibold text-foreground">Permissions</h1>

        <Card>
            <CardHeader>
                <CardTitle>Access levels</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-20 cursor-pointer select-none" @click="toggleSort('id')">ID{{ sortIndicator('id') }}</TableHead>
                            <TableHead class="cursor-pointer select-none" @click="toggleSort('name')">Name{{ sortIndicator('name') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="acl in sortedAcls" :key="acl.id">
                            <TableCell class="font-mono text-sm">{{ acl.id }}</TableCell>
                            <TableCell>{{ acl.name }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <p class="text-xs text-muted-foreground">
                    User access is changed on the Users page.
                </p>
            </CardContent>
        </Card>
    </div>
</template>