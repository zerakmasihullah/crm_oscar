<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PublicMediaUrl
{
    /**
     * Resolve a stored media path or absolute URL for API/public responses.
     */
    public static function make(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $path = trim($path);

        // Unwrap accidental nesting: /storage/https://… or https://host/storage/https://…
        if (preg_match('#(?:^|/)storage/(https?://.+)$#i', $path, $matches) === 1) {
            return $matches[1];
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/') || str_starts_with($path, '/brand/') || str_starts_with($path, '/images/')) {
            return url($path);
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }
}
