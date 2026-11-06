<script setup lang="ts">
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

defineProps<{
    label: string;
    value: number | string;
    loading?: boolean;
}>();
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardDescription>{{ label }}</CardDescription>
            <CardTitle class="text-2xl">
                <Skeleton v-if="loading" class="h-7 w-10" />
                <span v-else>{{ value }}</span>
            </CardTitle>
        </CardHeader>
    </Card>
</template>