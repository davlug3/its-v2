<script setup lang="ts">
defineProps<{
    message: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
        <p class="text-sm text-muted-foreground">{{ message }}</p>
        <div v-if="$slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>