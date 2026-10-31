<?php

namespace App\Support;

use App\Models\OrgSetting;

class ThemeColors
{
    public const SETTING_KEY = 'theme.colors';

    /**
     * @return array{
     *     brand: string,
     *     secondary: string,
     *     accent: string,
     *     accentDark: string,
     *     backgroundLight: string,
     *     backgroundDark: string,
     *     radius: string,
     *     density: string,
     *     sidebar_active: string,
     *     shell: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'brand' => '#1a120b',
            'secondary' => '#e4c590',
            'accent' => '#ea7516',
            'accentDark' => '#fb923c',
            'backgroundLight' => '#f7f3ee',
            'backgroundDark' => '#120d09',
            'radius' => 'soft',
            'density' => 'comfortable',
            'sidebar_active' => 'accent',
            'shell' => 'sidebar-floating',
        ];
    }

    /**
     * @return array{
     *     brand: string,
     *     secondary: string,
     *     accent: string,
     *     accentDark: string,
     *     backgroundLight: string,
     *     backgroundDark: string,
     *     radius: string,
     *     density: string,
     *     sidebar_active: string,
     *     shell: string
     * }
     */
    public static function current(): array
    {
        $raw = OrgSetting::getValue(self::SETTING_KEY);
        $parsed = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        if (! is_array($parsed)) {
            return self::defaults();
        }

        return self::normalize($parsed);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     brand: string,
     *     secondary: string,
     *     accent: string,
     *     accentDark: string,
     *     backgroundLight: string,
     *     backgroundDark: string,
     *     radius: string,
     *     density: string,
     *     sidebar_active: string,
     *     shell: string
     * }
     */
    public static function normalize(array $input): array
    {
        $defaults = self::defaults();
        $out = $defaults;

        foreach (['brand', 'secondary', 'accent', 'accentDark', 'backgroundLight', 'backgroundDark'] as $key) {
            $value = $input[$key] ?? null;

            if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                $out[$key] = strtolower($value);
            }
        }

        // Upgrade legacy pure-black dark backgrounds to the current charcoal shell.
        if (in_array($out['backgroundDark'], ['#0a0a0a', '#111318'], true)) {
            $out['backgroundDark'] = $defaults['backgroundDark'];
        }

        $radius = $input['radius'] ?? null;
        if (is_string($radius) && in_array($radius, ['soft', 'medium', 'sharp'], true)) {
            $out['radius'] = $radius;
        }

        $density = $input['density'] ?? null;
        if (is_string($density) && in_array($density, ['comfortable', 'compact'], true)) {
            $out['density'] = $density;
        }

        $sidebarActive = $input['sidebar_active'] ?? $input['sidebarActive'] ?? null;
        if (is_string($sidebarActive) && in_array($sidebarActive, ['pill', 'accent', 'soft'], true)) {
            $out['sidebar_active'] = $sidebarActive;
        }

        $shell = $input['shell'] ?? null;
        if (is_string($shell) && in_array($shell, ['sidebar-floating', 'sidebar-inset', 'sidebar-flush', 'header'], true)) {
            $out['shell'] = $shell;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function store(array $input): array
    {
        $normalized = self::normalize($input);
        OrgSetting::setValue(self::SETTING_KEY, json_encode($normalized));

        return $normalized;
    }

    public static function reset(): array
    {
        OrgSetting::setValue(self::SETTING_KEY, '');

        return self::defaults();
    }

    public static function isCustomized(): bool
    {
        $raw = OrgSetting::getValue(self::SETTING_KEY);

        return is_string($raw) && $raw !== '';
    }
}
