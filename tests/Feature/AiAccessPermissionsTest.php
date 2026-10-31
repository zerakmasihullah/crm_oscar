<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MisPermissionRegistrar;
use App\Support\AiSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiAccessPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_ai_permission_set_is_view_chat_assist_analytics_manage(): void
    {
        $names = app(MisPermissionRegistrar::class)->definedPermissionNames();
        $ai = array_values(array_filter($names, fn (string $name) => str_starts_with($name, 'ai.')));

        $this->assertSame(
            ['ai.view', 'ai.chat', 'ai.assist', 'ai.analytics', 'ai.manage'],
            $ai,
        );
        $this->assertFalse(Permission::query()->where('name', 'ai.create')->exists());
        $this->assertFalse(Permission::query()->where('name', 'ai.delete')->exists());
    }

    public function test_assist_requires_ai_assist_permission(): void
    {
        AiSettings::save([
            'enabled' => true,
            'chat_enabled' => true,
            'chat_allow_create' => false,
            'chat_allow_delete' => false,
            'chat_allow_analytics' => false,
            'langfuse_enabled' => false,
            'features' => [
                'grammar' => true,
                'improve' => true,
                'shorten' => true,
                'expand' => true,
                'describe' => true,
                'auto' => true,
                'custom' => true,
                'fill_form' => true,
            ],
            'failover_enabled' => false,
            'field_edit_enabled' => true,
            'max_input_chars' => 1000,
            'max_output_tokens' => 200,
            'daily_token_budget' => 10000,
            'panel_size' => 'comfortable',
            'models' => [],
        ]);

        $denied = User::factory()->create();
        $denied->givePermissionTo(['ai.chat']);

        $this->actingAs($denied)
            ->postJson('/ai/assist', [
                'action' => 'improve',
                'text' => 'hello',
            ])
            ->assertForbidden();

        $allowed = User::factory()->create();
        $allowed->givePermissionTo(['ai.assist']);

        $this->actingAs($allowed)
            ->postJson('/ai/assist', [
                'action' => 'improve',
                'text' => 'hello',
            ])
            ->assertStatus(422);
    }

    public function test_analytics_page_allows_view_only_but_generate_needs_analytics(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['ai.view']);

        $this->actingAs($viewer)
            ->get('/ai/analytics')
            ->assertOk();

        $this->actingAs($viewer)
            ->postJson('/ai/analytics/generate', ['prompt' => 'Overview'])
            ->assertForbidden();

        $runner = User::factory()->create();
        $runner->givePermissionTo(['ai.analytics']);

        $this->actingAs($runner)
            ->get('/ai/analytics')
            ->assertOk();
    }

    public function test_ai_settings_accepts_ai_manage(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(['ai.manage']);

        $this->actingAs($manager)
            ->get('/settings/ai')
            ->assertOk();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['ai.view']);

        $this->actingAs($viewer)
            ->get('/settings/ai')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get('/settings/ai/logs')
            ->assertOk();
    }
}
