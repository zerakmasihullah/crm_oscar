<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\SystemControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SystemControlSettingsTest extends TestCase
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

    /**
     * @return array<string, bool>
     */
    protected function allEnabledPayload(array $overrides = []): array
    {
        $payload = [];

        foreach (array_keys(SystemControl::KEYS) as $key) {
            $payload[$key] = true;
        }

        return array_merge($payload, $overrides);
    }

    public function test_system_control_page_renders_for_managers(): void
    {
        $user = $this->actingAsManager();

        $this->actingAs($user)
            ->get('/settings/system-control')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/SystemControl')
                ->has('settings.registration')
                ->has('settings.multilingual')
                ->has('settings.reset_password')
                ->has('modules')
                ->has('definitions'));
    }

    public function test_system_control_can_disable_registration_and_forgot_password(): void
    {
        $user = $this->actingAsManager();

        $this->actingAs($user)
            ->put('/settings/system-control', $this->allEnabledPayload([
                'registration' => false,
                'reset_password' => false,
            ]))
            ->assertRedirect();

        $this->assertFalse(SystemControl::registrationEnabled());
        $this->assertFalse(SystemControl::resetPasswordEnabled());
        $this->assertFalse(Features::enabled(Features::registration()));
        $this->assertFalse(Features::enabled(Features::resetPasswords()));

        auth()->logout();

        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/Login')
                ->where('canRegister', false)
                ->where('canResetPassword', false));
    }

    public function test_system_control_can_disable_multilingual(): void
    {
        $user = $this->actingAsManager();

        $this->actingAs($user)
            ->put('/settings/system-control', $this->allEnabledPayload([
                'multilingual' => false,
            ]))
            ->assertRedirect();

        $this->assertFalse(SystemControl::multilingualEnabled());

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kitFeatures.multilingual', false));

        $this->post('/locale/fa')->assertNotFound();
    }

    public function test_guests_cannot_open_system_control_settings(): void
    {
        $this->get('/settings/system-control')->assertRedirect(route('login'));
    }
}
