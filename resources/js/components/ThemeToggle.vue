<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';

import { Button } from '@/components/ui/button';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
</script>

<template>
    <Button variant="ghost" size="icon" @click="theme.toggle()">
        <Sun v-if="!theme.isDark" class="size-4" />
        <Moon v-else class="size-4" />
        <span class="sr-only">Toggle theme</span>
    </Button>
</template>
