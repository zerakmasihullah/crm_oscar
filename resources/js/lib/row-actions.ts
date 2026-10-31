import type { Component } from 'vue';

export interface RowActionItem {
    label: string;
    icon?: Component;
    href?: string;
    method?: 'delete' | 'post' | 'put' | 'patch';
    data?: Record<string, unknown>;
    onClick?: () => void;
    variant?: 'default' | 'destructive';
    disabled?: boolean;
    hidden?: boolean;
    confirm?: {
        title: string;
        description: string;
        confirmLabel?: string;
    };
    confirmVariant?: 'destructive' | 'default';
    separator?: boolean;
    /** Use a native browser navigation for file downloads instead of Inertia. */
    download?: boolean;
    /** Open href in a new browser tab (uses a native anchor). */
    openInNewTab?: boolean;
    /** Play row dust-out before running (defaults on for delete method). */
    dust?: boolean;
}
