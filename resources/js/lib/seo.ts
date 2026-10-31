export type SeoJsonLd = Record<string, unknown> | Array<Record<string, unknown>>;

export type SharedSeo = {
    app_url: string;
    site_name: string;
    default_description: string;
    default_image: string;
    locale: string;
    alternate_locales?: string[];
    keywords?: string;
};

const META_DESCRIPTION_MAX = 160;

export function absoluteUrl(pathOrUrl: string | null | undefined, baseUrl: string): string {
    if (!pathOrUrl) {
        return baseUrl.replace(/\/$/, '') || '/';
    }

    if (/^https?:\/\//i.test(pathOrUrl)) {
        return pathOrUrl;
    }

    const base = baseUrl.replace(/\/$/, '');
    const path = pathOrUrl.startsWith('/') ? pathOrUrl : `/${pathOrUrl}`;

    return `${base}${path}`;
}

export function stripHtml(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return value
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&quot;/gi, '"')
        .replace(/&#39;/gi, "'")
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/\s+/g, ' ')
        .trim();
}

export function truncateMeta(value: string | null | undefined, max = META_DESCRIPTION_MAX): string {
    const text = stripHtml(value);

    if (text.length <= max) {
        return text;
    }

    const sliced = text.slice(0, max - 1);
    const lastSpace = sliced.lastIndexOf(' ');

    return `${(lastSpace > 80 ? sliced.slice(0, lastSpace) : sliced).trim()}…`;
}

export function pickDescription(...candidates: Array<string | null | undefined>): string {
    for (const candidate of candidates) {
        const text = truncateMeta(candidate);

        if (text) {
            return text;
        }
    }

    return '';
}
