import {
    ChartColumn,
    Euro,
    FileChartColumn,
    LayoutGrid,
    Mail,
    Megaphone,
    Percent,
    Settings,
    ShoppingCart,
    Users,
    UsersRound,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function useAppNavigation() {
    const { t } = useTranslations();
    const { can, canAny } = usePermissions();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const mainNavItems = computed(() => {
        const items: NavItem[] = [
            {
                title: t('Dashboard'),
                href: dashboard(),
                icon: LayoutGrid,
            },
        ];

        if (can('orders.view') || can('orders.create')) {
            items.push({
                title: t('Orders'),
                href: '/orders',
                icon: ShoppingCart,
            });
        }

        if (can('platform_revenues.view') || can('expenses.view')) {
            const children: NavItem[] = [];
            if (can('platform_revenues.view')) {
                children.push({
                    title: t('Platform revenue'),
                    href: '/platform-revenues',
                    icon: Euro,
                });
            }
            if (can('expenses.view')) {
                children.push({
                    title: t('Expenses'),
                    href: '/expenses',
                    icon: Wallet,
                });
            }
            children.push({
                title: t('Reports'),
                href: '/finance/reports',
                icon: FileChartColumn,
            });
            items.push({
                title: t('Finance'),
                href: children[0]?.href ?? '/platform-revenues',
                icon: Wallet,
                children,
            });
        }

        if (
            can('discounts.view') ||
            can('promotions.view') ||
            can('promotion_recipients.view')
        ) {
            const children: NavItem[] = [];

            if (
                can('promotions.view') ||
                can('discounts.view') ||
                can('promotion_recipients.view')
            ) {
                children.push({
                    title: t('Overview'),
                    href: '/marketing',
                    icon: Megaphone,
                });
            }
            if (can('promotions.view')) {
                children.push({
                    title: t('Campaigns'),
                    href: '/promotions',
                    icon: Mail,
                });
            }
            if (can('discounts.view')) {
                children.push({
                    title: t('Discounts'),
                    href: '/discounts',
                    icon: Percent,
                });
            }
            if (can('promotions.view')) {
                children.push({
                    title: t('Audience'),
                    href: '/marketing/audience',
                    icon: Users,
                });
            }
            if (can('promotion_recipients.view')) {
                children.push({
                    title: t('Delivery'),
                    href: '/promotion-recipients',
                    icon: UsersRound,
                });
            }

            items.push({
                title: t('Marketing'),
                href: '/marketing',
                icon: Megaphone,
                children,
            });
        }

        if (can('analytics.view')) {
            items.push({
                title: t('Analytics'),
                href: '/analytics',
                icon: ChartColumn,
            });
        }

        if (can('oscar_users.view')) {
            items.push({
                title: t('Customers'),
                href: '/oscar-users',
                icon: Users,
            });
        }

        if (canAny(['ai.view', 'ai.analytics'])) {
            items.push({
                title: t('AI Analytics'),
                href: '/ai/analytics',
                icon: ChartColumn,
            });
        }

        return items;
    });

    const adminNavItems = computed(() => {
        const items: NavItem[] = [];

        if (
            can('settings.manage') ||
            can('users.view') ||
            can('roles.view') ||
            can('backups.view') ||
            canAny(['backups.run', 'backups.download']) ||
            can('storage.view') ||
            can('trash.view') ||
            can('audit.view')
        ) {
            items.push({
                title: t('Settings'),
                href: can('settings.manage')
                    ? '/settings/appearance'
                    : can('users.view')
                      ? '/settings/users'
                      : can('roles.view')
                        ? '/settings/roles'
                        : can('backups.view') ||
                            canAny(['backups.run', 'backups.download'])
                          ? '/settings/backups'
                          : can('storage.view')
                            ? '/settings/file-manager'
                            : can('trash.view')
                              ? '/settings/trash'
                              : '/settings/login-logs',
                icon: Settings,
            });
        }

        return items;
    });

    const allNavItems = computed(() => [
        ...mainNavItems.value,
        ...adminNavItems.value,
    ]);

    /** Header center tabs: children of the active top-level section, if any. */
    const headerTabItems = computed(() => {
        const active = allNavItems.value.find((item) => {
            if (item.title === t('Settings')) {
                return false;
            }

            if (item.children?.length) {
                return (
                    isCurrentOrParentUrl(item.href) ||
                    item.children.some((child) =>
                        isCurrentOrParentUrl(child.href),
                    )
                );
            }

            return isCurrentOrParentUrl(item.href);
        });

        return active?.children?.length ? active.children : [];
    });

    /** Longest matching child href — avoids /marketing also matching /marketing/audience. */
    const activeHeaderTabHref = computed(() => {
        const matches = headerTabItems.value
            .filter((item) => isCurrentOrParentUrl(item.href))
            .map((item) => String(item.href))
            .sort((a, b) => b.length - a.length);

        return matches[0] ?? null;
    });

    return {
        mainNavItems,
        adminNavItems,
        allNavItems,
        headerTabItems,
        activeHeaderTabHref,
    };
}
