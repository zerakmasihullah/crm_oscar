export type AiPageContext = {
    label: string;
    path: string;
    resource?: string;
    record_id?: number;
};

type PathRule = {
    pattern: RegExp;
    resource?: string;
    label: (match: RegExpMatchArray, path: string) => string;
};

const PATH_RULES: PathRule[] = [
    {
        pattern: /^\/field-notes\/(\d+)(?:\/|$)/,
        resource: 'field_notes',
        label: (m) => `Field note #${m[1]}`,
    },
    {
        pattern: /^\/field-notes(?:\/|$)/,
        resource: 'field_notes',
        label: () => 'Field notes',
    },
    {
        pattern: /^\/settings\/users(?:\/|$)/,
        resource: 'users',
        label: () => 'Users',
    },
    {
        pattern: /^\/settings\/roles(?:\/|$)/,
        resource: 'roles',
        label: () => 'Roles',
    },
    {
        pattern: /^\/settings(?:\/|$)/,
        label: () => 'Settings',
    },
    {
        pattern: /^\/ai\/analytics(?:\/|$)/,
        label: () => 'AI Analytics',
    },
    {
        pattern: /^\/dashboard(?:\/|$)/,
        label: () => 'Dashboard',
    },
];

function titleFromComponent(component: string | undefined): string | null {
    if (!component) {
        return null;
    }

    const leaf = component.split('/').pop() ?? component;
    if (!leaf || leaf === 'Index') {
        const parent = component.split('/').slice(-2, -1)[0];
        if (parent) {
            return parent
                .replace(/([a-z])([A-Z])/g, '$1 $2')
                .replace(/[-_]/g, ' ');
        }
    }

    return leaf.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/[-_]/g, ' ');
}

export function derivePageContext(
    url: string,
    component?: string,
): AiPageContext | null {
    const path = (url.split('?')[0] || '/').replace(/\/+$/, '') || '/';

    if (path === '/' || path === '') {
        return {
            label: 'Home',
            path: '/',
        };
    }

    for (const rule of PATH_RULES) {
        const match = path.match(rule.pattern);
        if (!match) {
            continue;
        }

        const recordMatch = path.match(/\/(\d+)(?:\/|$)/);
        const recordId = recordMatch ? Number(recordMatch[1]) : undefined;

        return {
            label: rule.label(match, path),
            path,
            ...(rule.resource ? { resource: rule.resource } : {}),
            ...(recordId && Number.isFinite(recordId)
                ? { record_id: recordId }
                : {}),
        };
    }

    const fromComponent = titleFromComponent(component);

    return {
        label: fromComponent || path,
        path,
    };
}
