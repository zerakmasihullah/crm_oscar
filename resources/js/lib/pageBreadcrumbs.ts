import type { BreadcrumbItem } from '@/types';

/**
 * Build Inertia layout breadcrumbs for app header title + trail.
 * First crumb is the section title/link; extra crumbs show under it.
 *
 * @example
 * defineOptions(pageBreadcrumbs(
 *   { title: 'Field notes', href: '/field-notes' },
 *   { title: 'Create', href: '/field-notes/create' },
 * ));
 */
export function pageBreadcrumbs(
    ...crumbs: Array<BreadcrumbItem | false | null | undefined>
): { layout: { breadcrumbs: BreadcrumbItem[] } } {
    return {
        layout: {
            breadcrumbs: crumbs.filter(
                (crumb): crumb is BreadcrumbItem =>
                    crumb != null && crumb !== false && crumb.title !== '',
            ),
        },
    };
}

/** Index / create / edit crumbs for a resource list route. */
export function resourcePageBreadcrumbs(
    resourceTitle: string,
    resourceHref: string,
    page?: 'create' | 'edit' | { title: string; href?: string },
): { layout: { breadcrumbs: BreadcrumbItem[] } } {
    const base = { title: resourceTitle, href: resourceHref };

    if (!page) {
        return pageBreadcrumbs(base);
    }

    if (page === 'create') {
        return pageBreadcrumbs(base, {
            title: 'Create',
            href: `${resourceHref.replace(/\/$/, '')}/create`,
        });
    }

    if (page === 'edit') {
        return pageBreadcrumbs(base, {
            title: 'Edit',
            href: resourceHref,
        });
    }

    return pageBreadcrumbs(base, {
        title: page.title,
        href: page.href ?? resourceHref,
    });
}
