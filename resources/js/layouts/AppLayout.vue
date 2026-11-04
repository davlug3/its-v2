<script setup lang="ts">
import { RouterView } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
    <div class="flex min-h-screen bg-background">
        <aside class="hidden w-60 shrink-0 border-r border-border bg-paper md:block">
            <div class="flex h-14 items-center border-b border-border px-4">
                <span class="text-sm font-semibold text-foreground">IHOMS</span>
            </div>
            <nav class="p-2">
                <RouterLink
                    to="/"
                    class="block rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-haze hover:text-foreground"
                >
                    Dashboard
                </RouterLink>
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
