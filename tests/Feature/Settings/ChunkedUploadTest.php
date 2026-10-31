<?php

namespace Tests\Feature\Settings;

use App\Models\UploadSession;
use App\Models\User;
use App\Services\ChunkedUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class ChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $publicRoot;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['storage.view', 'storage.create', 'storage.edit', 'storage.delete'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $role = Role::findOrCreate('Owner');
        $role->givePermissionTo([
            'storage.view',
            'storage.create',
            'storage.edit',
            'storage.delete',
        ]);

        $this->publicRoot = storage_path('app/public');
        File::ensureDirectoryExists($this->publicRoot);
        File::ensureDirectoryExists(storage_path('app/private'));

        config([
            'uploads.max_bytes' => 50 * 1024 * 1024,
            'uploads.chunk_bytes' => 1024,
            'uploads.min_chunk_bytes' => 256,
            'uploads.max_chunk_bytes' => 16 * 1024 * 1024,
            'uploads.require_chunk_checksum' => true,
            'uploads.require_file_checksum' => false,
            'uploads.max_active_sessions_per_user' => 10,
            'uploads.session_ttl_hours' => 48,
            'uploads.min_free_bytes' => 0,
        ]);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        return $user;
    }

    public function test_guest_cannot_initiate_upload(): void
    {
        $this->postJson(route('settings.uploads.initiate'), [
            'filename' => 'big.bin',
            'size' => 2048,
            'root' => 'public',
            'path' => '',
        ])->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_initiate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('settings.uploads.initiate'), [
                'filename' => 'big.bin',
                'size' => 2048,
                'root' => 'public',
                'path' => '',
            ])
            ->assertForbidden();
    }

    public function test_blocked_extension_is_rejected(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->postJson(route('settings.uploads.initiate'), [
                'filename' => 'shell.php',
                'size' => 2048,
                'root' => 'public',
                'path' => '',
            ])
            ->assertStatus(422);
    }

    public function test_double_extension_script_trick_is_rejected(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->postJson(route('settings.uploads.initiate'), [
                'filename' => 'photo.php.jpg',
                'size' => 2048,
                'root' => 'public',
                'path' => '',
            ])
            ->assertStatus(422);
    }

    public function test_chunked_upload_assembles_file_with_checksums(): void
    {
        $user = $this->owner();
        $payload = random_bytes(2500);
        $chunkSize = 1024;
        $filename = 'dataset-'.uniqid('', true).'.bin';

        $initiate = $this->actingAs($user)
            ->postJson(route('settings.uploads.initiate'), [
                'filename' => $filename,
                'size' => strlen($payload),
                'mime' => 'application/octet-stream',
                'chunk_bytes' => $chunkSize,
                'root' => 'public',
                'path' => '',
            ])
            ->assertCreated()
            ->json('session');

        $this->assertSame(3, $initiate['total_chunks']);
        $sessionId = $initiate['id'];

        for ($i = 0; $i < 3; $i++) {
            $start = $i * $chunkSize;
            $piece = substr($payload, $start, $chunkSize);
            $checksum = hash('sha256', $piece);

            $temp = tempnam(sys_get_temp_dir(), 'chunk');
            file_put_contents($temp, $piece);
            $file = new UploadedFile($temp, "chunk-{$i}.part", 'application/octet-stream', null, true);

            $this->actingAs($user)
                ->post(route('settings.uploads.chunk', $sessionId), [
                    'index' => $i,
                    'checksum' => $checksum,
                    'chunk' => $file,
                ], ['Accept' => 'application/json'])
                ->assertOk();
        }

        // Idempotent re-upload of chunk 1 should succeed.
        $piece = substr($payload, 1024, 1024);
        $temp = tempnam(sys_get_temp_dir(), 'chunk');
        file_put_contents($temp, $piece);

        $this->actingAs($user)
            ->post(route('settings.uploads.chunk', $sessionId), [
                'index' => 1,
                'checksum' => hash('sha256', $piece),
                'chunk' => new UploadedFile($temp, 'chunk-1.part', 'application/octet-stream', null, true),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $complete = $this->actingAs($user)
            ->postJson(route('settings.uploads.complete', $sessionId))
            ->assertOk()
            ->json('session');

        $this->assertSame(UploadSession::STATUS_COMPLETED, $complete['status']);
        $this->assertSame(hash('sha256', $payload), $complete['actual_checksum']);
        $this->assertSame($filename, $complete['final_path']);
        $this->assertFileExists($this->publicRoot.'/'.$filename);
        $this->assertSame($payload, File::get($this->publicRoot.'/'.$filename));
        $this->assertFalse(Storage::disk('local')->exists('chunked-uploads/'.$sessionId.'/00000000.part'));

        @unlink($this->publicRoot.'/'.$filename);
    }

    public function test_tampered_chunk_checksum_is_rejected(): void
    {
        $user = $this->owner();

        $session = $this->actingAs($user)
            ->postJson(route('settings.uploads.initiate'), [
                'filename' => 'safe.txt',
                'size' => 12,
                'chunk_bytes' => 1024,
                'root' => 'public',
                'path' => '',
            ])
            ->assertCreated()
            ->json('session');

        $temp = tempnam(sys_get_temp_dir(), 'chunk');
        file_put_contents($temp, 'hello world!');

        $this->actingAs($user)
            ->post(route('settings.uploads.chunk', $session['id']), [
                'index' => 0,
                'checksum' => str_repeat('a', 64),
                'chunk' => new UploadedFile($temp, 'chunk.part', 'application/octet-stream', null, true),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_abort_removes_chunks(): void
    {
        $user = $this->owner();

        $sessionPayload = $this->actingAs($user)
            ->postJson(route('settings.uploads.initiate'), [
                'filename' => 'abort-me.bin',
                'size' => 2048,
                'chunk_bytes' => 1024,
                'root' => 'public',
                'path' => '',
            ])
            ->assertCreated()
            ->json('session');

        $piece = random_bytes(1024);
        $temp = tempnam(sys_get_temp_dir(), 'chunk');
        file_put_contents($temp, $piece);

        $this->actingAs($user)
            ->post(route('settings.uploads.chunk', $sessionPayload['id']), [
                'index' => 0,
                'checksum' => hash('sha256', $piece),
                'chunk' => new UploadedFile($temp, 'chunk.part', 'application/octet-stream', null, true),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('session.received_chunks', [0]);

        $session = UploadSession::query()->findOrFail($sessionPayload['id']);
        $chunkRelative = $session->chunk_directory.'/00000000.part';

        $this->assertTrue(
            Storage::disk($session->chunk_disk)->exists($chunkRelative),
            'Expected chunk at '.$chunkRelative,
        );

        $this->actingAs($user)
            ->deleteJson(route('settings.uploads.abort', $session->id))
            ->assertOk()
            ->assertJsonPath('session.status', UploadSession::STATUS_ABORTED);

        $this->assertFalse(Storage::disk($session->chunk_disk)->exists($chunkRelative));
    }

    public function test_another_user_cannot_access_session(): void
    {
        $owner = $this->owner();
        $other = $this->owner();

        $session = $this->actingAs($owner)
            ->postJson(route('settings.uploads.initiate'), [
                'filename' => 'private.bin',
                'size' => 1024,
                'chunk_bytes' => 1024,
                'root' => 'public',
                'path' => '',
            ])
            ->assertCreated()
            ->json('session');

        $this->actingAs($other)
            ->getJson(route('settings.uploads.status', $session['id']))
            ->assertForbidden();
    }

    public function test_prune_expires_stale_sessions(): void
    {
        $user = $this->owner();
        $service = app(ChunkedUploadService::class);

        $session = $service->initiate($user, [
            'filename' => 'stale.bin',
            'size' => 1024,
            'chunk_bytes' => 1024,
            'root' => 'public',
            'path' => '',
        ]);

        $session->forceFill(['expires_at' => now()->subMinute()])->save();

        $result = $service->prune();

        $this->assertSame(1, $result['expired']);
        $this->assertSame(UploadSession::STATUS_EXPIRED, $session->fresh()->status);
    }

    public function test_file_manager_exposes_chunked_limits(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->get(route('settings.file-manager.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('limits.max_upload_bytes', (int) config('uploads.max_bytes'))
                ->where('limits.chunk_bytes', (int) config('uploads.chunk_bytes'))
            );
    }

    public function test_attachment_upload_replaces_old_file(): void
    {
        Permission::findOrCreate('field_reports.edit');
        Permission::findOrCreate('field_reports.create');
        $user = $this->owner();
        $user->givePermissionTo(['field_reports.edit', 'field_reports.create']);

        $report = \App\Models\FieldReport::query()->create([
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'title' => 'Report',
            'status' => 'draft',
            'client_updated_at' => now(),
        ]);

        $oldPayload = 'old-content-here';
        $oldTemp = tempnam(sys_get_temp_dir(), 'old');
        file_put_contents($oldTemp, $oldPayload);
        $old = $report->storeAttachmentFromPath(
            $oldTemp,
            'old.txt',
            'text/plain',
            strlen($oldPayload),
            $user->id,
        );
        @unlink($oldTemp);

        $newPayload = 'brand-new-replacement';
        $chunkSize = 1024;

        $session = $this->actingAs($user)
            ->postJson(route('settings.uploads.initiate'), [
                'filename' => 'new.txt',
                'size' => strlen($newPayload),
                'chunk_bytes' => $chunkSize,
                'destination' => 'attachment',
                'attachable' => 'field_reports',
                'attachable_id' => $report->id,
                'replace_attachment_id' => $old->id,
            ])
            ->assertCreated()
            ->json('session');

        $temp = tempnam(sys_get_temp_dir(), 'chunk');
        file_put_contents($temp, $newPayload);

        $this->actingAs($user)
            ->post(route('settings.uploads.chunk', $session['id']), [
                'index' => 0,
                'checksum' => hash('sha256', $newPayload),
                'chunk' => new UploadedFile($temp, 'chunk.part', 'application/octet-stream', null, true),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $complete = $this->actingAs($user)
            ->postJson(route('settings.uploads.complete', $session['id']))
            ->assertOk()
            ->json('session');

        $this->assertNotNull($complete['attachment_id']);
        $this->assertSame($old->id, $complete['replaced_attachment_id']);
        $this->assertSoftDeleted('attachments', ['id' => $old->id]);
        $this->assertDatabaseHas('attachments', ['id' => $complete['attachment_id']]);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($old->file_path));

        $new = \App\Models\Attachment::query()->findOrFail($complete['attachment_id']);
        $this->assertSame(
            $newPayload,
            \Illuminate\Support\Facades\Storage::disk('local')->get($new->file_path),
        );
    }

    public function test_file_manager_replace_path_deletes_old_file(): void
    {
        $user = $this->owner();
        $oldRelative = 'replace-me-'.uniqid('', true).'.txt';
        File::put($this->publicRoot.'/'.$oldRelative, 'old bytes');

        $payload = 'new bytes for replace';
        $session = $this->actingAs($user)
            ->postJson(route('settings.uploads.initiate'), [
                'filename' => 'replacement.txt',
                'size' => strlen($payload),
                'chunk_bytes' => 1024,
                'destination' => 'file_manager',
                'root' => 'public',
                'path' => '',
                'replace_path' => $oldRelative,
            ])
            ->assertCreated()
            ->json('session');

        $temp = tempnam(sys_get_temp_dir(), 'chunk');
        file_put_contents($temp, $payload);

        $this->actingAs($user)
            ->post(route('settings.uploads.chunk', $session['id']), [
                'index' => 0,
                'checksum' => hash('sha256', $payload),
                'chunk' => new UploadedFile($temp, 'chunk.part', 'application/octet-stream', null, true),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('settings.uploads.complete', $session['id']))
            ->assertOk();

        $this->assertFileDoesNotExist($this->publicRoot.'/'.$oldRelative);
        $this->assertFileExists($this->publicRoot.'/replacement.txt');
        $this->assertSame($payload, File::get($this->publicRoot.'/replacement.txt'));
        @unlink($this->publicRoot.'/replacement.txt');
    }
}
