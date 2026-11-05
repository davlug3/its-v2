<script setup lang="ts">
import { onMounted, ref } from 'vue';

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
                            <TableHead class="w-20">ID</TableHead>
                            <TableHead>Name</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="acl in acls" :key="acl.id">
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