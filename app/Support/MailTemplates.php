<?php

namespace App\Support;

use App\Models\OrgSetting;
use Illuminate\Mail\Markdown;

class MailTemplates
{
    public const SETTING_KEY = 'mail.template';

    public const DEFAULT = 'classic';

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     tagline: string,
     *     description: string,
     *     preview: array{
     *         header: string,
     *         surface: string,
     *         card: string,
     *         accent: string,
     *         button: string,
     *         text: string,
     *         muted: string,
     *         radius: string
     *     }
     * }>
     */
    public static function all(): array
    {
        $colors = ThemeColors::current();
        $brand = $colors['brand'];
        $secondary = $colors['secondary'];
        $accent = $colors['accent'];
        $panel = $colors['backgroundLight'];

        return [
            [
                'id' => 'classic',
                'name' => 'Classic brand',
                'tagline' => 'Brand-first',
                'description' => 'Strong brand header with clear navy actions — closest to your admin look.',
                'preview' => [
                    'header' => $brand,
                    'surface' => $panel,
                    'card' => '#ffffff',
                    'accent' => $brand,
                    'button' => $brand,
                    'text' => '#1f2937',
                    'muted' => '#6b7280',
                    'radius' => '8px',
                ],
            ],
            [
                'id' => 'modern',
                'name' => 'Modern accent',
                'tagline' => 'Soft & rounded',
                'description' => 'Soft panel background with teal accent buttons and rounded cards.',
                'preview' => [
                    'header' => '#ffffff',
                    'surface' => $panel,
                    'card' => '#ffffff',
                    'accent' => $accent,
                    'button' => $accent,
                    'text' => '#0f172a',
                    'muted' => '#64748b',
                    'radius' => '16px',
                ],
            ],
            [
                'id' => 'formal',
                'name' => 'Formal gold',
                'tagline' => 'Official notices',
                'description' => 'Institutional layout with a gold accent line — suited to official notices.',
                'preview' => [
                    'header' => '#ffffff',
                    'surface' => '#f8f5ef',
                    'card' => '#ffffff',
                    'accent' => $secondary,
                    'button' => $brand,
                    'text' => '#162238',
                    'muted' => '#78716c',
                    'radius' => '4px',
                ],
            ],
            [
                'id' => 'minimal',
                'name' => 'Minimal clean',
                'tagline' => 'Simple alerts',
                'description' => 'Quiet white canvas with a slim brand accent — best for simple alerts.',
                'preview' => [
                    'header' => '#ffffff',
                    'surface' => '#ffffff',
                    'card' => '#ffffff',
                    'accent' => $brand,
                    'button' => $brand,
                    'text' => '#111827',
                    'muted' => '#9ca3af',
                    'radius' => '2px',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_column(self::all(), 'id');
    }

    public static function currentId(): string
    {
        $value = OrgSetting::getValue(self::SETTING_KEY, self::DEFAULT);

        if (! is_string($value) || ! in_array($value, self::ids(), true)) {
            return self::DEFAULT;
        }

        return $value;
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     preview: array<string, string>
     * }
     */
    public static function current(): array
    {
        $id = self::currentId();

        foreach (self::all() as $template) {
            if ($template['id'] === $id) {
                return $template;
            }
        }

        return self::all()[0];
    }

    public static function set(string $templateId): void
    {
        if (! in_array($templateId, self::ids(), true)) {
            $templateId = self::DEFAULT;
        }

        OrgSetting::setValue(self::SETTING_KEY, $templateId);
        self::applyToConfig();
    }

    public static function applyToConfig(): void
    {
        try {
            $theme = self::currentId();

            config([
                'mail.markdown.theme' => $theme,
            ]);

            if (app()->bound(Markdown::class)) {
                app(Markdown::class)->theme($theme);
            }
        } catch (\Throwable) {
            // Ignore during early boot / missing DB.
        }
    }
}
