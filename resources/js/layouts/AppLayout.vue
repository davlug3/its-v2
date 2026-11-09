<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';

import { Bell, ChevronDown, Menu } from '@lucide/vue';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

import { useAuthStore } from '@/stores/auth';
import { navGroups, type NavItem } from '@/config/nav';
import { api, newIdempotencyKey } from '@/services/api';

interface Notification {
    id: number;
    subject: string;
    data: string;
    dt: number;
    state: number;
}

const auth = useAuthStore();
const route = useRoute();

const acl = computed(() => auth.user?.acl ?? 1);

const visibleGroups = computed(() =>
    navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item: NavItem) => (item.acl ?? 1) <= acl.value),
        }))
        .filter((group) => group.items.length > 0),
);

const mobileNavOpen = ref(false);

const notificationsOpen = ref(false);
const notifications = ref<Notification[]>([]);
const unreadCount = ref(0);
let pollTimer: ReturnType<typeof setInterval> | null = null;

const loadUnread = async (): Promise<void> => {
    try {
        const res = await api<{ count: number }>('/notifications/unread-count');
        unreadCount.value = res.count ?? 0;
    } catch {
        unreadCount.value = 0;
    }
};

const loadNotifications = async (): Promise<void> => {
    try {
        notifications.value = await api<Notification[]>('/notifications');
    } catch {
        notifications.value = [];
    }
};

const toggleNotifications = async (): Promise<void> => {
    notificationsOpen.value = !notificationsOpen.value;
    if (notificationsOpen.value) {
        await loadNotifications();
    }
};

const markRead = async (n: Notification): Promise<void> => {
    if (n.state === 1) {
        try {
            await api(`/notifications/${n.id}/read`, { method: 'POST' }, newIdempotencyKey());
            n.state = 2;
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        } catch {
            // ignore
        }
    }
};

const markAllRead = async (): Promise<void> => {
    try {
        await api('/notifications/read-all', { method: 'POST' }, newIdempotencyKey());
        notifications.value = notifications.value.map((n) => ({ ...n, state: 2 }));
        unreadCount.value = 0;
    } catch {
        // ignore
    }
};

const pageTitle = computed(() => {
    const name = route.name?.toString() ?? '';
    return name
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
});

const initials = computed(() => {
    const name = auth.user?.fullname || auth.user?.username || '?';
    return name
        .split(' ')
        .map((w) => w.charAt(0))
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

onMounted(() => {
    void loadUnread();
    pollTimer = setInterval(() => void loadUnread(), 60000);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <div class="flex min-h-screen bg-background">
        <!-- Desktop sidebar -->
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
                        <component :is="item.icon" class="h-4 w-4" />
                        {{ item.label }}
                    </RouterLink>
                </div>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Header -->
            <header class="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-border bg-background/80 px-4 backdrop-blur">
                <!-- Mobile menu -->
                <Sheet v-model:open="mobileNavOpen">
                    <SheetTrigger as-child>
                        <button
                            type="button"
                            class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-haze hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:hidden"
                        >
                            <Menu class="h-5 w-5" />
                        </button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-64 p-0">
                        <SheetHeader class="border-b border-border px-4 py-3">
                            <SheetTitle>IHOMS</SheetTitle>
                        </SheetHeader>
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
                                    @click="mobileNavOpen = false"
                                >
                                    <component :is="item.icon" class="h-4 w-4" />
                                    {{ item.label }}
                                </RouterLink>
                            </div>
                        </nav>
                    </SheetContent>
                </Sheet>

                <div class="flex min-w-0 items-center gap-2">
                    <span class="hidden text-sm font-semibold text-foreground sm:inline">IHOMS</span>
                    <span class="text-muted-foreground">/</span>
                    <span class="truncate text-sm text-muted-foreground">{{ pageTitle }}</span>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <!-- Notifications -->
                    <div class="relative">
                        <button
                            type="button"
                            class="relative rounded-md p-2 text-muted-foreground transition-colors hover:bg-haze hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @click="toggleNotifications"
                        >
                            <Bell class="h-5 w-5" />
                            <span
                                v-if="unreadCount > 0"
                                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-destructive-foreground"
                            >
                                {{ unreadCount > 99 ? '99+' : unreadCount }}
                            </span>
                        </button>
                        <div
                            v-if="notificationsOpen"
                            class="absolute right-0 top-12 z-50 w-80 rounded-md border border-border bg-background shadow-lg"
                        >
                            <div class="flex items-center justify-between border-b border-border px-3 py-2">
                                <span class="text-sm font-semibold">Notifications</span>
                                <button
                                    type="button"
                                    class="text-xs text-muted-foreground hover:text-foreground"
                                    @click="markAllRead"
                                >
                                    Mark all read
                                </button>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                <p v-if="notifications.length === 0" class="p-3 text-sm text-muted-foreground">
                                    No notifications.
                                </p>
                                <button
                                    v-for="n in notifications"
                                    :key="n.id"
                                    type="button"
                                    class="block w-full border-b border-border px-3 py-2 text-left text-sm hover:bg-haze"
                                    :class="n.state === 1 ? 'font-medium' : 'text-muted-foreground'"
                                    @click="markRead(n)"
                                >
                                    <div class="font-medium">{{ n.subject || 'Notification' }}</div>
                                    <div class="text-xs" v-html="n.data"></div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- User menu -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-haze hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <Avatar class="h-7 w-7">
                                    <AvatarFallback class="text-xs">{{ initials }}</AvatarFallback>
                                </Avatar>
                                <span class="hidden sm:inline">{{ auth.user?.username }}</span>
                                <ChevronDown class="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48">
                            <DropdownMenuLabel>{{ auth.user?.fullname || auth.user?.username }}</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <RouterLink to="/user-options" class="w-full">User options</RouterLink>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <RouterLink to="/about" class="w-full">About</RouterLink>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="auth.logout()">Log out</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>

            <main class="flex-1 p-4 md:p-6">
                <RouterView />
            </main>
        </div>
    </div>
</template>