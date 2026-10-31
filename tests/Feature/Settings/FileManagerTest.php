<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\FileManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $publicRoot;

    private string $privateRoot;

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
        $this->privateRoot = storage_path('app/private');

        File::ensureDirectoryExists($this->publicRoot);
        File::ensureDirectoryExists($this->privateRoot);
    }

    public function test_guest_cannot_view_file_manager(): void
    {
        $this->get(route('settings.file-manager.index'))
            ->assertRedirect();
    }

    public function test_user_without_permission_cannot_view_file_manager(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.file-manager.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_browse_storage_roots(): void
    {
        File::put($this->publicRoot.'/readme.txt', 'hello storage');

        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.file-manager.index', ['root' => 'public']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/FileManager/Index')
                ->has('roots', 2)
                ->where('listing.root', 'public')
                ->has('listing.entries')
                ->where('permissions.create', true)
            );
    }

    public function test_authorized_user_can_create_folder_upload_rename_and_delete(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->post(route('settings.file-manager.folders.store'), [
                'root' => 'public',
                'path' => '',
                'name' => 'branding-assets',
            ])
            ->assertRedirect();

        $this->assertDirectoryExists($this->publicRoot.'/branding-assets');

        $file = UploadedFile::fake()->create('logo.txt', 2, 'text/plain');

        $this->actingAs($user)
            ->post(route('settings.file-manager.upload'), [
                'root' => 'public',
                'path' => 'branding-assets',
                'file' => $file,
            ])
            ->assertRedirect();

        $this->assertFileExists($this->publicRoot.'/branding-assets/logo.txt');

        $this->actingAs($user)
            ->put(route('settings.file-manager.rename'), [
                'root' => 'public',
                'path' => 'branding-assets/logo.txt',
                'name' => 'school-logo.txt',
                'current_path' => 'branding-assets',
            ])
            ->assertRedirect();

        $this->assertFileExists($this->publicRoot.'/branding-assets/school-logo.txt');
        $this->assertFileDoesNotExist($this->publicRoot.'/branding-assets/logo.txt');

        $this->actingAs($user)
            ->get(route('settings.file-manager.index', [
                'root' => 'public',
                'path' => 'branding-assets',
                'preview' => 'branding-assets/school-logo.txt',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('preview.name', 'school-logo.txt')
                ->where('preview.is_text', true)
            );

        $this->actingAs($user)
            ->delete(route('settings.file-manager.destroy'), [
                'root' => 'public',
                'path' => 'branding-assets',
                'current_path' => '',
            ])
            ->assertRedirect();

        $this->assertDirectoryDoesNotExist($this->publicRoot.'/branding-assets');
    }

    public function test_path_traversal_is_blocked(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.file-manager.index', [
                'root' => 'public',
                'path' => '../private',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('listing.path', '')
            );
    }

    public function test_search_finds_nested_files(): void
    {
        File::ensureDirectoryExists($this->privateRoot.'/attachments/docs');
        File::put($this->privateRoot.'/attachments/docs/report-final.txt', 'report body');

        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.file-manager.index', [
                'root' => 'private',
                'search' => 'report-final',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('listing.entries', 1)
                ->where('listing.entries.0.name', 'report-final.txt')
            );
    }

    public function test_download_returns_file(): void
    {
        File::put($this->publicRoot.'/download-me.txt', 'payload');

        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.file-manager.download', [
                'root' => 'public',
                'path' => 'download-me.txt',
            ]))
            ->assertOk()
            ->assertDownload('download-me.txt');
    }

    public function test_service_rejects_unknown_root(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(FileManagerService::class)->list('framework');
    }
}
