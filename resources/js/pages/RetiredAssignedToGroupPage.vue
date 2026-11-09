<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PageHeader from '@/components/common/PageHeader.vue';
</script>

<template>
    <div class="space-y-4">
        <PageHeader title="Assigned to group" description="This view has been retired." />
        <Card>
            <CardHeader>
                <CardTitle>Teams retired</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">
                    Group/team assignment views are retired in this version. Use <strong>Assigned to you</strong>,
                    <strong>Unassigned tickets</strong>, or <strong>All tickets</strong> instead.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
