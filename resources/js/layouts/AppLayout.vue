<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, RouterView } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import { navGroups, type NavItem } from '@/config/nav';

const auth = useAuthStore();

const acl = computed(() => auth.user?.acl ?? 1);

const visibleGroups = computed(() =>
    navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item: NavItem) => (item.acl ?? 1) <= acl.value),
        }))
        .filter((group) => group.items.length > 0),
);

function iconClass(): string {
    return 'h-4 w-4';
}
</script>

<template>
    <div class="flex min-h-screen bg-background">
        <aside class="hidden w-60 shrink-0 border-r border-border bg-paper md:block">
            <div class="flex h-14 items-center border-b border-border px-4">
                <span class="text-sm font-semibold text-foreground">IHOMS</span>
            </div>
            <nav class="space-y-4 p-2">
                <div v-for="group in visibleGroups" :key="group.label">
                    <p class="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ group.label }}
                    </p>
                    <RouterLink
                        v-for="item in group.items"
                        :key="item.to"
                        :to="item.to"
                        class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-haze hover:text-foreground"
                        active-class="bg-haze text-foreground"
                    >
                        <component :is="item.icon" :class="iconClass()" />
                        {{ item.label }}
                    </RouterLink>
                </div>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-14 items-center justify-between border-b border-border px-4">
                <span class="text-sm font-medium text-foreground md:hidden">IHOMS</span>
                <div class="ml-auto flex items-center gap-2">
                    <span class="text-sm text-muted-foreground">{{ auth.user?.username }}</span>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm text-muted-foreground hover:bg-haze hover:text-foreground"
                        @click="auth.logout()"
                    >
                        Log out
                    </button>
                </div>
            </header>

            <main class="flex-1 p-4 md:p-6">
                <RouterView />
            </main>
        </div>
    </div>
</template>