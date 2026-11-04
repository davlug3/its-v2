<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-subheading font-semibold text-foreground">Dashboard</h1>
        <Card>
            <CardHeader>
                <CardTitle>Welcome</CardTitle>
                <CardDescription> Signed in as {{ auth.user?.username ?? '—' }}. </CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">
                    Feature screens arrive in the next phases.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
