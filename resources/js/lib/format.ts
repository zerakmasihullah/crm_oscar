export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    /** Present when using Laravel simplePaginate (no expensive COUNT). */
    has_more?: boolean;
    simple?: boolean;
}

/**
 * Laravel LengthAwarePaginator / SimplePaginator shape as sent through Inertia.
 */
export interface Paginated<T> {
    data: T[];
    links?: PaginationLink[];
    meta?: PaginationMeta;
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
    next_page_url?: string | null;
    prev_page_url?: string | null;
    first_page_url?: string | null;
    path?: string;
}

export type PaginatedLike = Pick<Paginated<unknown>, 'links' | 'meta'> &
    Partial<PaginationMeta> &
    Pick<
        Paginated<unknown>,
        'next_page_url' | 'prev_page_url' | 'first_page_url' | 'path' | 'data'
    > & {
        data?: unknown[];
    };

export type NormalizedPagination = PaginationMeta & {
    links: PaginationLink[];
};

/** Normalize Eloquent flat pagination or nested `{ meta, links }` into one shape. */
export function normalizePagination(
    pagination: PaginatedLike | null | undefined,
): NormalizedPagination | null {
    if (!pagination) {
        return null;
    }

    if (pagination.meta) {
        return {
            ...pagination.meta,
            links: pagination.links ?? [],
        };
    }

    // Length-aware (has total + last_page)
    if (
        pagination.last_page != null &&
        pagination.current_page != null &&
        pagination.per_page != null &&
        pagination.total != null
    ) {
        return {
            current_page: pagination.current_page,
            last_page: pagination.last_page,
            per_page: pagination.per_page,
            total: pagination.total,
            from: pagination.from ?? null,
            to: pagination.to ?? null,
            links: pagination.links ?? [],
            simple: false,
        };
    }

    // Simple paginator (prev/next only — no COUNT(*))
    if (
        pagination.current_page != null &&
        pagination.per_page != null &&
        (pagination.next_page_url !== undefined ||
            pagination.prev_page_url !== undefined)
    ) {
        const hasMore = Boolean(pagination.next_page_url);
        const links: PaginationLink[] = [
            {
                url: pagination.prev_page_url ?? null,
                label: '&laquo; Previous',
                active: false,
            },
            {
                url: null,
                label: String(pagination.current_page),
                active: true,
            },
            {
                url: pagination.next_page_url ?? null,
                label: 'Next &raquo;',
                active: false,
            },
        ];

        return {
            current_page: pagination.current_page,
            last_page: hasMore
                ? pagination.current_page + 1
                : pagination.current_page,
            per_page: pagination.per_page,
            total: pagination.to ?? pagination.data?.length ?? 0,
            from: pagination.from ?? null,
            to: pagination.to ?? null,
            has_more: hasMore,
            simple: true,
            links: pagination.links?.length ? pagination.links : links,
        };
    }

    return null;
}

/** Always use Western (0–9) digits, including in Pashto/Dari UI. */
export const LATIN_NUMERALS = { numberingSystem: 'latn' as const };

export function formatNumber(
    value?: number | null,
    options?: Intl.NumberFormatOptions,
): string {
    if (value == null) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        ...LATIN_NUMERALS,
        ...options,
    }).format(value);
}
