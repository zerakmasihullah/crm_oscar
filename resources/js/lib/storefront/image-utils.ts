const DEFAULT_IMAGE = '/brand/menu-default.jpg';

/**
 * Normalize menu/product image URLs for the storefront.
 * Prefers same-origin /storage when already on this app.
 */
export function getImageUrl(imageUrl: string | undefined | null): string | null {
    if (!imageUrl) return null;

    let url = String(imageUrl).trim();
    if (!url || url === 'null' || url === 'undefined') return null;

    // Unwrap /storage/https://… (and host-prefixed variants) from bad API joins
    const nested = url.match(/\/storage\/(https?:\/\/.+)$/i);
    if (nested) {
        url = nested[1];
    }

    if (url.startsWith('/storage/http://') || url.startsWith('/storage/https://')) {
        url = url.slice('/storage/'.length);
    }

    if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
            const parsed = new URL(url);
            const storagePath = parsed.pathname.startsWith('/storage/')
                ? parsed.pathname.slice('/storage/'.length)
                : '';

            // Prefer local storage only for real disk paths, not nested remote URLs
            if (
                parsed.pathname.startsWith('/storage/') &&
                storagePath &&
                !storagePath.startsWith('http://') &&
                !storagePath.startsWith('https://') &&
                !storagePath.includes('://')
            ) {
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
