import type { Component } from 'vue';
import {
    BarChart3,
    ClipboardList,
    FileText,
    Inbox,
    LayoutDashboard,
    Monitor,
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
            { label: 'All tickets', to: '/tickets', icon: ClipboardList },
            { label: 'My tickets', to: '/my-tickets', icon: Inbox },
            { label: 'New ticket', to: '/tickets/new', icon: FileText },
            { label: 'Approvals', to: '/approvals', icon: Shield },
            { label: 'Monitoring', to: '/monitoring', icon: Monitor },
        ],
    },
    {
        label: 'Reports',
        items: [{ label: 'Reports', to: '/reports', icon: BarChart3 }],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', to: '/admin/users', icon: Users, acl: 3 },
            { label: 'Categories', to: '/admin/categories', icon: Settings, acl: 3 },
            { label: 'Locations', to: '/admin/locations', icon: Settings, acl: 3 },
            { label: 'Groups', to: '/admin/groups', icon: Settings, acl: 3 },
            { label: 'Modules', to: '/admin/modules', icon: Settings, acl: 9 },
            { label: 'Permissions', to: '/admin/permissions', icon: Shield, acl: 9 },
        ],
    },
    {
        label: 'Account',
        items: [{ label: 'User options', to: '/user-options', icon: UserCog }],
    },
];
