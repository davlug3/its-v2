<script setup lang="ts">
import { shallowRef } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface Props {
    searchPlaceholder?: string;
}

withDefaults(defineProps<Props>(), {
    searchPlaceholder: 'Search tickets…',
});

const emit = defineEmits<{
    (e: 'search', query: string): void;
    (e: 'reset'): void;
    (e: 'toggle-advanced', open: boolean): void;
}>();

const searchQuery = shallowRef('');
const showAdvanced = shallowRef(false);

const doSearch = (): void => {
    emit('search', searchQuery.value.trim());
};

const doReset = (): void => {
    searchQuery.value = '';
    showAdvanced.value = false;
    emit('reset');
};

const toggleAdvanced = (): void => {
    showAdvanced.value = !showAdvanced.value;
    emit('toggle-advanced', showAdvanced.value);
};
</script>

<template>
    <div class="space-y-3">
        <!-- Single search bar -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <svg
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
                <Input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="searchPlaceholder"
                    aria-label="Search tickets"
                    class="pl-9"
                    @keyup.enter="doSearch"
                />
            </div>
            <div class="flex gap-2">
                <Button type="button" class="min-h-[44px]" @click="doSearch">Search</Button>
                <Button type="button" variant="outline" class="min-h-[44px]" @click="doReset">
                    Reset
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="min-h-[44px]"
                    @click="toggleAdvanced"
                    :aria-expanded="showAdvanced"
                >
                    {{ showAdvanced ? 'Less filters' : 'Advanced' }}
                    <svg
                        class="ml-1 h-3 w-3 transition-transform"
                        :class="showAdvanced ? 'rotate-180' : ''"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </Button>
            </div>
        </div>

        <!-- Advanced filters (collapsible via slot) -->
        <div
            v-if="showAdvanced"
            class="grid grid-cols-1 gap-3 rounded-md border border-border bg-haze/30 p-3 sm:grid-cols-2 lg:grid-cols-4"
        >
            <slot name="advanced" />
        </div>
    </div>
</template>