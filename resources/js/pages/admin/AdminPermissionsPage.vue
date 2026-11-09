<script setup lang="ts">
import { onMounted, ref } from 'vue';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
import DataTable from '@/components/common/DataTable.vue';
import { api } from '@/services/api';

interface Acl {
    id: number;
    name: string;
}

const acls = ref<Acl[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: 80, resizable: true },
    { key: 'name', label: 'Name', sortable: true, resizable: true },
];

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
        <PageHeader title="Permissions" description="Access levels used across the system." />

        <Card>
            <CardHeader>
                <CardTitle>Access levels</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                <DataTable
                    table-id="admin-permissions"
                    :columns="columns"
                    :rows="acls as unknown as Record<string, unknown>[]"
                    :loading="loading"
                    empty-message="No access levels."
                    pagination="client"
                    :page-size="20"
                >
                    <template #cell-id="{ value }">
                        <span class="font-mono text-sm">{{ value }}</span>
                    </template>
                </DataTable>

                <p class="text-xs text-muted-foreground">
                    User access is changed on the Users page.
                </p>
            </CardContent>
        </Card>
    </div>
</template>