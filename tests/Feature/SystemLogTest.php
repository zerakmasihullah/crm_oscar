<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemLogReader;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_owner_can_view_system_logs_table(): void
    {
        $this->writeSampleLog('laravel.log', [
            '[2026-07-24 10:00:00] local.INFO: Boot complete',
            '[2026-07-24 10:01:00] local.ERROR: Something broke {"userId":1}',
            '[stacktrace]',
            '#0 /app/Example.php(10): fail()',
            '#1 {main}',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.system-logs.index', [
                'file' => 'laravel.log',
                'level' => 'error',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/SystemLogs/Index')
                ->has('files')
                ->has('entries.data', 1)
                ->where('entries.data.0.level', 'error')
                ->where('entries.data.0.has_stack', true)
                ->has('scan')
                ->has('levels', 8)
                ->has('filters')
            );
    }

    public function test_search_filters_log_entries(): void
    {
        $this->writeSampleLog('laravel.log', [
            '[2026-07-24 10:00:00] local.INFO: sync.batch ok',
            '[2026-07-24 10:01:00] local.WARNING: slow.request /dashboard',
            '[2026-07-24 10:02:00] local.ERROR: sync.mutation_failed',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.system-logs.index', [
                'file' => 'laravel.log',
                'search' => 'sync',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('entries.data', 2)
            );
    }

    public function test_owner_can_download_log_file(): void
    {
        $this->writeSampleLog('laravel.log', [
            '[2026-07-24 10:00:00] local.INFO: hello',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.system-logs.download', ['file' => 'laravel.log']))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_user_role_cannot_view_system_logs_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAs($user)
            ->get(route('settings.system-logs.index'))
            ->assertForbidden();
    }

    public function test_path_traversal_is_rejected(): void
    {
        $reader = app(SystemLogReader::class);

        $this->assertNull($reader->resolvePath('../.env'));
        $this->assertNull($reader->resolvePath('/etc/passwd'));
    }

    public function test_owner_can_still_access_log_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get('/log-viewer')
            ->assertOk();
    }

    public function test_owner_can_delete_log_file(): void
    {
        $this->writeSampleLog('old-errors.log', [
            '[2026-07-24 10:00:00] local.ERROR: old',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->assertFileExists(storage_path('logs/old-errors.log'));

        $this->actingAs($user)
            ->delete(route('settings.system-logs.destroy'), ['file' => 'old-errors.log'])
            ->assertRedirect(route('settings.system-logs.index'));

        $this->assertFileDoesNotExist(storage_path('logs/old-errors.log'));
    }

    public function test_user_role_cannot_delete_log_file(): void
    {
        $this->writeSampleLog('protected.log', [
            '[2026-07-24 10:00:00] local.INFO: keep',
        ]);

        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAs($user)
            ->delete(route('settings.system-logs.destroy'), ['file' => 'protected.log'])
            ->assertForbidden();

        $this->assertFileExists(storage_path('logs/protected.log'));
    }

    /**
     * @param  list<string>  $lines
     */
    protected function writeSampleLog(string $filename, array $lines): void
    {
        $directory = storage_path('logs');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/'.$filename, implode("\n", $lines)."\n");
    }
}
