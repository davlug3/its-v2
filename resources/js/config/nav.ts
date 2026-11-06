import type { Component } from 'vue';
import {
    BarChart3,
    ClipboardList,
    Info,
    Monitor,
    FileText,
    Inbox,
    LayoutDashboard,
    Settings,
    Shield,
    UserCog,
    Users,
} from '@lucide/vue';

export interface NavItem {
    label: string;
    to: string;
    icon: Component;
    acl?: number;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

export const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [{ label: 'Dashboard', to: '/', icon: LayoutDashboard }],
    },
    {
        label: 'Tickets',
        items: [
            { label: 'All tickets', to: '/all-tickets', icon: ClipboardList, acl: 2 },
            { label: 'My tickets', to: '/my-tickets', icon: Inbox },
            { label: 'New ticket', to: '/tickets/new', icon: FileText },
            { label: 'By subordinates', to: '/tickets/by-subordinates', icon: Users, acl: 3 },
            { label: 'Monitoring', to: '/monitoring', icon: Monitor, acl: 2 },
        ],
    },
    {
        label: 'Reports',
        items: [{ label: 'Reports', to: '/reports', icon: BarChart3, acl: 3 }],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', to: '/admin/users', icon: Users, acl: 3 },
            { label: 'Categories', to: '/admin/categories', icon: Settings, acl: 3 },
            { label: 'Permissions', to: '/admin/permissions', icon: Shield, acl: 9 },
        ],
    },
    {
        label: 'Account',
        items: [
            { label: 'User options', to: '/user-options', icon: UserCog },
            { label: 'About', to: '/about', icon: Info },
        ],
    },
];
