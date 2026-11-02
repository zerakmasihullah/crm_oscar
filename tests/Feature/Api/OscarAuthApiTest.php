<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OscarAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->customer()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('secret12'),
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'login' => 'customer@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid login details');
    }

    public function test_login_returns_user_and_token(): void
    {
        User::factory()->customer()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('secret12'),
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'login' => 'customer@example.com',
            'password' => 'secret12',
        ])
            ->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'name'], 'token', 'token_type']);
    }

    public function test_login_blocks_inactive_users(): void
    {
        User::factory()->customer()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('secret12'),
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'login' => 'inactive@example.com',
            'password' => 'secret12',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'User account is not active');
    }

    public function test_register_creates_customer(): void
    {
        $this->postJson('/api/register', [
            'name' => 'New Guest',
            'email' => 'newguest@example.com',
            'password' => 'secret12',
        ])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('user.email', 'newguest@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'newguest@example.com',
            'role' => User::ROLE_CUSTOMER,
        ]);
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create([
            'email' => 'resetme@example.com',
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ])
            ->assertOk()
            ->assertJsonPath('status', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_legacy_forgot_password_path_works(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create([
            'email' => 'legacy@example.com',
        ]);

        $this->postJson('/api/user-verification/email', [
            'email' => $user->email,
        ])->assertOk()->assertJsonPath('status', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_fcm_update_requires_bearer_token(): void
    {
        $user = User::factory()->customer()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/update_fcm_token', [
            'fcm_token' => 'device-token-123',
        ])
            ->assertOk()
            ->assertJsonPath('user_id', $user->id);

        $this->assertSame('device-token-123', $user->fresh()->fcm_token);
    }
}
