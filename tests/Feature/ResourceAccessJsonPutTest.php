<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ResourceAccessJsonPutTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_json_put_persists_resource_access(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('settings.manage');
        $role = Role::findOrCreate('Admin');
        $role->givePermissionTo(['settings.manage']);
        $user->assignRole($role);

        $payload = [
            'enabled' => true,
            'chat_enabled' => true,
            'chat_allow_create' => true,
            'chat_allow_delete' => true,
            'chat_allow_analytics' => false,
            'langfuse_enabled' => false,
            'langfuse_public_key' => '',
            'langfuse_secret_key' => '',
            'langfuse_base_url' => 'https://cloud.langfuse.com',
            'field_edit_enabled' => true,
            'default_model_id' => null,
            'models' => [],
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
            'failover_enabled' => true,
            'base_system_prompt' => '',
            'action_prompts' => [],
            'max_input_chars' => 2500,
            'max_output_tokens' => 400,
            'daily_token_budget' => 80000,
            'panel_size' => 'comfortable',
            'return_tab' => 'data',
            'resource_access' => [
                'users' => [
                    'view' => true,
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                ],
                'roles' => [
                    'view' => false,
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                ],
            ],
        ];

        $this->actingAs($user)
            ->call(
                'PUT',
                '/settings/ai',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'text/html, application/xhtml+xml',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                    'HTTP_X_INERTIA' => 'true',
                ],
                json_encode($payload),
            )
            ->assertRedirect(route('settings.ai.data'));

        $this->assertFalse(AiSettings::resourceActionEnabled('users', 'create'));
        $this->assertFalse(AiSettings::resourceEnabled('roles'));

        $raw = \App\Models\OrgSetting::query()->where('key', 'ai.resource_access')->value('value');
        $this->assertIsString($raw);
        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['users']['create']);
        $this->assertFalse($decoded['roles']['view']);
    }

    public function test_resource_access_endpoint_persists_and_reloads(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('settings.manage');
        $role = Role::findOrCreate('Admin');
        $role->givePermissionTo(['settings.manage']);
        $user->assignRole($role);

        $this->actingAs($user)
            ->put('/settings/ai/resource-access', [
                'resource_access' => [
                    'users' => [
                        'view' => true,
                        'create' => false,
                        'edit' => false,
                        'delete' => false,
                    ],
                    'roles' => [
                        'view' => false,
                        'create' => false,
                        'edit' => false,
                        'delete' => false,
                    ],
                ],
            ])
            ->assertRedirect(route('settings.ai.data'));

        $this->assertFalse(AiSettings::resourceActionEnabled('users', 'create'));
        $this->assertFalse(AiSettings::resourceEnabled('roles'));

        $this->actingAs($user)
            ->get('/settings/ai/data')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('settings.resource_access.users.create', false)
                ->where('settings.resource_access.roles.view', false));
    }
}
