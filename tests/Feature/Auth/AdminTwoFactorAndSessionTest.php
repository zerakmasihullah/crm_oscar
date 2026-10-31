<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\UserSessionManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminTwoFactorAndSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_two_factor_gate_redirects_when_enabled(): void
    {
        Config::set('security.require_admin_two_factor', true);

        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile.edit', ['section' => 'two-factor']));
    }

    public function test_admin_with_two_factor_can_access_dashboard_when_gate_enabled(): void
    {
        Config::set('security.require_admin_two_factor', true);

        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->withTwoFactor()->create();
        $user->assignRole('Admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_revoking_other_sessions_bumps_session_version(): void
    {
        $user = User::factory()->create(['session_version' => 1]);

        $this->actingAs($user);
        session(['auth.session_version' => 1]);

        app(UserSessionManager::class)->revokeOthers($user, session()->getId());

        $this->assertSame(2, $user->fresh()->session_version);
        $this->assertSame(2, (int) session('auth.session_version'));
    }
}
