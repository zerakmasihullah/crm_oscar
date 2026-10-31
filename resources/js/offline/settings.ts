export type OfflineClientSettings = {
    enabled: boolean;
    pwa_enabled: boolean;
    auto_sync: boolean;
    warm_cache: boolean;
    attachments_enabled: boolean;
    metrics_enabled: boolean;
    max_batch_size: number;
    max_outbox_size: number;
    attachment_max_bytes: number;
    allowed_mimes: string[];
    cache_ttl_seconds: number;
    resources: string[];
    warm_resources: string[];
    slow_request_ms: number;
};

const DEFAULTS: OfflineClientSettings = {
    enabled: false,
    pwa_enabled: false,
    auto_sync: true,
    warm_cache: true,
    attachments_enabled: false,
    metrics_enabled: true,
    max_batch_size: 50,
    max_outbox_size: 500,
    attachment_max_bytes: 2_097_152,
    allowed_mimes: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    cache_ttl_seconds: 3600,
    resources: [],
    warm_resources: [],
    slow_request_ms: 2000,
};

let cachedSettings: OfflineClientSettings = { ...DEFAULTS };

function normalize(settings?: Partial<OfflineClientSettings> | null): OfflineClientSettings {
    const resources = Array.isArray(settings?.resources)
        ? settings.resources.map(String)
        : DEFAULTS.resources;
    const warmResources = Array.isArray(settings?.warm_resources)
        ? settings.warm_resources.map(String)
        : resources;

    return {
        ...DEFAULTS,
        ...settings,
        max_batch_size: Number(settings?.max_batch_size ?? DEFAULTS.max_batch_size),
        max_outbox_size: Number(settings?.max_outbox_size ?? DEFAULTS.max_outbox_size),
        attachment_max_bytes: Number(settings?.attachment_max_bytes ?? DEFAULTS.attachment_max_bytes),
        cache_ttl_seconds: Number(settings?.cache_ttl_seconds ?? DEFAULTS.cache_ttl_seconds),
        slow_request_ms: Number(settings?.slow_request_ms ?? DEFAULTS.slow_request_ms),
        allowed_mimes: Array.isArray(settings?.allowed_mimes)
            ? settings.allowed_mimes
            : DEFAULTS.allowed_mimes,
        resources,
        warm_resources: warmResources.length > 0 ? warmResources : resources,
    };
}

export function setOfflineSettingsCache(settings: Partial<OfflineClientSettings>): void {
    cachedSettings = normalize(settings);
}

export function getOfflineSettings(): OfflineClientSettings {
    return { ...cachedSettings };
}

export function registeredOfflineResources(): string[] {
    const settings = getOfflineSettings();

    return settings.warm_resources.length > 0
        ? settings.warm_resources
        : settings.resources;
}

export function readOfflineSettingsFromPage(props: {
    offlineSettings?: Partial<OfflineClientSettings>;
    offlineEnabled?: boolean;
}): OfflineClientSettings {
    const settings = normalize({
        ...props.offlineSettings,
        enabled: Boolean(props.offlineEnabled ?? props.offlineSettings?.enabled ?? DEFAULTS.enabled),
    });

    setOfflineSettingsCache(settings);

    return settings;
}
