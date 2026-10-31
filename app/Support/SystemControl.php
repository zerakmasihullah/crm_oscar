<?php

namespace App\Support;

use App\Models\OrgSetting;
use Laravel\Fortify\Features;

class SystemControl
{
    public const KEYS = [
        'registration' => 'system.registration_enabled',
        'reset_password' => 'system.reset_password_enabled',
        'email_verification' => 'system.email_verification_enabled',
        'multilingual' => 'system.multilingual_enabled',
        'translations_ui' => 'system.translations_ui_enabled',
        'exports' => 'system.exports_enabled',
        'excel_exports' => 'system.excel_exports_enabled',
        'tours' => 'system.tours_enabled',
        'seo' => 'system.seo_enabled',
        'attachments' => 'system.attachments_enabled',
        'audit' => 'system.audit_enabled',
        'api' => 'system.api_enabled',
    ];

    /**
     * Toggle definitions for the admin UI (grouped).
     *
     * @return list<array{key: string, group: string, label: string, help: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'registration',
                'group' => 'auth',
                'label' => 'Registration',
                'help' => 'Allow new users to create accounts from the register page.',
            ],
            [
                'key' => 'reset_password',
                'group' => 'auth',
                'label' => 'Forgot password',
                'help' => 'Show “Forgot password?” and allow password reset emails.',
            ],
            [
                'key' => 'email_verification',
                'group' => 'auth',
                'label' => 'Email verification',
                'help' => 'Require users to verify their email address after signing up.',
            ],
            [
                'key' => 'multilingual',
                'group' => 'locale',
                'label' => 'Multi-language',
                'help' => 'Show the language switcher and allow users to change locale.',
            ],
            [
                'key' => 'translations_ui',
                'group' => 'locale',
                'label' => 'Translations editor',
                'help' => 'Settings → Translations JSON editor for managing strings.',
            ],
            [
                'key' => 'exports',
                'group' => 'product',
                'label' => 'Exports & print',
                'help' => 'CSV / print export actions on list pages (e.g. users).',
            ],
            [
                'key' => 'excel_exports',
                'group' => 'product',
                'label' => 'Excel exports',
                'help' => 'SpreadsheetML (.xlsx-compatible) downloads. Requires Exports.',
            ],
            [
                'key' => 'tours',
                'group' => 'product',
                'label' => 'Product tours',
                'help' => 'Guided admin shell tours for new users.',
            ],
            [
                'key' => 'seo',
                'group' => 'product',
                'label' => 'SEO',
                'help' => 'Public meta tags, robots.txt, and sitemap helpers.',
            ],
            [
                'key' => 'attachments',
                'group' => 'product',
                'label' => 'Attachments',
                'help' => 'File attachments on domain records that support them.',
            ],
            [
                'key' => 'audit',
                'group' => 'product',
                'label' => 'Audit logging',
                'help' => 'Activity and authentication audit trails.',
            ],
            [
                'key' => 'api',
                'group' => 'integrations',
                'label' => 'API',
                'help' => 'Sanctum token API routes (/api/auth, /api/users, …).',
            ],
        ];
    }

    public static function bool(string $key, bool $default): bool
    {
        $raw = OrgSetting::getValue(self::KEYS[$key] ?? $key, null);

        if ($raw === null) {
            return $default;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        return in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true);
    }

    public static function registrationEnabled(): bool
    {
        return self::bool('registration', true);
    }

    public static function resetPasswordEnabled(): bool
    {
        return self::bool('reset_password', true);
    }

    public static function emailVerificationEnabled(): bool
    {
        return self::bool('email_verification', true);
    }

    public static function multilingualEnabled(): bool
    {
        return self::bool('multilingual', true);
    }

    public static function translationsUiEnabled(): bool
    {
        return self::bool('translations_ui', (bool) config('kit.features.translations_ui', true));
    }

    public static function exportsEnabled(): bool
    {
        return self::bool('exports', (bool) config('kit.features.exports', true));
    }

    public static function excelExportsEnabled(): bool
    {
        return self::exportsEnabled()
            && self::bool('excel_exports', (bool) config('kit.features.excel_exports', true));
    }

    public static function toursEnabled(): bool
    {
        return self::bool('tours', (bool) config('kit.features.tours', true));
    }

    public static function seoEnabled(): bool
    {
        return self::bool('seo', (bool) config('kit.features.seo', true));
    }

    public static function attachmentsEnabled(): bool
    {
        return self::bool('attachments', (bool) config('kit.features.attachments', true));
    }

    public static function auditEnabled(): bool
    {
        return self::bool('audit', (bool) config('kit.features.audit', true));
    }

    public static function apiEnabled(): bool
    {
        return self::bool('api', (bool) config('kit.features.api', false));
    }

    /**
     * Resolve the effective value for a known toggle key.
     */
    public static function enabled(string $key): bool
    {
        return match ($key) {
            'registration' => self::registrationEnabled(),
            'reset_password' => self::resetPasswordEnabled(),
            'email_verification' => self::emailVerificationEnabled(),
            'multilingual' => self::multilingualEnabled(),
            'translations_ui' => self::translationsUiEnabled(),
            'exports' => self::exportsEnabled(),
            'excel_exports' => self::excelExportsEnabled(),
            'tours' => self::toursEnabled(),
            'seo' => self::seoEnabled(),
            'attachments' => self::attachmentsEnabled(),
            'audit' => self::auditEnabled(),
            'api' => self::apiEnabled(),
            default => false,
        };
    }

    /**
     * Config / env default before OrgSetting override.
     */
    public static function configDefault(string $key): bool
    {
        return match ($key) {
            'registration', 'reset_password', 'email_verification', 'multilingual' => true,
            'translations_ui' => (bool) env('KIT_TRANSLATIONS_UI', true),
            'exports' => (bool) env('KIT_EXPORTS', true),
            'excel_exports' => (bool) env('KIT_EXCEL_EXPORTS', true),
            'tours', 'seo', 'attachments', 'audit' => true,
            'api' => (bool) env('KIT_API', false),
            default => false,
        };
    }

    /**
     * Stored preference (not effective AND logic) for admin toggles.
     */
    public static function stored(string $key): bool
    {
        return self::bool($key, self::configDefault($key));
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAdmin(): array
    {
        $settings = [];

        foreach (array_keys(self::KEYS) as $key) {
            $settings[$key] = self::stored($key);
        }

        return [
            'settings' => $settings,
            'modules' => [
                [
                    'key' => 'ai',
                    'label' => 'AI',
                    'href' => '/settings/ai',
                    'enabled' => AiSettings::enabled(),
                    'help' => 'Writing assist and chat — configure models under Settings → AI.',
                ],
                [
                    'key' => 'offline',
                    'label' => 'Offline & PWA',
                    'href' => '/settings/offline',
                    'enabled' => OfflineSettings::enabled(),
                    'help' => 'Offline forms, sync, and installable app shell.',
                ],
                [
                    'key' => 'google_auth',
                    'label' => 'Google Auth',
                    'href' => '/settings/google-auth',
                    'enabled' => GoogleAuth::featureEnabled(),
                    'help' => 'Sign in with Google — credentials under Settings → Google Auth.',
                ],
            ],
        ];
    }

    /**
     * Compact flags shared on every Inertia page.
     *
     * @return array<string, bool>
     */
    public static function forClient(): array
    {
        return [
            'registration' => self::registrationEnabled(),
            'reset_password' => self::resetPasswordEnabled(),
            'email_verification' => self::emailVerificationEnabled(),
            'multilingual' => self::multilingualEnabled(),
            'translations_ui' => self::translationsUiEnabled(),
            'exports' => self::exportsEnabled(),
            'excel_exports' => self::excelExportsEnabled(),
            'tours' => self::toursEnabled(),
            'seo' => self::seoEnabled(),
            'attachments' => self::attachmentsEnabled(),
            'audit' => self::auditEnabled(),
            'api' => self::apiEnabled(),
            'ai' => AiSettings::enabled(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function save(array $input): void
    {
        foreach (array_keys(self::KEYS) as $key) {
            if (array_key_exists($key, $input)) {
                OrgSetting::setValue(self::KEYS[$key], $input[$key] ? '1' : '0');
            }
        }

        self::applyToConfig();
    }

    /**
     * Apply OrgSetting overrides onto Fortify + kit feature config.
     *
     * Must run before Fortify registers routes (prefer AppServiceProvider::register).
     */
    public static function applyToConfig(): void
    {
        try {
            if (! config()->has('fortify.features_source')) {
                config(['fortify.features_source' => config('fortify.features', [])]);
            }

            config([
                'kit.features.translations_ui' => self::translationsUiEnabled(),
                'kit.features.exports' => self::exportsEnabled(),
                'kit.features.excel_exports' => self::excelExportsEnabled(),
                'kit.features.tours' => self::toursEnabled(),
                'kit.features.seo' => self::seoEnabled(),
                'kit.features.attachments' => self::attachmentsEnabled(),
                'kit.features.audit' => self::auditEnabled(),
                'kit.features.api' => self::apiEnabled(),
                'kit.features.multilingual' => self::multilingualEnabled(),
                'fortify.features' => self::filteredFortifyFeatures(),
            ]);
        } catch (\Throwable) {
            // Org settings table may not exist during early install/migrate.
        }
    }

    /**
     * @return list<mixed>
     */
    protected static function filteredFortifyFeatures(): array
    {
        $features = config('fortify.features_source', config('fortify.features', []));

        if (! is_array($features)) {
            return [];
        }

        $allowed = [];

        foreach ($features as $feature) {
            $name = is_string($feature) ? $feature : (string) $feature;

            if ($name === Features::registration() && ! self::registrationEnabled()) {
                continue;
            }

            if ($name === Features::resetPasswords() && ! self::resetPasswordEnabled()) {
                continue;
            }

            if ($name === Features::emailVerification() && ! self::emailVerificationEnabled()) {
                continue;
            }

            $allowed[] = $feature;
        }

        return array_values($allowed);
    }
}
