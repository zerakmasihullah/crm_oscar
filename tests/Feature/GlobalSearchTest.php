<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_cannot_search(): void
    {
        $this->getJson(route('search', ['q' => 'admin']))
            ->assertUnauthorized();
    }

    public function test_owner_can_search_users_and_pages(): void
    {
        $owner = User::factory()->create(['name' => 'Owner User', 'email' => 'owner@example.com']);
        $owner->assignRole('Owner');

        $target = User::factory()->create([
            'name' => 'Sabawoon Yaqubi',
            'email' => 'sabawoon@example.com',
        ]);

        $this->actingAs($owner)
            ->getJson(route('search', ['q' => 'sabawoon']))
            ->assertOk()
            ->assertJsonPath('query', 'sabawoon')
            ->assertJsonFragment([
                'id' => 'user:'.$target->id,
                'title' => 'Sabawoon Yaqubi',
                'type' => 'user',
            ]);

        $this->actingAs($owner)
            ->getJson(route('search', ['q' => 'users']))
            ->assertOk()
            ->assertJsonFragment([
                'href' => '/settings/users',
                'type' => 'page',
            ]);
    }

    public function test_user_role_does_not_see_users_in_search(): void
    {
        $user = User::factory()->create(['name' => 'Limited User']);
        $user->assignRole('User');

        User::factory()->create([
            'name' => 'Hidden Admin',
            'email' => 'hidden@example.com',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('search', ['q' => 'Hidden']))
            ->assertOk();

        $types = collect($response->json('groups'))
            ->flatMap(fn (array $group) => $group['items'])
            ->pluck('type');

        $this->assertFalse($types->contains('user'));
    }

}
