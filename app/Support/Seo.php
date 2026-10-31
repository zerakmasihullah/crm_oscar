<?php

namespace App\Support;

use App\Models\OrgSetting;
use App\Services\LocaleRegistry;

class Seo
{
    /**
     * @return array{
     *     app_url: string,
     *     site_name: string,
     *     default_description: string,
     *     default_image: string,
     *     locale: string,
     *     alternate_locales: list<string>,
     *     keywords: string
     * }
     */
    public static function shared(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $locale = app()->getLocale();
        $available = app(LocaleRegistry::class)->codes();

        return [
            'app_url' => $appUrl,
            'site_name' => (string) OrgSetting::getValue('app_name', config('app.name')),
            'default_description' => (string) config('kit.seo.default_description', ''),
            'default_image' => self::absoluteUrl((string) config('kit.seo.default_image', '/favicon.svg')),
            'locale' => $locale,
            'alternate_locales' => array_values(array_filter(
                $available,
                fn (string $code): bool => $code !== $locale,
            )),
            'keywords' => (string) config('kit.seo.keywords', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function document(array $overrides = []): array
    {
        $shared = self::shared();
        $path = $overrides['path'] ?? parse_url((string) request()->getRequestUri(), PHP_URL_PATH) ?: '/';
        $description = self::truncate(self::stripHtml(
            (string) ($overrides['description'] ?? $shared['default_description']),
        ));
        $image = $overrides['image'] ?? $shared['default_image'];
        $title = array_key_exists('title', $overrides)
            ? trim((string) ($overrides['title'] ?? ''))
            : null;

        return [
            'title' => $title !== null && $title !== '' ? $title : null,
            'description' => $description,
            'image' => $image ? self::absoluteUrl((string) $image) : $shared['default_image'],
            'image_alt' => $overrides['image_alt'] ?? ($title ?: $shared['site_name']),
            'url' => $overrides['url'] ?? self::absoluteUrl(is_string($path) ? $path : '/'),
            'path' => is_string($path) ? $path : '/',
            'type' => $overrides['type'] ?? 'website',
            'robots' => $overrides['robots'] ?? null,
            'site_name_only' => (bool) ($overrides['site_name_only'] ?? false),
            'json_ld' => $overrides['json_ld'] ?? null,
        ];
    }

    public static function absoluteUrl(string $path = '/'): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($path === '' || $path === '/') {
            return $appUrl !== '' ? $appUrl.'/' : '/';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $appUrl.'/'.ltrim($path, '/');
    }

    public static function stripHtml(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public static function truncate(?string $value, int $max = 160): string
    {
        $text = self::stripHtml($value);

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $sliced = mb_substr($text, 0, $max - 1);
        $lastSpace = mb_strrpos($sliced, ' ');

        if ($lastSpace !== false && $lastSpace > 80) {
            $sliced = mb_substr($sliced, 0, $lastSpace);
        }

        return rtrim($sliced).'…';
    }
}
