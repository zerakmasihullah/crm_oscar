import { Eye, Pencil, Trash2 } from '@lucide/vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import type { RowActionItem } from '@/lib/row-actions';

export function useMisPage() {
    const { t } = useTranslations();
    const { can } = usePermissions();

    const gated = (
        action: RowActionItem,
        permission?: string,
    ): RowActionItem => ({
        ...action,
        hidden: action.hidden || (permission ? !can(permission) : false),
    });

    const viewAction = (href: string): RowActionItem => ({
        label: t('View'),
        icon: Eye,
        href,
    });

    const editAction = (href: string, permission?: string): RowActionItem =>
        gated(
            {
                label: t('Edit'),
                icon: Pencil,
                href,
            },
            permission,
        );

    const deleteAction = (
        options: {
            href: string;
            title: string;
            description: string;
            method?: 'delete' | 'post' | 'put';
        },
        permission?: string,
    ): RowActionItem =>
        gated(
            {
                label: t('Move to trash'),
                icon: Trash2,
                variant: 'destructive',
                separator: true,
                href: options.href,
                method: options.method ?? 'delete',
                confirm: {
                    title: options.title,
                    description: options.description,
                    confirmLabel: t('Move to trash'),
                },
            },
            permission,
        );

    const gateActions = (
        actions: RowActionItem[],
        permission: string,
    ): RowActionItem[] => actions.map((action) => gated(action, permission));

    return {
        t,
        can,
        viewAction,
        editAction,
        deleteAction,
        gateActions,
    };
}
