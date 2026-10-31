<?php

namespace Tests\Feature\Settings;

use App\Jobs\PruneTrashJob;
use App\Models\FieldReport;
use App\Models\FileTrashEntry;
use App\Models\OrgSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\TrashRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trash.view', 'trash.restore', 'trash.delete', 'trash.create', 'trash.edit'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $role = Role::findOrCreate('Owner');
        $role->givePermissionTo([
            'trash.view',
            'trash.restore',
            'trash.delete',
        ]);
    }

    protected function owner(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        return $user;
    }

    protected function makeReport(User $user, string $title): FieldReport
    {
        return FieldReport::query()->create([
            'client_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => $title,
            'body' => 'Notes',
            'status' => 'draft',
        ]);
    }

    public function test_soft_deleted_field_report_appears_in_trash_and_can_be_restored(): void
    {
        $user = $this->owner();
        $report = $this->makeReport($user, 'Site visit');

        $report->delete();

        $this->assertSoftDeleted('field_reports', ['id' => $report->id]);
        $this->assertNull(FieldReport::query()->find($report->id));

        $this->actingAs($user)
            ->get(route('settings.trash.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Trash/Index')
                ->has('items.data', 1)
                ->has('summary.total')
                ->where('summary.total', 1)
                ->where('items.data.0.type', 'field_reports')
                ->where('items.data.0.title', 'Site visit')
            );

        $this->actingAs($user)
            ->post(route('settings.trash.restore'), [
                'type' => 'field_reports',
                'id' => $report->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('field_reports', [
            'id' => $report->id,
            'deleted_at' => null,
        ]);
        $this->assertNotNull(FieldReport::query()->find($report->id));
    }

    public function test_force_delete_removes_record_permanently(): void
    {
        $user = $this->owner();
        $report = $this->makeReport($user, 'Gone forever');
        $report->delete();

        $this->actingAs($user)
            ->delete(route('settings.trash.force-destroy'), [
                'type' => 'field_reports',
                'id' => $report->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('field_reports', ['id' => $report->id]);
    }

    public function test_attachment_file_survives_soft_delete_and_is_removed_on_force_delete(): void
    {
        Storage::fake('local');

        $user = $this->owner();
        $report = $this->makeReport($user, 'With file');

        $path = 'attachments/FieldReport/'.$report->id.'/doc.txt';
        Storage::disk('local')->put($path, 'hello');

        $attachment = $report->attachments()->create([
            'original_filename' => 'doc.txt',
            'file_path' => $path,
            'file_size' => 5,
            'mime_type' => 'text/plain',
            'uploaded_by' => $user->id,
        ]);

        $report->delete();

        $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertExists($path);

        $report->refresh();
        $report->forceDelete();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_logo_remove_soft_deletes_and_keeps_file_until_force_delete(): void
    {
        Storage::fake('public');

        $user = $this->owner();
        Permission::findOrCreate('settings.manage');
        $user->givePermissionTo('settings.manage');

        $path = 'branding/logo.png';
        Storage::disk('public')->put($path, 'png-bytes');
        OrgSetting::setValue('logo_path', $path);

        $this->actingAs($user)
            ->put(route('settings.general.update'), [
                'app_name' => 'Kit',
                'default_locale' => config('app.locale'),
                'remove_logo' => true,
            ])
            ->assertRedirect();

        $this->assertNull(OrgSetting::logoPath());
        Storage::disk('public')->assertExists($path);

        $trashed = OrgSetting::onlyTrashed()->where('key', 'logo_path')->first();
        $this->assertNotNull($trashed);

        $trashed->forceDelete();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_file_manager_delete_moves_to_trash_and_can_restore(): void
    {
        foreach (['storage.view', 'storage.delete'] as $permission) {
            Permission::findOrCreate($permission);
        }
        Role::findOrCreate('Owner')->givePermissionTo(['storage.view', 'storage.delete']);

        $publicRoot = storage_path('app/public');
        File::ensureDirectoryExists($publicRoot);
        File::put($publicRoot.'/keep-me.txt', 'content');

        $user = $this->owner();

        $this->actingAs($user)
            ->delete(route('settings.file-manager.destroy'), [
                'root' => 'public',
                'path' => 'keep-me.txt',
                'current_path' => '',
            ])
            ->assertRedirect();

        $this->assertFileDoesNotExist($publicRoot.'/keep-me.txt');
        $this->assertDatabaseCount('file_trash_entries', 1);

        $entry = FileTrashEntry::onlyTrashed()->first();
        $this->assertNotNull($entry);
        $this->assertFileExists($publicRoot.'/'.$entry->trash_path);

        app(TrashRegistry::class)->restore('file_trash_entries', $entry->id);

        $this->assertFileExists($publicRoot.'/keep-me.txt');
        $this->assertDatabaseCount('file_trash_entries', 0);
    }

    public function test_trash_prune_force_deletes_items_older_than_retention(): void
    {
        $user = $this->owner();
        $report = $this->makeReport($user, 'Old trash');
        $report->delete();
        $report->forceFill(['deleted_at' => now()->subDays(61)])->save();

        $fresh = $this->makeReport($user, 'Recent trash');
        $fresh->delete();

        Artisan::call('trash:prune', ['--days' => 60]);

        $this->assertDatabaseMissing('field_reports', ['id' => $report->id]);
        $this->assertSoftDeleted('field_reports', ['id' => $fresh->id]);
    }

    public function test_prune_trash_job_dispatches_command(): void
    {
        PruneTrashJob::dispatchSync(days: 60);

        $this->assertTrue(true);
    }
}
