<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GoogleAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class GoogleAuthSettingsTest extends TestCase
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

    public function test_google_auth_settings_page_renders_for_managers(): void
    {
        $user = $this->actingAsManager();

        $this->actingAs($user)
            ->get('/settings/google-auth')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/GoogleAuth')
                ->has('settings.enabled')
                ->has('settings.client_id')
                ->has('settings.client_secret_set')
                ->has('settings.redirect_uri'));
    }

    public function test_google_auth_credentials_can_be_saved_from_settings(): void
    {
        $user = $this->actingAsManager();

        $this->actingAs($user)
            ->put('/settings/google-auth', [
                'enabled' => true,
                'client_id' => 'settings-client-id',
                'client_secret' => 'settings-client-secret',
                'redirect_uri' => 'http://localhost/auth/google/callback',
            ])
            ->assertRedirect();

        $this->assertTrue(GoogleAuth::featureEnabled());
        $this->assertTrue(GoogleAuth::credentialsConfigured());
        $this->assertTrue(GoogleAuth::enabled());
        $this->assertSame('settings-client-id', GoogleAuth::clientId());
        $this->assertSame('settings-client-secret', GoogleAuth::decryptedClientSecret());
        $this->assertSame('settings-client-id', config('services.google.client_id'));
        $this->assertSame('settings-client-secret', config('services.google.client_secret'));
    }

    public function test_blank_client_secret_keeps_existing_secret(): void
    {
        $user = $this->actingAsManager();

        $this->actingAs($user)
            ->put('/settings/google-auth', [
                'enabled' => true,
                'client_id' => 'settings-client-id',
                'client_secret' => 'original-secret',
                'redirect_uri' => 'http://localhost/auth/google/callback',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->put('/settings/google-auth', [
                'enabled' => true,
                'client_id' => 'settings-client-id',
                'client_secret' => '',
                'redirect_uri' => 'http://localhost/auth/google/callback',
            ])
            ->assertRedirect();

        $this->assertSame('original-secret', GoogleAuth::decryptedClientSecret());
    }

    public function test_google_auth_can_be_toggled_off(): void
    {
        $user = $this->actingAsManager();

        GoogleAuth::save([
            'enabled' => true,
            'client_id' => 'settings-client-id',
            'client_secret' => 'settings-client-secret',
        ]);

        $this->actingAs($user)
            ->put('/settings/google-auth', [
                'enabled' => false,
                'client_id' => 'settings-client-id',
                'client_secret' => '',
                'redirect_uri' => 'http://localhost/auth/google/callback',
            ])
            ->assertRedirect();

        $this->assertFalse(GoogleAuth::featureEnabled());
        $this->assertFalse(GoogleAuth::enabled());
    }

    public function test_guests_cannot_open_google_auth_settings(): void
    {
        $this->get('/settings/google-auth')->assertRedirect(route('login'));
    }
}
