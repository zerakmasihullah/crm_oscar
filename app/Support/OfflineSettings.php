<?php

namespace App\Support;

use App\Models\OrgSetting;
use App\Models\SyncClientMutation;
use App\Models\SyncDevice;

class OfflineSettings
{
    public const KEYS = [
        'enabled' => 'offline.enabled',
        'pwa_enabled' => 'offline.pwa_enabled',
        'auto_sync' => 'offline.auto_sync',
        'warm_cache' => 'offline.warm_cache',
        'attachments_enabled' => 'offline.attachments_enabled',
        'metrics_enabled' => 'offline.metrics_enabled',
        'max_batch_size' => 'offline.max_batch_size',
        'max_outbox_size' => 'offline.max_outbox_size',
        'attachment_max_bytes' => 'offline.attachment_max_bytes',
        'slow_request_ms' => 'offline.slow_request_ms',
    ];

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

    public static function int(string $key, int $default): int
    {
        $raw = OrgSetting::getValue(self::KEYS[$key] ?? $key, null);

        if ($raw === null || $raw === '') {
            return $default;
        }

        return (int) $raw;
    }

    public static function enabled(): bool
    {
        $default = (bool) config('kit.features.offline', false)
            || (bool) config('offline.enabled', false);

        return self::bool('enabled', $default);
    }

    public static function pwaEnabled(): bool
    {
        return self::enabled() && self::bool('pwa_enabled', true);
    }

    public static function autoSync(): bool
    {
        return self::enabled() && self::bool('auto_sync', true);
    }

    public static function warmCache(): bool
    {
        return self::enabled() && self::bool('warm_cache', true);
    }

    public static function attachmentsEnabled(): bool
    {
        return self::enabled() && self::bool('attachments_enabled', (bool) config('offline.attachments.enabled', false));
    }

    public static function metricsEnabled(): bool
    {
        return self::enabled() && self::bool('metrics_enabled', (bool) config('offline.metrics.enabled', true));
    }

    public static function attachmentMaxBytes(): int
    {
        return self::int('attachment_max_bytes', (int) config('offline.attachments.max_bytes', 2_097_152));
    }

    /**
     * @return list<string>
     */
    public static function allowedMimes(): array
    {
        $mimes = config('offline.attachments.allowed_mimes', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ]);

        return array_values(array_filter(array_map('strval', is_array($mimes) ? $mimes : [])));
    }

    public static function cacheTtlSeconds(?string $resource = null): int
    {
        if ($resource) {
            $resourceTtl = config("offline.warm.resources.{$resource}.ttl");

            if ($resourceTtl !== null) {
                return (int) $resourceTtl;
            }
        }

        $warm = config('offline.warm.resources');

        if (is_array($warm) && $warm !== []) {
            $first = reset($warm);

            if (is_array($first) && isset($first['ttl'])) {
                return (int) $first['ttl'];
            }
        }

        return 3600;
    }

    /**
     * @return list<string>
     */
    public static function resourceKeys(): array
    {
        return array_values(array_map('strval', array_keys(config('offline.resources', []))));
    }

    /**
     * Resources that should be warmed / incrementally pulled on the client.
     *
     * @return list<string>
     */
    public static function warmResourceKeys(): array
    {
        $warm = array_keys(config('offline.warm.resources', []));
        $registered = self::resourceKeys();

        $keys = array_values(array_intersect(
            array_map('strval', $warm),
            $registered,
        ));

        return $keys !== [] ? $keys : $registered;
    }

    /**
     * Apply OrgSetting overrides onto runtime config.
     */
    public static function applyToConfig(): void
    {
        try {
            $enabled = self::enabled();

            config([
                'kit.features.offline' => $enabled,
                'offline.enabled' => $enabled,
                'offline.pwa_enabled' => self::pwaEnabled(),
                'offline.auto_sync' => self::autoSync(),
                'offline.warm_cache' => self::warmCache(),
                'offline.max_batch_size' => self::int('max_batch_size', (int) config('offline.max_batch_size', 50)),
                'offline.max_outbox_size' => self::int('max_outbox_size', (int) config('offline.max_outbox_size', 500)),
                'offline.attachments.enabled' => self::attachmentsEnabled(),
                'offline.attachments.max_bytes' => self::attachmentMaxBytes(),
                'offline.metrics.enabled' => self::metricsEnabled(),
                'offline.slow_request_ms' => self::int('slow_request_ms', (int) config('offline.slow_request_ms', 2000)),
            ]);

            if ($enabled && ! config('kit.features.api')) {
                // Offline sync needs API routes even when general API flag is off.
                config(['kit.features.api' => true]);
            }
        } catch (\Throwable) {
            // Database may be unavailable during early install / package discovery.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAdmin(): array
    {
        return [
            'enabled' => self::enabled(),
            'pwa_enabled' => self::bool('pwa_enabled', true),
            'auto_sync' => self::bool('auto_sync', true),
            'warm_cache' => self::bool('warm_cache', true),
            'attachments_enabled' => self::bool('attachments_enabled', (bool) config('offline.attachments.enabled', false)),
            'metrics_enabled' => self::bool('metrics_enabled', (bool) config('offline.metrics.enabled', true)),
            'max_batch_size' => self::int('max_batch_size', (int) config('offline.max_batch_size', 50)),
            'max_outbox_size' => self::int('max_outbox_size', (int) config('offline.max_outbox_size', 500)),
            'attachment_max_bytes' => self::attachmentMaxBytes(),
            'slow_request_ms' => self::int('slow_request_ms', (int) config('offline.slow_request_ms', 2000)),
            'conflict_policy' => (string) config('offline.conflict_policy', 'last_write_wins'),
            'resources' => self::resourceKeys(),
            'warm_resources' => self::warmResourceKeys(),
            'allowed_mimes' => self::allowedMimes(),
            'cache_ttl_seconds' => self::cacheTtlSeconds(),
            'env_default' => (bool) env('KIT_OFFLINE', false),
        ];
    }

    /**
     * Compact payload shared to every Inertia page.
     *
     * @return array<string, mixed>
     */
    public static function forClient(): array
    {
        return [
            'enabled' => self::enabled(),
            'pwa_enabled' => self::pwaEnabled(),
            'auto_sync' => self::autoSync(),
            'warm_cache' => self::warmCache(),
            'attachments_enabled' => self::attachmentsEnabled(),
            'metrics_enabled' => self::metricsEnabled(),
            'max_batch_size' => self::int('max_batch_size', (int) config('offline.max_batch_size', 50)),
            'max_outbox_size' => self::int('max_outbox_size', (int) config('offline.max_outbox_size', 500)),
            'attachment_max_bytes' => self::attachmentMaxBytes(),
            'allowed_mimes' => self::allowedMimes(),
            'cache_ttl_seconds' => self::cacheTtlSeconds(),
            'resources' => self::resourceKeys(),
            'warm_resources' => self::warmResourceKeys(),
            'slow_request_ms' => self::int('slow_request_ms', (int) config('offline.slow_request_ms', 2000)),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function save(array $input): void
    {
        $boolKeys = [
            'enabled',
            'pwa_enabled',
            'auto_sync',
            'warm_cache',
            'attachments_enabled',
            'metrics_enabled',
        ];

        foreach ($boolKeys as $key) {
            if (array_key_exists($key, $input)) {
                OrgSetting::setValue(self::KEYS[$key], $input[$key] ? '1' : '0');
            }
        }

        $intKeys = [
            'max_batch_size',
            'max_outbox_size',
            'attachment_max_bytes',
            'slow_request_ms',
        ];

        foreach ($intKeys as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null) {
                OrgSetting::setValue(self::KEYS[$key], (string) (int) $input[$key]);
            }
        }

        self::applyToConfig();
    }

    /**
     * @return array{failed_mutations: int, conflict_mutations: int, active_devices: int}
     */
    public static function stats(): array
    {
        try {
            return [
                'failed_mutations' => SyncClientMutation::query()->where('status', 'failed')->count(),
                'conflict_mutations' => SyncClientMutation::query()->where('status', 'conflict')->count(),
                'active_devices' => SyncDevice::query()->whereNull('revoked_at')->count(),
            ];
        } catch (\Throwable) {
            return [
                'failed_mutations' => 0,
                'conflict_mutations' => 0,
                'active_devices' => 0,
            ];
        }
    }
}
