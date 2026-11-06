<script setup lang="ts">
import { onMounted, ref } from 'vue';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
import { api } from '@/services/api';
import { APP_VERSION } from '@/version';

interface AboutInfo {
    app_name: string;
    app_description: string;
    app_env: string;
    app_url: string;
    app_timezone: string;
    app_debug: boolean;
    app_version: string;
    php_version: string;
    laravel_version: string;
    database_url: string | null;
    payroll_database: string | null;
}

const info = ref<AboutInfo | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
    try {
        info.value = await api<AboutInfo>('/about');
    } catch (e) {
        error.value = (e as { message?: string }).message ?? 'Failed to load.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="About" description="Application and environment information." />

        <Card>
            <CardHeader>
                <CardTitle>Application</CardTitle>
            </CardHeader>
            <CardContent>
                <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>
                <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
                <dl v-else class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Name</dt>
                        <dd>{{ info?.app_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Description</dt>
                        <dd>{{ info?.app_description }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Environment</dt>
                        <dd>{{ info?.app_env }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">URL</dt>
                        <dd>{{ info?.app_url }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Timezone</dt>
                        <dd>{{ info?.app_timezone }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Debug</dt>
                        <dd>{{ info?.app_debug ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Version (server)</dt>
                        <dd class="font-mono text-xs">{{ info?.app_version }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Version (client build)</dt>
                        <dd class="font-mono text-xs">{{ APP_VERSION }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">PHP</dt>
                        <dd>{{ info?.php_version }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Laravel</dt>
                        <dd>{{ info?.laravel_version }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card v-if="info?.database_url">
            <CardHeader>
                <CardTitle>Database (private)</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <div>
                    <dt class="text-xs font-medium text-muted-foreground">Main</dt>
                    <dd class="font-mono text-xs">{{ info.database_url }}</dd>
                </div>
                <div v-if="info.payroll_database">
                    <dt class="text-xs font-medium text-muted-foreground">Payroll</dt>
                    <dd class="font-mono text-xs">{{ info.payroll_database }}</dd>
                </div>
            </CardContent>
        </Card>
    </div>
</template>