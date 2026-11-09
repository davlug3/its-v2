import { createRouter, createWebHistory } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: () => import('@/pages/LoginPage.vue'),
            meta: { guest: true },
        },
        {
            path: '/',
            component: () => import('@/layouts/AppLayout.vue'),
            meta: { requiresAuth: true },
            children: [
                {
                    path: '',
                    name: 'dashboard',
                    component: () => import('@/pages/DashboardPage.vue'),
                },
                {
                    path: 'my-tickets',
                    name: 'my-tickets',
                    component: () => import('@/pages/MyTicketsPage.vue'),
                },
                {
                    path: 'all-tickets',
                    name: 'all-tickets',
                    component: () => import('@/pages/AllTicketsPage.vue'),
                    meta: { acl: 2 },
                },
                {
                    path: 'tickets/closed-by-me',
                    name: 'closed-by-me',
                    component: () => import('@/pages/ClosedByMePage.vue'),
                    meta: { acl: 2 },
                },
                {
                    path: 'tickets/by-subordinates',
                    name: 'by-subordinates',
                    component: () => import('@/pages/BySubordinatesPage.vue'),
                    meta: { acl: 3 },
                },
                {
                    path: 'tickets/new',
                    name: 'new-ticket',
                    component: () => import('@/pages/NewTicketPage.vue'),
                },
                {
                    path: 'tickets/:id',
                    name: 'ticket-detail',
                    component: () => import('@/pages/TicketDetailPage.vue'),
                },
                {
                    path: 'user-options',
                    name: 'user-options',
                    component: () => import('@/pages/UserOptionsPage.vue'),
                },
                {
                    path: 'about',
                    name: 'about',
                    component: () => import('@/pages/AboutPage.vue'),
                },
                {
                    path: 'reports',
                    name: 'reports',
                    component: () => import('@/pages/ReportsPage.vue'),
                    meta: { acl: 3 },
                },
                {
                    path: 'monitoring',
                    name: 'monitoring',
                    component: () => import('@/pages/MonitoringPage.vue'),
                    meta: { acl: 2 },
                },
                {
                    path: 'admin/users',
                    name: 'admin-users',
                    component: () => import('@/pages/admin/AdminUsersPage.vue'),
                    meta: { acl: 3 },
                },
                {
                    path: 'admin/categories',
                    name: 'admin-categories',
                    component: () => import('@/pages/admin/AdminCategoriesPage.vue'),
                    meta: { acl: 3 },
                },
                {
                    path: 'admin/permissions',
                    name: 'admin-permissions',
                    component: () => import('@/pages/admin/AdminPermissionsPage.vue'),
                    meta: { acl: 9 },
                },
            ],
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: '/',
        },
    ],
});

router.beforeEach(async (to) => {
    // Legacy deep link: #ticket=123
    const hash = to.hash || (typeof window !== 'undefined' ? window.location.hash : '');
    const ticketMatch = hash.match(/ticket=(\d+)/i);
    if (ticketMatch && to.name !== 'ticket-detail') {
        return { path: `/tickets/${ticketMatch[1]}`, replace: true };
    }

    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (auth.isAuthenticated && auth.user === null) {
        await auth.fetchMe();
    }

    const required = to.meta.acl;
    if (typeof required === 'number' && (auth.user?.acl ?? 1) < required) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
