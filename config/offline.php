<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offline foundation
    |--------------------------------------------------------------------------
    |
    | Enable via config('kit.features.offline') / KIT_OFFLINE=true.
    | See docs/OFFLINE.md for architecture and opt-in checklist.
    |
    */

    'enabled' => (bool) env('KIT_OFFLINE', false),

    /*
    |--------------------------------------------------------------------------
    | Sync
    |--------------------------------------------------------------------------
    */

    'max_batch_size' => (int) env('OFFLINE_MAX_BATCH_SIZE', 50),

    'max_outbox_size' => (int) env('OFFLINE_MAX_OUTBOX_SIZE', 500),

    'token_abilities' => ['sync'],

    'token_name' => 'offline-sync',

    /*
    |--------------------------------------------------------------------------
    | Conflict policy
    |--------------------------------------------------------------------------
    |
    | v1: idempotent client_uuid + last-write-wins on updated_at.
    |
    */

    'conflict_policy' => 'last_write_wins',

    /*
    |--------------------------------------------------------------------------
    | Reference data to warm into IndexedDB on login / online
    |--------------------------------------------------------------------------
    */

    'warm' => [
        'org_settings' => [
            'app_name',
            'default_locale',
            'calendar_system',
            'logo_path',
        ],
        'resources' => [
            'field_reports' => ['ttl' => 3600],
            'field_notes' => ['ttl' => 3600],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources that support offline mutations
    |--------------------------------------------------------------------------
    |
    | Maps sync resource key => handler class implementing
    | App\Contracts\SyncsOfflineMutations.
    |
    */

    'resources' => [
        'field_reports' => \App\Sync\Handlers\FieldReportSyncHandler::class,
        'field_notes' => \App\Sync\Handlers\FieldNoteSyncHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachment parent models
    |--------------------------------------------------------------------------
    |
    | Maps sync resource key => parent model config used by
    | OfflineAttachmentService when uploading blobs before batch flush.
    | Models must use App\Concerns\HasAttachments.
    |
    */

    'attachment_parents' => [
        'field_reports' => [
            'model' => \App\Models\FieldReport::class,
            'user_scoped' => true,
            'defaults' => [
                'title' => 'Offline draft',
                'status' => 'draft',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'enabled' => (bool) env('OFFLINE_ATTACHMENTS', false),
        'max_bytes' => (int) env('OFFLINE_ATTACHMENT_MAX_BYTES', 2_097_152), // 2 MB
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'enabled' => (bool) env('OFFLINE_METRICS', true),
        'slow_ms' => (int) env('OFFLINE_SLOW_MS', 1500),
    ],

    'slow_request_ms' => (int) env('SLOW_REQUEST_MS', 2000),

];
