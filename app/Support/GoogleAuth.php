<?php

namespace App\Support;

use App\Models\OrgSetting;
use Illuminate\Support\Facades\Crypt;

class GoogleAuth
{
    public const KEYS = [
        'enabled' => 'google_auth.enabled',
        'client_id' => 'google_auth.client_id',
        'client_secret' => 'google_auth.client_secret',
        'redirect_uri' => 'google_auth.redirect_uri',
    ];

    public static function string(string $key, ?string $default = null): ?string
    {
        $raw = OrgSetting::getValue(self::KEYS[$key] ?? $key, null);

        if ($raw === null || $raw === '') {
            return $default;
        }

        return (string) $raw;
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

    /**
     * Whether the Settings / env toggle is on (credentials may still be missing).
     */
    public static function featureEnabled(): bool
    {
        $default = (bool) config('kit.features.google_auth', false);

        return self::bool('enabled', $default);
    }

    public static function clientId(): ?string
    {
        $stored = self::string('client_id');

        if (filled($stored)) {
            return $stored;
        }

        $fromConfig = config('services.google.client_id');

        return filled($fromConfig) ? (string) $fromConfig : null;
    }

    public static function clientSecretSet(): bool
    {
        return filled(self::string('client_secret'))
            || filled(config('services.google.client_secret'));
    }

    public static function decryptedClientSecret(): ?string
    {
        $encrypted = self::string('client_secret');

        if ($encrypted !== null) {
            try {
                return Crypt::decryptString($encrypted);
            } catch (\Throwable) {
                // Fall through to config/env.
            }
        }

        $fromConfig = config('services.google.client_secret');

        return filled($fromConfig) ? (string) $fromConfig : null;
    }

    public static function redirectUri(): string
    {
        $stored = self::string('redirect_uri');

        if (filled($stored)) {
            return $stored;
        }

        $fromConfig = config('services.google.redirect');

        if (filled($fromConfig)) {
            return (string) $fromConfig;
        }

        return rtrim((string) config('app.url'), '/').'/auth/google/callback';
    }

    public static function credentialsConfigured(): bool
    {
        return filled(self::clientId()) && filled(self::decryptedClientSecret());
    }

    /**
     * Whether Google sign-in is available on login/register.
     */
    public static function enabled(): bool
    {
        return self::featureEnabled() && self::credentialsConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAdmin(): array
    {
        return [
            'enabled' => self::featureEnabled(),
            'client_id' => (string) (self::clientId() ?? ''),
            'client_secret_set' => self::clientSecretSet(),
            'redirect_uri' => self::redirectUri(),
            'credentials_configured' => self::credentialsConfigured(),
            'active' => self::enabled(),
            'env_default' => (bool) env('KIT_GOOGLE_AUTH', false),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function save(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            OrgSetting::setValue(self::KEYS['enabled'], $input['enabled'] ? '1' : '0');
        }

        if (array_key_exists('client_id', $input)) {
            OrgSetting::setValue(self::KEYS['client_id'], trim((string) ($input['client_id'] ?? '')));
        }

        if (array_key_exists('client_secret', $input) && filled($input['client_secret'])) {
            OrgSetting::setValue(
                self::KEYS['client_secret'],
                Crypt::encryptString(trim((string) $input['client_secret'])),
            );
        }

        if (array_key_exists('redirect_uri', $input)) {
            OrgSetting::setValue(
                self::KEYS['redirect_uri'],
                trim((string) ($input['redirect_uri'] ?? '')),
            );
        }

        self::applyToConfig();
    }

    public static function applyToConfig(): void
    {
        try {
            config([
                'kit.features.google_auth' => self::featureEnabled(),
                'services.google.client_id' => self::clientId(),
                'services.google.client_secret' => self::decryptedClientSecret(),
                'services.google.redirect' => self::redirectUri(),
            ]);
        } catch (\Throwable) {
            // Org settings table may not exist during early install/migrate.
        }
    }
}
