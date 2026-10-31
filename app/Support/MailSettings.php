<?php

namespace App\Support;

use App\Models\OrgSetting;
use Illuminate\Support\Facades\Crypt;

class MailSettings
{
    public const KEYS = [
        'mailer' => 'mail.mailer',
        'from_address' => 'mail.from_address',
        'from_name' => 'mail.from_name',
        'host' => 'mail.host',
        'port' => 'mail.port',
        'username' => 'mail.username',
        'password' => 'mail.password',
        'encryption' => 'mail.encryption',
        'mail_alerts' => 'mail.mail_alerts',
    ];

    public const MAILERS = ['log', 'smtp', 'sendmail', 'array'];

    public const ENCRYPTIONS = ['', 'tls', 'ssl'];

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

    public static function passwordSet(): bool
    {
        return filled(self::string('password'));
    }

    public static function decryptedPassword(): ?string
    {
        $encrypted = self::string('password');

        if ($encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Apply OrgSetting overrides onto runtime mail config.
     */
    public static function applyToConfig(): void
    {
        try {
            $mailer = self::string('mailer', (string) config('mail.default', 'log')) ?: 'log';

            if (! in_array($mailer, self::MAILERS, true)) {
                $mailer = (string) config('mail.default', 'log');
            }

            $fromAddress = self::string('from_address', (string) config('mail.from.address'));
            $fromName = self::string('from_name', (string) config('mail.from.name'));
            $host = self::string('host', (string) config('mail.mailers.smtp.host'));
            $port = (int) (self::string('port', (string) config('mail.mailers.smtp.port', '2525')) ?: 2525);
            $username = self::string('username', config('mail.mailers.smtp.username'));
            $password = self::decryptedPassword() ?? config('mail.mailers.smtp.password');
            $encryption = self::normalizeEncryption(
                self::string('encryption', self::encryptionFromScheme(config('mail.mailers.smtp.scheme'))),
            );

            config([
                'mail.default' => $mailer,
                'mail.from.address' => $fromAddress,
                'mail.from.name' => $fromName,
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.username' => $username,
                'mail.mailers.smtp.password' => $password,
                // Laravel/Symfony expect "smtp" (STARTTLS) or "smtps" (implicit SSL), not "tls"/"ssl".
                'mail.mailers.smtp.scheme' => self::schemeFromEncryption($encryption),
                'kit.features.mail_alerts' => self::bool('mail_alerts', (bool) config('kit.features.mail_alerts', true)),
            ]);

            MailTemplates::applyToConfig();

            if (app()->bound('mail.manager')) {
                app('mail.manager')->purge('smtp');
                app('mail.manager')->forgetMailers();
            }
        } catch (\Throwable) {
            // Database may be unavailable during early install / package discovery.
        }
    }

    /**
     * Map UI encryption values to Symfony SMTP DSN schemes.
     */
    public static function schemeFromEncryption(?string $encryption): ?string
    {
        return match (self::normalizeEncryption($encryption)) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => null,
        };
    }

    /**
     * Map a configured SMTP scheme back to the UI encryption value.
     */
    public static function encryptionFromScheme(mixed $scheme): string
    {
        return match (is_string($scheme) ? strtolower($scheme) : '') {
            'smtps', 'ssl' => 'ssl',
            'smtp', 'tls' => 'tls',
            default => '',
        };
    }

    public static function normalizeEncryption(?string $encryption): string
    {
        $value = strtolower(trim((string) $encryption));

        return in_array($value, ['tls', 'ssl'], true) ? $value : '';
    }

    /**
     * @return array{
     *     mailer: string,
     *     from_address: string,
     *     from_name: string,
     *     host: string,
     *     port: int,
     *     username: string|null,
     *     password_set: bool,
     *     encryption: string,
     *     mail_alerts: bool,
     *     env_mailer: string,
     *     template: string
     * }
     */
    public static function forAdmin(): array
    {
        return [
            'mailer' => self::string('mailer', (string) config('mail.default', 'log')) ?: 'log',
            'from_address' => self::string('from_address', (string) config('mail.from.address', '')) ?: '',
            'from_name' => self::string('from_name', (string) config('mail.from.name', '')) ?: '',
            'host' => self::string('host', (string) config('mail.mailers.smtp.host', '127.0.0.1')) ?: '127.0.0.1',
            'port' => (int) (self::string('port', (string) config('mail.mailers.smtp.port', '2525')) ?: 2525),
            'username' => self::string('username', config('mail.mailers.smtp.username')),
            'password_set' => self::passwordSet(),
            'encryption' => self::normalizeEncryption(
                self::string('encryption', self::encryptionFromScheme(config('mail.mailers.smtp.scheme'))),
            ),
            'mail_alerts' => self::bool('mail_alerts', (bool) config('kit.features.mail_alerts', true)),
            'env_mailer' => (string) env('MAIL_MAILER', 'log'),
            'template' => MailTemplates::currentId(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function save(array $input): void
    {
        if (array_key_exists('mailer', $input) && is_string($input['mailer'])) {
            OrgSetting::setValue(self::KEYS['mailer'], $input['mailer']);
        }

        foreach (['from_address', 'from_name', 'host', 'username'] as $key) {
            if (array_key_exists($key, $input)) {
                OrgSetting::setValue(self::KEYS[$key], (string) ($input[$key] ?? ''));
            }
        }

        if (array_key_exists('port', $input) && $input['port'] !== null) {
            OrgSetting::setValue(self::KEYS['port'], (string) (int) $input['port']);
        }

        if (array_key_exists('encryption', $input)) {
            OrgSetting::setValue(
                self::KEYS['encryption'],
                self::normalizeEncryption(is_string($input['encryption'] ?? null) ? $input['encryption'] : null),
            );
        }

        if (array_key_exists('password', $input) && filled($input['password'])) {
            OrgSetting::setValue(self::KEYS['password'], Crypt::encryptString((string) $input['password']));
        }

        if (array_key_exists('mail_alerts', $input)) {
            OrgSetting::setValue(self::KEYS['mail_alerts'], $input['mail_alerts'] ? '1' : '0');
        }

        self::applyToConfig();
    }
}
