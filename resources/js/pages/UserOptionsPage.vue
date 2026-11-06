<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="User options" description="Account details." />

        <Card>
            <CardHeader>
                <CardTitle>Account</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Username</dt>
                        <dd>{{ auth.user?.username ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Name</dt>
                        <dd>{{ auth.user?.fullname || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Access level</dt>
                        <dd>{{ auth.user?.acl ?? '—' }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-muted-foreground">
                    Passwords are reset by an administrator.
                </p>
            </CardContent>
        </Card>
    </div>
</template>