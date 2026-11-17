<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="sticky top-14 z-30 -mx-4 flex flex-col gap-1 border-b border-border bg-background/80 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between"
    >
        <div>
            <h1 class="text-subheading font-semibold text-foreground">{{ title }}</h1>
            <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
