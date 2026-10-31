<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function enableGoogleAuth(): void
    {
        config([
            'kit.features.google_auth' => true,
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    protected function mockGoogleUser(
        string $id = 'google-123',
        string $email = 'person@example.com',
        string $name = 'Google Person',
    ): SocialiteUser {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        return $googleUser;
    }

    public function test_google_redirect_is_unavailable_when_disabled(): void
    {
        config(['kit.features.google_auth' => false]);

        $this->get(route('auth.google.redirect'))->assertNotFound();
    }

    public function test_login_page_exposes_google_auth_flag(): void
    {
        $this->enableGoogleAuth();

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/Login')
                ->where('canUseGoogleAuth', true));
    }

    public function test_google_callback_does_not_create_admin_users(): void
    {
        $this->enableGoogleAuth();
        $this->mockGoogleUser();

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'person@example.com']);
    }

    public function test_google_callback_logs_in_existing_staff_google_user(): void
    {
        $this->enableGoogleAuth();

        $user = User::factory()->create([
            'email' => 'person@example.com',
            'google_id' => 'google-123',
        ]);

        $this->mockGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->count());
    }

    public function test_google_callback_rejects_customer_accounts_for_admin(): void
    {
        $this->enableGoogleAuth();

        User::factory()->customer()->create([
            'email' => 'person@example.com',
            'google_id' => 'google-123',
        ]);

        $this->mockGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_google_callback_links_google_id_to_existing_staff_email_account(): void
    {
        $this->enableGoogleAuth();

        $user = User::factory()->unverified()->create([
            'email' => 'person@example.com',
            'google_id' => null,
        ]);

        $this->mockGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_disabled_users_cannot_sign_in_with_google(): void
    {
        $this->enableGoogleAuth();

        User::factory()->create([
            'email' => 'person@example.com',
            'google_id' => 'google-123',
            'is_active' => false,
        ]);

        $this->mockGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your account has been disabled.');

        $this->assertGuest();
    }

    public function test_google_callback_challenges_two_factor_users(): void
    {
        $this->enableGoogleAuth();

        $user = User::factory()->withTwoFactor()->create([
            'email' => 'person@example.com',
            'google_id' => 'google-123',
        ]);

        $this->mockGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertTrue(session()->has('login.id'));
        $this->assertSame($user->id, session('login.id'));
    }
}
