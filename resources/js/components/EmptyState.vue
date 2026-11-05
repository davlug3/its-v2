<script setup lang="ts">
import type { Component } from 'vue';

import { cn } from '@/lib/utils';

interface Props {
    title: string;
    description?: string;
    icon?: Component;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    description: '',
});
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-haze/40 px-6 py-12 text-center',
                props.class,
            )
        "
    >
        <component :is="props.icon" v-if="props.icon" class="mb-3 size-8 text-muted-foreground" />
        <p class="text-sm font-medium text-foreground">{{ props.title }}</p>
        <p v-if="props.description" class="mt-1 text-xs text-muted-foreground">
            {{ props.description }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
