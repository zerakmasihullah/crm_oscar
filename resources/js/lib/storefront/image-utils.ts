const DEFAULT_IMAGE = '/brand/menu-default.jpg';

/**
 * Normalize menu/product image URLs for the storefront.
 * Prefers same-origin /storage when already on this app.
 */
export function getImageUrl(imageUrl: string | undefined | null): string | null {
    if (!imageUrl) return null;

    let url = String(imageUrl).trim();
    if (!url || url === 'null' || url === 'undefined') return null;

    if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
            const parsed = new URL(url);
            if (parsed.pathname.startsWith('/storage/')) {
                // Prefer local storage when running inside oscar_cms
                if (typeof window !== 'undefined' && window.location.origin) {
                    return `${window.location.origin}${parsed.pathname}${parsed.search}`;
                }
            }
            if (url.includes('oscarlimerick.com')) {
                url = url.replace(
                    /https?:\/\/([^/]*\.)?oscarlimerick\.com/,
                    'https://order.oscarlimerick.com',
                );
            }
            return url;
        } catch {
            return url;
        }
    }

    if (url.startsWith('/storage/') || url.startsWith('/brand/') || url.startsWith('/images/')) {
        return url;
    }

    if (!url.includes('://')) {
        return `/storage/${url.replace(/^\/+/, '')}`;
    }

    return null;
}

export function resolveImageUrl(imageUrl: string | undefined | null, fallback = DEFAULT_IMAGE): string {
    return getImageUrl(imageUrl) || fallback;
}

export { DEFAULT_IMAGE };
