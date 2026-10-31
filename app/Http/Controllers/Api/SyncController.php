<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncDevice;
use App\Services\OfflineAttachmentService;
use App\Services\SyncService;
use App\Support\OfflineSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class SyncController extends Controller
{
    public function __construct(
        protected SyncService $sync,
        protected OfflineAttachmentService $attachments,
    ) {}

    public function batch(Request $request): JsonResponse
    {
        $this->ensureOfflineEnabled();
        $this->ensureSyncAbility($request);

        $validated = $request->validate([
            'mutations' => ['required', 'array', 'max:'.(int) config('offline.max_batch_size', 50)],
            'mutations.*.client_uuid' => ['required', 'uuid'],
            'mutations.*.resource' => ['required', 'string', 'max:100'],
            'mutations.*.action' => ['required', 'string', 'max:20'],
            'mutations.*.body' => ['nullable', 'array'],
            'mutations.*.client_updated_at' => ['nullable', 'date'],
            'device_id' => ['required', 'uuid'],
        ]);

        $this->assertDeviceAllowed($request, $validated['device_id']);

        $results = $this->sync->processBatch($request->user(), $validated['mutations'], $request);

        return response()->json([
            'results' => $results,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $this->ensureOfflineEnabled();
        $this->ensureSyncAbility($request);

        $validated = $request->validate([
            'resource' => ['required', 'string', 'max:100'],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'device_id' => ['nullable', 'uuid'],
        ]);

        if (! empty($validated['device_id'])) {
            $this->assertDeviceAllowed($request, $validated['device_id']);
        }

        $payload = $this->sync->pull(
            $request->user(),
            $validated['resource'],
            $validated['since'] ?? null,
            $validated['limit'] ?? 100,
        );

        return response()->json($payload);
    }

    public function warm(Request $request): JsonResponse
    {
        $this->ensureOfflineEnabled();
        $this->ensureSyncAbility($request);

        if (! OfflineSettings::warmCache()) {
            return response()->json([
                'org_settings' => [],
                'resources' => [],
                'warmed_at' => now()->toIso8601String(),
                'skipped' => true,
            ]);
        }

        return response()->json($this->sync->warmPayload($request->user()));
    }

    public function status(Request $request): JsonResponse
    {
        $pending = \App\Models\SyncClientMutation::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'failed')
            ->count();

        $conflicts = \App\Models\SyncClientMutation::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'conflict')
            ->count();

        return response()->json([
            'offline_enabled' => OfflineSettings::enabled(),
            'pwa_enabled' => OfflineSettings::pwaEnabled(),
            'auto_sync' => OfflineSettings::autoSync(),
            'failed_count' => $pending,
            'conflict_count' => $conflicts,
            'resources' => OfflineSettings::enabled() ? array_keys(config('offline.resources', [])) : [],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $this->ensureOfflineEnabled();
        $this->ensureSyncAbility($request);

        if (! OfflineSettings::metricsEnabled()) {
            return response()->json(['accepted' => false], 204);
        }

        $validated = $request->validate([
            'flush_duration_ms' => ['nullable', 'integer', 'min:0'],
            'outbox_count' => ['nullable', 'integer', 'min:0'],
            'failure_count' => ['nullable', 'integer', 'min:0'],
            'oldest_pending_age_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        Log::channel('sync')->info('sync.client_metrics', [
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return response()->json(['accepted' => true]);
    }

    public function uploadAttachment(Request $request): JsonResponse
    {
        $this->ensureOfflineEnabled();
        $this->ensureSyncAbility($request);

        $validated = $request->validate([
            'file' => ['required', 'file'],
            'client_uuid' => ['required', 'uuid'],
            'parent_client_uuid' => ['required', 'uuid'],
            'resource' => ['required', 'string', 'max:100'],
            'device_id' => ['required', 'uuid'],
        ]);

        $this->assertDeviceAllowed($request, $validated['device_id']);

        $result = $this->attachments->storeForSync(
            $request->user(),
            $validated['resource'],
            $validated['parent_client_uuid'],
            $validated['client_uuid'],
            $validated['file'],
        );

        $attachment = $result['attachment'];

        return response()->json([
            'attachment_id' => $attachment->id,
            'client_uuid' => $attachment->client_uuid,
            'download_url' => $attachment->download_url,
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function issueToken(Request $request): JsonResponse
    {
        $this->ensureOfflineEnabled();

        $validated = $request->validate([
            'device_id' => ['nullable', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $deviceId = $validated['device_id'] ?? (string) Str::uuid();

        $device = SyncDevice::query()->firstOrNew(['device_id' => $deviceId]);

        if ($device->exists && (int) $device->user_id !== (int) $user->id) {
            abort(403, 'Device belongs to another user.');
        }

        if ($device->exists && $device->isRevoked()) {
            abort(403, 'This sync device has been revoked.');
        }

        $tokenName = config('offline.token_name', 'offline-sync').':'.$deviceId;

        if ($device->personal_access_token_id) {
            PersonalAccessToken::query()
                ->whereKey($device->personal_access_token_id)
                ->delete();
        }

        $accessToken = $user->createToken(
            $tokenName,
            config('offline.token_abilities', ['sync']),
        );

        $device->fill([
            'user_id' => $user->id,
            'name' => $validated['device_name'] ?? 'browser',
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'personal_access_token_id' => $accessToken->accessToken->id,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ])->save();

        return response()->json([
            'token' => $accessToken->plainTextToken,
            'device_id' => $deviceId,
            'abilities' => config('offline.token_abilities', ['sync']),
        ]);
    }

    public function revokeDevice(Request $request, string $deviceId): JsonResponse
    {
        $this->ensureOfflineEnabled();

        $device = SyncDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('device_id', $deviceId)
            ->firstOrFail();

        $device->revoke();

        return response()->json(['revoked' => true]);
    }

    protected function ensureOfflineEnabled(): void
    {
        abort_unless(OfflineSettings::enabled(), 503, 'Offline sync is disabled.');
    }

    protected function ensureSyncAbility(Request $request): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $token = $user->currentAccessToken();

        // Session-authenticated requests (cookie) are allowed for same-origin sync.
        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        if (! $token->can('sync')) {
            abort(403, 'Token missing sync ability.');
        }
    }

    protected function assertDeviceAllowed(Request $request, string $deviceId): SyncDevice
    {
        $user = $request->user();
        $device = SyncDevice::query()
            ->where('device_id', $deviceId)
            ->first();

        if ($device && (int) $device->user_id !== (int) $user->id) {
            abort(403, 'Device belongs to another user.');
        }

        if ($device?->isRevoked()) {
            abort(403, 'This sync device has been revoked.');
        }

        $token = $user->currentAccessToken();

        if (! $device) {
            // Session-authenticated clients may sync before a dedicated token exists.
            if ($token instanceof PersonalAccessToken) {
                abort(403, 'Unknown sync device.');
            }

            $device = SyncDevice::query()->create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'name' => 'browser',
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'last_seen_at' => now(),
            ]);
        }

        if ($token instanceof PersonalAccessToken
            && $device->personal_access_token_id
            && (int) $device->personal_access_token_id !== (int) $token->id) {
            abort(403, 'Token does not match this sync device.');
        }

        $device->touchSeen();

        return $device;
    }
}
