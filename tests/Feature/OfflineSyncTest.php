<?php

namespace Tests\Feature;

use App\Models\FieldReport;
use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kit.features.offline' => true,
            'kit.features.api' => true,
            'offline.enabled' => true,
            'offline.attachments.enabled' => true,
        ]);
    }

    protected function actingAsSyncUser(?User $user = null): array
    {
        $user ??= User::factory()->create(['is_active' => true]);
        $deviceId = (string) Str::uuid();

        SyncDevice::query()->create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'name' => 'test-device',
            'last_seen_at' => now(),
        ]);

        Sanctum::actingAs($user, ['sync']);

        return [$user, $deviceId];
    }

    public function test_sync_batch_is_idempotent_for_field_reports(): void
    {
        [, $deviceId] = $this->actingAsSyncUser();

        $clientUuid = (string) Str::uuid();

        $mutation = [
            'client_uuid' => $clientUuid,
            'resource' => 'field_reports',
            'action' => 'upsert',
            'body' => [
                'client_uuid' => $clientUuid,
                'title' => 'Field visit',
                'body' => 'Notes',
                'location' => 'Kabul',
                'status' => 'draft',
            ],
            'client_updated_at' => now()->toIso8601String(),
        ];

        $this->postJson('/api/sync/batch', [
            'device_id' => $deviceId,
            'mutations' => [$mutation],
        ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'processed');

        $this->postJson('/api/sync/batch', [
            'device_id' => $deviceId,
            'mutations' => [$mutation],
        ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'processed')
            ->assertJsonPath('results.0.message', 'Idempotent replay.');

        $this->assertSame(1, FieldReport::query()->where('client_uuid', $clientUuid)->count());
    }

    public function test_sync_warm_returns_org_settings_and_field_reports(): void
    {
        [$user] = $this->actingAsSyncUser();

        FieldReport::query()->create([
            'client_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => 'Warm me',
            'status' => 'draft',
            'client_updated_at' => now(),
        ]);

        $this->getJson('/api/sync/warm')
            ->assertOk()
            ->assertJsonStructure([
                'org_settings',
                'resources' => ['field_reports'],
                'warmed_at',
            ]);
    }

    public function test_conflict_when_server_copy_is_newer(): void
    {
        [$user, $deviceId] = $this->actingAsSyncUser();

        $clientUuid = (string) Str::uuid();

        FieldReport::query()->create([
            'client_uuid' => $clientUuid,
            'user_id' => $user->id,
            'title' => 'Server wins',
            'status' => 'draft',
            'client_updated_at' => now(),
        ]);

        $this->postJson('/api/sync/batch', [
            'device_id' => $deviceId,
            'mutations' => [[
                'client_uuid' => $clientUuid,
                'resource' => 'field_reports',
                'action' => 'upsert',
                'body' => [
                    'client_uuid' => $clientUuid,
                    'title' => 'Stale client',
                    'status' => 'draft',
                ],
                'client_updated_at' => now()->subMinute()->toIso8601String(),
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'conflict');
    }

    public function test_force_overwrite_resolves_conflict(): void
    {
        [$user, $deviceId] = $this->actingAsSyncUser();
        $clientUuid = (string) Str::uuid();

        FieldReport::query()->create([
            'client_uuid' => $clientUuid,
            'user_id' => $user->id,
            'title' => 'Server wins',
            'status' => 'draft',
            'client_updated_at' => now(),
        ]);

        $this->postJson('/api/sync/batch', [
            'device_id' => $deviceId,
            'mutations' => [[
                'client_uuid' => $clientUuid,
                'resource' => 'field_reports',
                'action' => 'upsert',
                'body' => [
                    'client_uuid' => $clientUuid,
                    'title' => 'Client overwrite',
                    'status' => 'draft',
                    'force_overwrite' => true,
                ],
                'client_updated_at' => now()->subMinute()->toIso8601String(),
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'processed');

        $this->assertSame('Client overwrite', FieldReport::query()->where('client_uuid', $clientUuid)->value('title'));
    }

    public function test_revoked_device_cannot_sync(): void
    {
        [$user, $deviceId] = $this->actingAsSyncUser();

        SyncDevice::query()->where('device_id', $deviceId)->firstOrFail()->revoke();

        $this->postJson('/api/sync/batch', [
            'device_id' => $deviceId,
            'mutations' => [[
                'client_uuid' => (string) Str::uuid(),
                'resource' => 'field_reports',
                'action' => 'upsert',
                'body' => [
                    'client_uuid' => (string) Str::uuid(),
                    'title' => 'Blocked',
                ],
            ]],
        ])->assertForbidden();
    }

    public function test_issue_token_binds_device_without_invalidating_other_devices(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $first = $this->postJson('/api/sync/token', [
            'device_id' => (string) Str::uuid(),
            'device_name' => 'device-a',
        ])->assertOk()->json();

        $second = $this->postJson('/api/sync/token', [
            'device_id' => (string) Str::uuid(),
            'device_name' => 'device-b',
        ])->assertOk()->json();

        $this->assertNotSame($first['device_id'], $second['device_id']);
        $this->assertSame(2, SyncDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')->count());
        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_user_cannot_pull_another_users_field_reports(): void
    {
        [$user] = $this->actingAsSyncUser();
        $other = User::factory()->create(['is_active' => true]);

        FieldReport::query()->create([
            'client_uuid' => (string) Str::uuid(),
            'user_id' => $other->id,
            'title' => 'Secret',
            'status' => 'draft',
            'client_updated_at' => now(),
        ]);

        FieldReport::query()->create([
            'client_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => 'Mine',
            'status' => 'draft',
            'client_updated_at' => now(),
        ]);

        $response = $this->getJson('/api/sync/pull?resource=field_reports')->assertOk();

        $titles = collect($response->json('items'))->pluck('title')->all();

        $this->assertContains('Mine', $titles);
        $this->assertNotContains('Secret', $titles);
    }

    public function test_attachment_upload_is_idempotent(): void
    {
        Storage::fake('local');
        [$user, $deviceId] = $this->actingAsSyncUser();
        $parentUuid = (string) Str::uuid();
        $clientUuid = (string) Str::uuid();

        $file = UploadedFile::fake()->image('note.jpg', 100, 100);

        $first = $this->post('/api/sync/attachments', [
            'file' => $file,
            'client_uuid' => $clientUuid,
            'parent_client_uuid' => $parentUuid,
            'resource' => 'field_reports',
            'device_id' => $deviceId,
        ], ['Accept' => 'application/json'])->assertCreated()->json();

        $second = $this->post('/api/sync/attachments', [
            'file' => UploadedFile::fake()->image('note.jpg', 100, 100),
            'client_uuid' => $clientUuid,
            'parent_client_uuid' => $parentUuid,
            'resource' => 'field_reports',
            'device_id' => $deviceId,
        ], ['Accept' => 'application/json'])->assertOk()->json();

        $this->assertSame($first['attachment_id'], $second['attachment_id']);
        $this->assertTrue(FieldReport::query()->where('client_uuid', $parentUuid)->where('user_id', $user->id)->exists());
    }

    public function test_attachment_download_is_owner_only(): void
    {
        Storage::fake('local');
        [$owner, $deviceId] = $this->actingAsSyncUser();
        $parentUuid = (string) Str::uuid();
        $clientUuid = (string) Str::uuid();

        $upload = $this->post('/api/sync/attachments', [
            'file' => UploadedFile::fake()->image('note.jpg', 40, 40),
            'client_uuid' => $clientUuid,
            'parent_client_uuid' => $parentUuid,
            'resource' => 'field_reports',
            'device_id' => $deviceId,
        ], ['Accept' => 'application/json'])->assertCreated()->json();

        $other = User::factory()->create(['is_active' => true]);
        $this->actingAs($other);

        $this->get(route('attachments.download', $upload['attachment_id']))->assertForbidden();

        $this->actingAs($owner);
        $this->get(route('attachments.download', $upload['attachment_id']))->assertOk();
    }

    public function test_disabled_offline_returns_service_unavailable(): void
    {
        config(['offline.enabled' => false, 'kit.features.offline' => false]);

        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user, ['sync']);

        $this->postJson('/api/sync/batch', [
            'device_id' => (string) Str::uuid(),
            'mutations' => [],
        ])->assertStatus(503);
    }

    public function test_settings_manage_required_for_offline_page(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $this->withoutVite()
            ->get('/settings/offline')
            ->assertForbidden();

        Permission::findOrCreate('settings.manage');
        $user->givePermissionTo('settings.manage');

        $this->withoutVite()
            ->get('/settings/offline')
            ->assertOk();
    }
}
