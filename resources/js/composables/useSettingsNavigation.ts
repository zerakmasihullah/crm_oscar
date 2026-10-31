import {
    DatabaseBackup,
    FolderOpen,
    History,
    Languages,
    Mail,
    Palette,
    Settings2,
    Shield,
    Sparkles,
    Store,
    Trash2,
    UserRound,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

export type SettingsNavItem = NavItem & {
    icon: typeof Settings2;
    group: 'account' | 'administration';
    /** Paths that should keep this sidebar item active. */
    match?: string[];
};

export function useSettingsNavigation() {
    const { t } = useTranslations();
    const { can, canAny } = usePermissions();

    const navItems = computed<SettingsNavItem[]>(() => {
        const items: SettingsNavItem[] = [
            {
                title: t('Profile'),
                href: editProfile(),
                icon: UserRound,
                group: 'account',
                match: ['/settings/profile', '/settings/security', '/settings/password'],
            },
            {
                title: t('Appearance'),
                href: editAppearance(),
                icon: Palette,
                group: 'account',
                match: ['/settings/appearance'],
            },
        ];

        if (can('settings.manage')) {
            items.push({
                title: t('Platform'),
                href: '/settings/system-control',
                icon: Settings2,
                group: 'administration',
                match: [
                    '/settings/system-control',
                    '/settings/offline',
                    '/settings/google-auth',
                ],
            });
        }

        if (can('users.view') || can('roles.view')) {
            items.push({
                title: t('Access'),
                href: can('users.view') ? '/settings/users' : '/settings/roles',
                icon: can('users.view') ? Users : Shield,
                group: 'administration',
                match: ['/settings/users', '/settings/roles'],
            });
        }

        if (
            can('menus.view') ||
            can('menu_items.view') ||
            can('deliveries.view') ||
            can('reservations.view') ||
            can('platforms.view')
        ) {
            const storeHref = can('menus.view')
                ? '/menus'
                : can('menu_items.view')
                  ? '/menu-items'
                  : can('deliveries.view')
                    ? '/deliveries'
                    : can('reservations.view')
                      ? '/reservations'
                      : '/settings/platforms';

            items.push({
                title: t('Store'),
                href: storeHref,
                icon: Store,
                group: 'administration',
                match: [
                    '/menus',
                    '/menu-items',
                    '/deliveries',
                    '/reservations',
                    '/settings/platforms',
                ],
            });
        }

        if (
            can('storage.view') ||
            can('trash.view') ||
            can('backups.view') ||
            canAny(['backups.run', 'backups.download'])
        ) {
            const href = can('storage.view')
                ? '/settings/file-manager'
                : can('trash.view')
                  ? '/settings/trash'
                  : '/settings/backups';

            const icon = can('storage.view')
                ? FolderOpen
                : can('trash.view')
                  ? Trash2
                  : DatabaseBackup;

            items.push({
                title: t('Storage'),
                href,
                icon,
                group: 'administration',
                match: [
                    '/settings/file-manager',
                    '/settings/trash',
                    '/settings/backups',
                ],
            });
        }

        if (can('settings.manage')) {
            items.push({
                title: t('Translations'),
                href: '/settings/translations',
                icon: Languages,
                group: 'administration',
                match: ['/settings/translations'],
            });
        }

        if (canAny(['ai.manage', 'ai.view', 'settings.manage', 'audit.view'])) {
            items.push(
                {
                    title: t('AI'),
                    href: canAny(['ai.manage', 'settings.manage'])
                        ? '/settings/ai'
                        : '/settings/ai/logs',
                    icon: Sparkles,
                    group: 'administration',
                    match: ['/settings/ai'],
                },
            );
        }

        if (can('settings.manage') || can('audit.view')) {
            items.push(
                {
                    title: t('Email'),
                    href: can('settings.manage')
                        ? '/settings/email'
                        : '/settings/email/logs',
                    icon: Mail,
                    group: 'administration',
                    match: ['/settings/email'],
                },
            );
        }

        if (can('audit.view')) {
            items.push({
                title: t('Activity'),
                href: '/settings/login-logs',
                icon: History,
                group: 'administration',
                match: [
                    '/settings/login-logs',
                    '/settings/action-logs',
                    '/settings/system-logs',
                ],
            });
        }

        return items;
    });

    const accountItems = computed(() =>
        navItems.value.filter((item) => item.group === 'account'),
    );

    const adminItems = computed(() =>
        navItems.value.filter((item) => item.group === 'administration'),
    );

    function isItemActive(item: SettingsNavItem, currentPath: string): boolean {
        const paths = item.match?.length
            ? item.match
            : [String(typeof item.href === 'string' ? item.href : '')];

        return paths.some(
            (path) => currentPath === path || currentPath.startsWith(`${path}/`),
        );
    }

    return {
        navItems,
        accountItems,
        adminItems,
        isItemActive,
    };
}
