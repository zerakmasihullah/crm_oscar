<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalizedDigits;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class KitPhaseExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_translations_ui_requires_permission(): void
    {
        config(['kit.features.translations_ui' => true]);

        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('Owner'));

        $this->actingAs($owner)
            ->get(route('settings.translations.index'))
            ->assertOk();
    }

    public function test_api_login_and_users_when_enabled(): void
    {
        config(['kit.features.api' => true]);

        $owner = User::factory()->create([
            'email' => 'api@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $owner->assignRole(Role::findByName('Owner'));

        $login = $this->postJson('/api/auth/login', [
            'email' => 'api@example.com',
            'password' => 'password',
        ]);

        $login->assertOk()->assertJsonPath('success', true);

        Sanctum::actingAs($owner);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_users_csv_export(): void
    {
        config(['kit.features.exports' => true]);

        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('Owner'));

        $this->actingAs($owner)
            ->get(route('settings.users.export'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_localized_digits(): void
    {
        $this->assertSame('۱۲۳', LocalizedDigits::format('123', 'fa'));
        $this->assertSame('123', LocalizedDigits::normalize('۱۲۳'));
    }

    public function test_status_change_logged_on_disable(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('Owner'));

        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($owner)
            ->patch(route('settings.users.update', $user), [
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('status_change_logs', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'to_status' => 'disabled',
        ]);
    }

    public function test_component_gallery_for_owner(): void
    {
        $this->withoutVite();

        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName('Owner'));

        $this->actingAs($owner)
            ->get(route('dev.gallery'))
            ->assertOk();
    }
}
