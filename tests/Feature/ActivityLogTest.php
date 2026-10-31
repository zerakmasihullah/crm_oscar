<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_owner_can_view_action_logs_with_changes(): void
    {
        $actor = User::factory()->create(['name' => 'Audit Owner']);
        $actor->assignRole('Owner');

        $subject = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($actor);

        $subject->update([
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'updated',
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'causer_id' => $actor->id,
        ]);

        $response = $this->get(route('settings.action-logs.index', [
            'event' => 'updated',
            'search' => 'Updated Name',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/ActivityLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.event', 'updated')
            ->where('logs.data.0.causer.name', 'Audit Owner')
            ->where('logs.data.0.subject_label', 'Updated Name')
            ->has('logs.data.0.changes')
        );
    }

    public function test_user_role_cannot_view_action_logs(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAs($user)
            ->get(route('settings.action-logs.index'))
            ->assertForbidden();
    }

    public function test_action_logs_support_deep_filters(): void
    {
        $actor = User::factory()->create(['name' => 'Filter Actor']);
        $actor->assignRole('Owner');

        $this->actingAs($actor);

        $subject = User::factory()->create(['name' => 'Filter Subject']);
        $subject->update(['name' => 'Renamed Subject', 'email' => 'renamed@example.com']);

        $this->actingAs($actor)
            ->get(route('settings.action-logs.index', [
                'event' => 'updated',
                'subject_type' => 'User',
                'causer_id' => (string) $actor->id,
                'attribute' => 'name',
                'has_changes' => '1',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/ActivityLogs/Index')
                ->where('filters.event', 'updated')
                ->where('filters.subject_type', 'User')
                ->where('filters.causer_id', (string) $actor->id)
                ->where('filters.attribute', 'name')
                ->where('filters.has_changes', '1')
                ->has('summary.total')
                ->has('subjectTypes')
                ->has('attributes')
                ->has('causers')
                ->has('logs.data', 1)
                ->where('logs.data.0.event', 'updated')
                ->where('logs.data.0.subject_label', 'Renamed Subject')
            );
    }
}
