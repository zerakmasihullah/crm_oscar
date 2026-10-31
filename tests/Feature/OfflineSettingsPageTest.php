<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\OfflineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class OfflineSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsManager(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('settings.manage');
        $role = Role::findOrCreate('Admin');
        $role->givePermissionTo('settings.manage');
        $user->assignRole($role);

        return $user;
    }

    public function test_offline_settings_page_renders_for_managers(): void
    {
        $user = $this->actingAsManager();

        $this->actingAs($user)
            ->get('/settings/offline')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Offline')
                ->has('settings')
                ->has('stats')
                ->has('devices'));
    }

    public function test_offline_settings_can_be_saved(): void
    {
        $user = $this->actingAsManager();

        $this->actingAs($user)
            ->put('/settings/offline', [
                'enabled' => true,
                'pwa_enabled' => true,
                'auto_sync' => false,
                'warm_cache' => true,
                'attachments_enabled' => true,
                'metrics_enabled' => false,
                'max_batch_size' => 25,
                'max_outbox_size' => 200,
                'attachment_max_bytes' => 1_048_576,
                'slow_request_ms' => 3000,
            ])
            ->assertRedirect();

        $this->assertTrue(OfflineSettings::enabled());
        $this->assertTrue(OfflineSettings::pwaEnabled());
        $this->assertFalse(OfflineSettings::autoSync());
        $this->assertTrue(OfflineSettings::attachmentsEnabled());
        $this->assertFalse(OfflineSettings::metricsEnabled());
        $this->assertSame(25, OfflineSettings::int('max_batch_size', 50));
    }
}
