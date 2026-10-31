<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_owner_can_view_users_and_login_logs(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.users.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('settings.login-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/AuthenticationLogs/Index')
                ->has('summary.total')
            );

        $this->actingAs($user)
            ->get(route('settings.system-logs.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('settings.action-logs.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('settings.email.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('settings.email.logs'))
            ->assertOk();
    }

    public function test_user_role_cannot_view_users_or_login_logs(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAs($user)
            ->get(route('settings.users.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('settings.login-logs.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('settings.system-logs.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('settings.action-logs.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('settings.email.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('settings.email.logs'))
            ->assertForbidden();
    }

    public function test_shared_auth_includes_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('auth.user.permissions')
                ->where('auth.user.roles', fn ($roles) => collect($roles)->contains('Admin'))
            );
    }
}
